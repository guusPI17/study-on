<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\DTO\User as UserDto;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;

class UserControllerTest extends AbstractTest
{
    private $urlBase;

    private $arrUsers;

    private $billingUrlBase;
    private $billingApiVersion;

    private $httpClient;
    private $serializer;
    private $security;

    private $historyTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = self::$container->get('http_client');
        $this->serializer = self::$container->get('jms_serializer');
        $this->security = self::$container->get('security.helper');
        $this->billingUrlBase = 'billing.study-on.local';
        $this->billingApiVersion = 'v1';
        $this->urlBase = '/profile';

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

        $this->historyTransactions = [
            [
                'type' => 'deposit',
                'amount' => 200,
                'courseCode' => null,
                'createdAt' => '2000-01-22 UTC 00:00:00',
            ],
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

    public function testProfile(): void
    {
        // авторизация
        $this->authorization($this->arrUsers['user@test.com']);
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

        self::assertEquals($email, 'E-mail: ' . $this->arrUsers['user@test.com']->getUsername());
        self::assertEquals($role, 'Роль: ' . 'Пользователь');
        self::assertEquals($balance, 'Баланс: ' . $this->arrUsers['user@test.com']->getBalance());
    }

    public function testTransactions(): void
    {
        // авторизация
        $this->authorization($this->arrUsers['user@test.com']);
        $client = self::getClient();

        // переход в профиль
        $crawler = $client->getCrawler();
        $linkProfile = $crawler->selectLink('Профиль')->link();
        $crawler = $client->click($linkProfile);
        $this->assertResponseOk();
        self::assertEquals($this->urlBase, $client->getRequest()->getPathInfo());

        // переход в транзакции
        $linkTransactions = $crawler->selectLink('История транзакций')->link();
        $crawler = self::getClient()->click($linkTransactions);

        $this->assertResponseOk();
        self::assertEquals('/transactions', $client->getRequest()->getPathInfo());

        foreach ($this->historyTransactions as $i => $transaction) {
            $crawlerTr = $crawler->filter('.tr-transaction')->eq($i);
            self::assertEquals(
                $crawlerTr->filter('td')->eq(0)->text(),
                'deposit' === $transaction['type'] ? 'Пополнение' : 'Списание'
            );
            self::assertEquals(
                $crawlerTr->filter('td')->eq(1)->text(),
                $transaction['amount']
            );
            self::assertEquals(
                $crawlerTr->filter('td')->eq(2)->text(),
                $transaction['courseCode'] ? 'Покупка курса ' . $transaction['courseCode'] : 'Пополнение счета'
            );
            self::assertEquals(
                $crawlerTr->filter('td')->eq(3)->text(),
                $transaction['createdAt']
            );
        }
    }

    private function authorization(UserDto $dataAccount): User
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
        $form['email'] = $dataAccount->getUsername();
        $form['password'] = $dataAccount->getPassword();
        $client->submit($form);

        // редирект на /courses/
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        // проверка авторизированного пользователя
        /** @var User $user */
        $user = $this->security->getUser();

        self::assertNotNull($user);
        self::assertEquals($dataAccount->getUsername(), $user->getUsername());
        self::assertContains($dataAccount->getRoles()[0], $user->getRoles());
        return $user;
    }
}
