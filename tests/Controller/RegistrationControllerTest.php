<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RegistrationControllerTest extends AbstractTest
{
    private $urlBase;

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
        $this->urlBase = '/register';
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

    public function testSuccessfulRegistration(): void
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        // проверка перехода на страницу авторизации (/register)
        $crawler = $client->request('GET', $this->urlBase);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        $email = 'test@test.com';
        $password = '123456';
        $role = 'ROLE_USER';

        // работа с формой
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration_form[username]'] = $email;
        $form['registration_form[password][first]'] = $password;
        $form['registration_form[password][second]'] = $password;
        $crawler = $client->submit($form);
        $this->assertResponseRedirect();

        // редирект на /courses
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        // проверка авторизированного пользователя
        /** @var User $user */
        $user = $this->security->getUser();
        self::assertNotNull($user);
        self::assertEquals($email, $user->getUsername());
        self::assertContains($role, $user->getRoles());
    }

    public function tesFailedRegistration(): void
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        // проверка перехода на страницу авторизации (/register)
        $crawler = $client->request('GET', $this->urlBase);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        $dataForm = [
            [
                'email' => 'user@test.com',
                'password' => '123',
                'passwordSecond' => '123',
                'error' => [
                    [
                        'element' => '#password',
                        'text' => 'Ваш пароль менее 6 символов.',
                    ],
                ],
            ],
            [
                'email' => 'user@test.com',
                'password' => '123456',
                'passwordSecond' => '123',
                'error' => [
                    [
                        'element' => '#password',
                        'text' => 'Пароли должны совпадать',
                    ],
                ],
            ],
            [
                'email' => 'user@test.com',
                'password' => '123456',
                'passwordSecond' => '123456',
                'error' => [
                    [
                        'element' => '.alert .alert-danger',
                        'text' => 'Данная почта уже зарегистрированна.',
                    ],
                ],
            ],
        ];

        foreach ($dataForm as $value) {
            // работа с формой
            $form = $crawler->selectButton('Зарегистрироваться')->form();
            $form['registration_form[username]'] = $value['email'];
            $form['registration_form[password][first]'] = $value['password'];
            $form['registration_form[password][second]'] = $value['passwordSecond'];
            $crawler = $client->submit($form);

            self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());
            self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());

            // проверка ошибок
            foreach ($value['error'] as $error) {
                $text = $crawler->filter("$error[element]")->text();
                self::assertEquals($text, $error['text']);
            }
        }
    }
}
