<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\DTO\User as UserDto;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SecurityControllerTest extends AbstractTest
{
    private $urlBase;

    private $arrUsers;

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

        $user = new UserDto();
        $user->setUsername('user@test.com');
        $user->setPassword('user@test.com');
        $user->setToken('header.eyJpYXQiOjE2MTY2ODA4MDIsImV4cCI6MTYxNjY4NDQwM
        iwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidXNlckB0ZXN0LmNvbSJ9.signature');
        $user->setRefreshToken('refresh_token_user');
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(200);

        $admin = new UserDto();
        $admin->setUsername('admin@test.com');
        $admin->setPassword('admin@test.com');
        $admin->setToken('header.eyJpYXQiOjE2MTY2ODEzMTEsImV4cCI6MTYxNjY4NDkxMSwicm9sZXMiOls
        iUk9MRV9TVVBFUl9BRE1JTiIsIlJPTEVfVVNFUiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.signature');
        $admin->setRefreshToken('refresh_token_admin');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(200);

        $this->arrUsers = [
            'user@test.com' => $user,
            'admin@test.com' => $admin,
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
                self::$container->get('doctrine'),
                $this->billingUrlBase,
                $this->billingApiVersion,
                $this->httpClient,
                $this->serializer,
                $this->security,
                $this->arrUsers
            )
        );
    }

    public function testSuccessfulAuthorization(): void
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        foreach ($this->arrUsers as $i => $value) {
            // проверка перехода на страницу авторизации (/login)
            $crawler = $client->request('GET', $this->urlBase);
            $this->assertResponseOk();
            self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

            // работа с формой
            $form = $crawler->selectButton('Войти')->form();
            $form['email'] = $value->getUsername();
            $form['password'] = $value->getPassword();
            $client->submit($form);

            // редирект на /courses/
            $crawler = $client->followRedirect();
            $this->assertResponseOk();
            self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

            // проверка авторизированного пользователя
            /** @var User $user */
            $user = $this->security->getUser();
            self::assertNotNull($user);
            self::assertEquals($value->getUsername(), $user->getUsername());
            self::assertContains($value->getRoles()[0], $user->getRoles());

            // разлогинивание аккаунта /logout
            $linkLogout = $crawler->selectLink('Выход')->link();
            $crawler = $client->click($linkLogout);
            $this->assertResponseRedirect();
            self::assertEquals('/logout', $client->getRequest()->getPathInfo());

            // редирект на /
            $crawler = $client->followRedirect();
            $this->assertResponseRedirect();
            self::assertEquals('/', $client->getRequest()->getPathInfo());

            // редирект на /courses/
            $crawler = $client->followRedirect();
            $this->assertResponseOk();
            self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
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
            ],
            [
                'email' => 'user@test.com',
                'password' => '123456',
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
            $crawler = $client->submit($form);

            // проверка ошибки
            self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
            $error = $crawler->filter('.alert .alert-danger')->text();
            self::assertEquals($error, $errorForm[$i]);
        }
    }
}
