<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;

class SecurityControllerTest extends AbstractTest
{
    private $urlBase;

    private $dataUser;
    private $dataAdmin;

    private $billingUrlBase;
    private $billingApiVersion;

    private $httpClient;
    private $serializer;
    private $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = self::$container->get('http_client');
        $this->serializer = self::$container->get('jms_serializer');
        $this->security = self::$container->get('security.helper');
        $this->billingUrlBase = 'billing.study-on.local';
        $this->billingApiVersion = 'v1';
        $this->urlBase = '/login';
        $this->dataAdmin = [
            'email' => 'admin@test.com',
            'password' => 'admin@test.com',
            'roles' => 'ROLE_SUPER_ADMIN',
        ];
        $this->dataUser = [
            'email' => 'user@test.com',
            'password' => 'user@test.com',
            'roles' => 'ROLE_USER',
        ];
    }

    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    private function serviceSubstitution(): void
    {
        // запрещаем перезагрузку ядра, чтобы не сбросилась подмена сервиса при запросе
        self::getClient()->disableReboot();

        // подмена сервиса
        self::getClient()->getContainer()->set(
            'App\Service\BillingClient',
            new BillingClientMock(
                $this->billingUrlBase,
                $this->billingApiVersion,
                $this->httpClient,
                $this->serializer,
                $this->security
            )
        );
    }

    public function testSuccessfulAuthorization(): void
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        // проверка перехода на страницу авторизации (/login)
        $crawler = $client->request('GET', $this->urlBase);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        // данные разных пользователей
        $dataAccounts = [
            $this->dataAdmin,
            $this->dataUser,
        ];
        foreach ($dataAccounts as $i => $value) {
            // работа с формой
            $form = $crawler->selectButton('Войти')->form();
            $form['email'] = $value['email'];
            $form['password'] = $value['password'];
            $form['_remember_me'] = true;
            $crawler = $client->submit($form);

            // только во время 2-ой и более авторизации видит редирект на /
            if ($i > 0) {
                // редирект на /
                $crawler = $client->followRedirect();
                $this->assertResponseRedirect();
                self::assertEquals('/', $client->getRequest()->getPathInfo());
            }

            // редирект на /course
            $crawler = $client->followRedirect();
            $this->assertResponseOk();
            self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

            // проверка авторизированного пользователя
            /** @var User $user */
            $user = $this->security->getUser();
            self::assertNotNull($user);
            self::assertEquals($value['email'], $user->getUsername());
            self::assertContains($value['roles'], $user->getRoles());

            /*// перезагружаем браузер
            $crawler = self::getClient()->reload();

            // проверка класса токена
            self::assertInstanceOf(RememberMeToken::class, $this->security->getToken());*/

            // разлогинивание аккаунта /logout
            $linkLogout = $crawler->selectLink('Выход')->link();
            $crawler = $client->click($linkLogout);
            $this->assertResponseRedirect();
            self::assertEquals('/logout', $client->getRequest()->getPathInfo());

            // редирект на /
            $crawler = $client->followRedirect();
            $this->assertResponseRedirect();
            self::assertEquals('/', $client->getRequest()->getPathInfo());

            // редирект на /login
            $crawler = $client->followRedirect();
            $this->assertResponseOk();
            self::assertEquals('/login', $client->getRequest()->getPathInfo());
        }
    }

    public function tesFailedAuthorization(): void
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        // проверка перехода на страницу авторизации (/login)
        $crawler = $client->request('GET', $this->urlBase);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        $dataForm = [
            [
                'email' => 'user@test.com',
                'password' => '123',
                'rememberMe' => true,
            ],
            [
                'email' => 'user@test.com',
                'password' => '123456',
                'rememberMe' => false,
            ],
        ];
        $errorForm = [
            'Invalid credentials.',
            'Invalid credentials.',
        ];

        foreach ($dataForm as $i => $value) {
            // работа с формой
            $form = $crawler->selectButton('Войти')->form();
            $form['email'] = $value['email'];
            $form['password'] = $value['password'];
            $form['_remember_me'] = $value['rememberMe'];
            $crawler = $client->submit($form);

            // проверка ошибки
            self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
            $error = $crawler->filter('.alert .alert-danger')->text();
            self::assertEquals($error, $errorForm[$i]);
        }
    }
}
