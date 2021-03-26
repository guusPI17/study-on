<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;

class UserControllerTest extends AbstractTest
{
    private $urlBase;

    private $dataAdmin;
    private $dataUser;

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
        $this->urlBase = '/profile';

        $this->dataAdmin = [
            'email' => 'admin@test.com',
            'password' => 'admin@test.com',
            'roles' => 'ROLE_SUPER_ADMIN',
            'balance' => 0,
        ];
        $this->dataUser = [
            'email' => 'user@test.com',
            'password' => 'user@test.com',
            'roles' => 'ROLE_USER',
            'balance' => 100,
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

    /**
     * Авторизация
     */
    private function authorization($dataAccount)
    {
        // подмена сервиса
        $this->serviceSubstitution();
        $client = self::getClient();

        // проверка перехода на страницу авторизации (/login)
        $crawler = $client->request('GET', '/login');
        $this->assertResponseOk();
        self::assertEquals('/login', $client->getRequest()->getPathInfo());

        // работа с формой
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = $dataAccount['email'];
        $form['password'] = $dataAccount['password'];
        $crawler = $client->submit($form);

        // редирект на /course
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        // проверка авторизированного пользователя
        /** @var User $user */
        $user = $this->security->getUser();
        self::assertNotNull($user);
        self::assertEquals($dataAccount['email'], $user->getUsername());
        self::assertContains($dataAccount['roles'], $user->getRoles());
    }

    public function testCurrent(): void
    {
        // авторизация
        $this->authorization($this->dataUser);
        $client = self::getClient();

        // переход в профиль
        $crawler = $client->getCrawler();
        $linkProfile = $crawler->selectLink('Профиль')->link();
        $crawler = $client->click($linkProfile);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        // проверка содержимого профиля
        $email = $crawler->filter('#email')->text();
        $role = $crawler->filter('#role')->text();
        $balance = $crawler->filter('#balance')->text();

        self::assertEquals($email, 'E-mail: ' . $this->dataUser['email']);
        self::assertEquals($role, 'Роль: ' . 'Пользователь');
        self::assertEquals($balance, 'Баланс: ' . $this->dataUser['balance']);
    }
}
