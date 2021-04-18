<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\DTO\User as UserDto;
use App\Entity\Course;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CourseControllerTest extends AbstractTest
{
    private $urlBase;
    private $errorsForm;
    private $elementsForm;
    private $checkData;

    private $billingUrlBase;
    private $billingApiVersion;

    private $httpClient;
    private $serializer;
    private $security;

    private $arrUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = self::$container->get('http_client');
        $this->serializer = self::$container->get('jms_serializer');
        $this->security = self::$container->get('security.helper');
        $this->billingUrlBase = 'billing.study-on.local';
        $this->billingApiVersion = 'v1';
        $this->urlBase = '/courses';
        $this->elementsForm = [
            [
                'name' => 'code',
                'labelFor' => 'course_code',
            ],
            [
                'name' => 'price',
                'labelFor' => 'course_price',
            ],
            [
                'name' => 'type',
                'labelFor' => 'course_type',
            ],
            [
                'name' => 'name',
                'labelFor' => 'course_name',
            ],
            [
                'name' => 'description',
                'labelFor' => 'course_description',
            ],
        ];
        $this->errorsForm = [
            'code' => [
                'uniqueFalse' => 'Данный код уже занят',
                'longLength' => 'Длина должна быть не более 255 символов',
                'empty' => 'Заполните код',
            ],
            'price' => [
                'errorType' => 'This value is not valid.',
                'empty' => 'Заполните цену',
            ],
            'name' => [
                'longLength' => 'Длина должна быть не более 255 символов',
                'empty' => 'Заполните название',
            ],
            'description' => ['longLength' => 'Длина должна быть не более 1000 символов'],
        ];
        // последний элемент массива с верными данными
        $this->checkData = [
            [
                'code' => [
                    'text' => '',
                    'request' => $this->errorsForm['code']['empty'],
                ],
                'price' => [
                    'text' => '',
                    'request' => $this->errorsForm['price']['empty'],
                ],
                'type' => [
                    'text' => 'buy',
                    'request' => 0,
                ],
                'name' => [
                    'text' => '',
                    'request' => $this->errorsForm['name']['empty'],
                ],
                'description' => [
                    'text' => str_repeat('1', 1001),
                    'request' => $this->errorsForm['description']['longLength'],
                ],
            ],
            [
                'code' => [
                    'text' => 'statistics_course',
                    'request' => $this->errorsForm['code']['uniqueFalse'],
                ],
                'price' => [
                    'text' => 'gdsgdsg',
                    'request' => $this->errorsForm['price']['errorType'],
                ],
                'type' => [
                    'text' => 'buy',
                    'request' => 0,
                ],
                'name' => [
                    'text' => str_repeat('1', 256),
                    'request' => $this->errorsForm['name']['longLength'],
                ],
                'description' => [
                    'text' => 'TestDescriptionCourse',
                    'request' => 0,
                ],
            ],
            [
                'code' => [
                    'text' => str_repeat('1', 256),
                    'request' => $this->errorsForm['code']['longLength'],
                ],
                'price' => [
                    'text' => '145.10',
                    'request' => 0,
                ],
                'type' => [
                    'text' => 'buy',
                    'request' => 0,
                ],
                'name' => [
                    'text' => 'TestNameCourse',
                    'request' => 0,
                ],
                'description' => [
                    'text' => 'TestDescriptionCourse',
                    'request' => 0,
                ],
            ],
            [
                'code' => [
                    'text' => 'TestCodeCourse',
                    'request' => 0,
                ],
                'price' => [
                    'text' => '145.10',
                    'request' => 0,
                ],
                'type' => [
                    'text' => 'rent',
                    'request' => 0,
                ],
                'name' => [
                    'text' => 'TestNameCourse',
                    'request' => 0,
                ],
                'description' => [
                    'text' => 'TestDescriptionCourse',
                    'request' => 0,
                ],
            ],
        ];

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

    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    public function testPayCourse(): void
    {
        // авторизация под админом
        $authUser = $this->authorization($this->arrUsers['admin@test.com']);

        /// Начало 1 теста - успешная аренда -->

        // проверка перехода на страницу курсов (/courses/)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        $codeCourse = 'statistics_course';
        $crawler = $crawler->filter("#course-$codeCourse");

        $linkPayCourse = $crawler->selectLink('Арендовать за 30р.')->link();
        $crawler = self::getClient()->click($linkPayCourse);

        // ищем нужное модальное окно
        $crawler = $crawler->filter("#modalCenter-$codeCourse");
        $linkConfirmation = $crawler->selectLink('Подтвердить')->link();
        $crawler = self::getClient()->click($linkConfirmation);

        // редирект на pay
        $this->assertResponseRedirect();
        self::assertEquals(
            $this->urlBase . '/pay',
            self::getClient()->getRequest()->getPathInfo()
        );
        $crawler = self::getClient()->followRedirect();

        //возвращениене /courses/
        $this->assertResponseOk();
        self::assertEquals(
            $this->urlBase . '/',
            self::getClient()->getRequest()->getPathInfo()
        );

        // проверка флеш сообщения
        $crawler = self::getClient()->getCrawler();
        $messageFlash = $crawler->filter('.alert')->text();
        self::assertEquals($messageFlash, 'Успешное выполнение операции!');

        // проверка изменения сообщения на курсе о покупке
        $crawler = $crawler->filter("#course-$codeCourse .line");
        $dateTimeRent = $crawler->filter('.price')->text();
        self::assertEquals(
            $dateTimeRent,
            'Арендовано до ' . (new \DateTime('+ 7 day'))->format('Y-m-d T H:i:s')
        );

        $linkProfile = self::getClient()->getCrawler()->selectLink('Профиль')->link();
        $crawler = self::getClient()->click($linkProfile);
        $this->assertResponseOk();
        self::assertEquals('/profile', self::getClient()->getRequest()->getPathInfo());

        // проверка изменения баланса после покупки
        $balance = $crawler->filter('#balance')->text();
        self::assertEquals($balance, 'Баланс: ' . $this->arrUsers[$authUser->getEmail()]->getBalance());

        // проверка транзакций после добавления
        $linkTransactions = self::getClient()->getCrawler()->selectLink('История транзакций')->link();
        $crawler = self::getClient()->click($linkTransactions);
        $this->assertResponseOk();

        self::assertEquals('/transactions', self::getClient()->getRequest()->getPathInfo());
        $historyTransactions = [
            [
                'type' => 'deposit',
                'amount' => 200,
                'courseCode' => null,
            ],
            [
                'type' => 'payment',
                'amount' => 50,
                'courseCode' => 'deep_learning',
            ],
            [
                'type' => 'payment',
                'amount' => 30,
                'courseCode' => $codeCourse,
            ],
        ];

        foreach ($historyTransactions as $i => $transaction) {
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
        }

        /// Конец 1 тест <--

        /// Начало 2 теста - не успешная покупка(недостаточно средств) -->

        // проверка перехода на страницу курсов (/courses/)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        $codeCourse = 'c_sharp_course';
        $crawler = self::getClient()->getCrawler();
        $crawler = $crawler->filter("#course-$codeCourse");

        $linkPayCourse = $crawler->selectLink('Купить за 250р.')->link();
        $crawler = self::getClient()->click($linkPayCourse);

        // ищем нужное модальное окно
        $crawler = $crawler->filter("#modalCenter-$codeCourse");
        $linkConfirmation = $crawler->selectLink('Подтвердить')->link();
        $crawler = self::getClient()->click($linkConfirmation);

        // редирект на pay
        $this->assertResponseRedirect();
        self::assertEquals(
            $this->urlBase . '/pay',
            self::getClient()->getRequest()->getPathInfo()
        );
        $crawler = self::getClient()->followRedirect();

        //возвращениене /courses/
        $this->assertResponseOk();
        self::assertEquals(
            $this->urlBase . '/',
            self::getClient()->getRequest()->getPathInfo()
        );

        // проверка флеш сообщения
        $crawler = self::getClient()->getCrawler();
        $messageFlash = $crawler->filter('.alert')->text();
        self::assertEquals($messageFlash, 'На вашем счету недостаточно средств');

        /// Конец 2 теста <--
    }

    public function testAccessFunctionUser(): void
    {
        // авторизация под user
        $this->authorization($this->arrUsers['user@test.com']);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // проверка на отсутсвие кнопки
        $button = $crawler->selectLink('Новый курс')->count();
        self::assertEquals($button, 0);

        self::getClient()->request('GET', $this->urlBase . '/new');
        $this->assertResponseForbidden();

        foreach ($courses as $course) {
            /* @var Course $course */
            $crawler = self::getClient()->request('GET', $this->urlBase . '/' . $course->getId());
            $this->assertResponseOk();

            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Добавить урок')->count();
            self::assertEquals($button, 0);

            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Редактировать курс')->count();
            self::assertEquals($button, 0);

            // проверка на отсутсвие кнопки
            $button = $crawler->selectButton('Удалить')->count();
            self::assertEquals($button, 0);

            self::getClient()->request('GET', $this->urlBase . '/' . $course->getId() . '/edit');
            $this->assertResponseForbidden();

            self::getClient()->request('DELETE', $this->urlBase . '/' . $course->getId());
            $this->assertResponseForbidden();
        }
    }

    public function testAccessFunctionAdmin(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        self::getClient()->request('GET', $this->urlBase . '/new');
        $this->assertResponseOk();

        foreach ($courses as $course) {
            /* @var Course $course */
            self::getClient()->request('GET', $this->urlBase . '/' . $course->getId());
            $this->assertResponseOk();

            $crawler = self::getClient()->getCrawler();
            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Добавить урок')->count();
            self::assertEquals($button, 1);

            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Редактировать курс')->count();
            self::assertEquals($button, 1);

            $button = $crawler->selectButton('Удалить')->count();
            self::assertEquals($button, 1);

            self::getClient()->request('DELETE', $this->urlBase . '/' . $course->getId());
            $this->assertResponseRedirect();

            self::getClient()->request('GET', $this->urlBase . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testNotExistentPage(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $maxId = $em->getRepository(Course::class)->findMaxId();

        // проверка по переходу к несуществующему курсу
        self::getClient()->request('GET', $this->urlBase . '/' . ($maxId + 1));
        $this->assertResponseNotFound();

        // проверка по переходу к несуществующему редактору курсу
        self::getClient()->request('GET', $this->urlBase . '/' . ($maxId + 1) . '/edit');
        $this->assertResponseNotFound();
    }

    public function testCourseEdit(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение объекта Course к которому планируем перейти
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        /** @var Course $course */
        $course = $em->getRepository(Course::class)->findOneBy(
            ['code' => 'python_course']
        );
        self::assertNotNull($course);

        // получение в html нужного курса
        $crawlers = $crawler->filter('.course')->each(function (Crawler $node, $i) {
            return $node;
        });
        foreach ($crawlers as $node) {
            $hrefLink = $node->selectLink('Просмотреть')->attr('href');
            $id = explode('/', $hrefLink)[2];
            if ($course->getId() === (int) $id) {
                $crawler = $node;
            }
        }

        // переход на страницу курса(/courses/{id})
        $linkCourse = $crawler->selectLink('Просмотреть')->link();
        $crawler = self::getClient()->click($linkCourse);
        self::assertEquals(
            $this->urlBase . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // переход на страницу редактирования курса(/courses/{id}/edit)
        $linkEditCourse = $crawler->selectLink('Редактировать курс')->link();
        $crawler = self::getClient()->click($linkEditCourse);
        self::assertEquals(
            $this->urlBase . '/' . $course->getId() . '/edit',
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // проверка что данные в форме верно взяты(отображаются в html) из базы данных
        $form = $crawler->selectButton('Обновить')->form();
        self::assertEquals($course->getCode(), $form['course[code]']->getValue());
        self::assertEquals($course->getName(), $form['course[name]']->getValue());
        self::assertEquals($course->getDescription(), $form['course[description]']->getValue());

        // проверка формы на валидность данных

        foreach ($this->checkData as $i => $iValue) {
            $crawler = $this->checkForm($form, $iValue);
            foreach ($this->elementsForm as $value) {
                $errorCrawler = $crawler->filter("label[for=$value[labelFor]] .form-error-message");
                if ($errorCrawler->count() > 0) {
                    self::assertEquals($iValue[$value['name']]['request'], $errorCrawler->text());
                } else {
                    self::assertEquals($iValue[$value['name']]['request'], 0);
                }
                if ($i + 1 != count($this->checkData)) {
                    self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
                } else {
                    $this->assertResponseRedirect();
                }
            }
        }

        // редирект на /courses/{id}
        $crawler = self::getClient()->followRedirect();
        self::assertEquals(
            $this->urlBase . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // проверка что курс изменился на html странице и отображается
        $newNameCourse = $crawler->filter('h1')->text();
        self::assertEquals('TestNameCourse', $newNameCourse);

        $newDescriptionCourse = $crawler->filter('.course-description')->text();
        self::assertEquals('TestDescriptionCourse', $newDescriptionCourse);

        // проверка что данные добавлены верно в базу данных
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(
            [
                'id' => $course->getId(),
                'code' => 'TestCodeCourse',
                'name' => 'TestNameCourse',
                'description' => 'TestDescriptionCourse',
            ]
        );
        self::assertNotNull($course);
    }

    public function testCourseShow(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем просмотреть любой из курсов
        for ($i = 0; $i < $countCourses; ++$i) {
            $crawler = $crawler->filter('.course')->eq($i);
            $nameCourse = $crawler->filter('h3')->text();
            $descriptionCourse = $crawler->filter('.course-description')->text();
            $hrefLink = $crawler->selectLink('Просмотреть')->attr('href');
            $idCourse = explode('/', $hrefLink)[2];

            // получение объекта Course к которому планируем перейти
            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(
                [
                    'id' => $idCourse,
                    'description' => $descriptionCourse,
                    'name' => $nameCourse,
                ]
            );
            self::assertNotNull($course);

            // переход на страницу курса(/courses/{id})
            $linkCourse = $crawler->selectLink('Просмотреть')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlBase . '/' . $course->getId(),
                self::getClient()->getRequest()->getPathInfo()
            );
            $this->assertResponseOk();

            // проверка на совпадение имени курса
            self::assertEquals($nameCourse, $crawler->filter('h1')->text());

            // проверка на совпадение описания курса
            self::assertEquals($descriptionCourse, $crawler->filter('.course-description')->text());

            // проверка что количество уроков в html совпадает с количество уроков в базе
            $countLessonsDB = count($course->getLessons());
            self::assertEquals($countLessonsDB, $crawler->filter('li')->count());

            // проверка что названия уроков в html совпадает с базой
            foreach ($course->getLessons() as $j => $jValue) {
                self::assertEquals(
                    $jValue->getName(),
                    $crawler->filter('li')->eq($j)->text()
                );
            }

            // проверка что правильно перешли обратно ко всем курсам (/courses)
            $linkCourses = $crawler->selectLink('К списку курсов')->link();
            $crawler = self::getClient()->click($linkCourses);
            self::assertEquals($this->urlBase . '/', self::getClient()->getRequest()->getPathInfo());
            $this->assertResponseOk();
        }
    }

    public function testCourseDelete(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем удалить любой из курсов
        for ($i = 0; $i < $countCourses; ++$i) {
            $crawler = $crawler->filter('.course')->eq(0);
            $hrefLink = $crawler->selectLink('Просмотреть')->attr('href');
            $idCourse = explode('/', $hrefLink)[2];

            // получение объекта Course к которому планируем перейти
            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(
                ['id' => $idCourse]
            );
            // проверка что Course найден
            self::assertNotNull($course);

            // переход на страницу курса(/courses/{id})
            $linkCourse = $crawler->selectLink('Просмотреть')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlBase . '/' . $course->getId(),
                self::getClient()->getRequest()->getPathInfo()
            );
            $this->assertResponseOk();

            // форма удаления курса
            $form = $crawler->selectButton('Удалить')->form();
            $crawler = self::getClient()->submit($form);
            // проверка редиректа
            $this->assertResponseRedirect();
            $crawler = self::getClient()->followRedirect();

            // проверка что правильно перешли на /courses
            $this->assertResponseOk();
            self::assertEquals($this->urlBase . '/', self::getClient()->getRequest()->getPathInfo());

            // проверка что курс удалился на html странице и не отображается
            $newCountCourses = $crawler->filter('.course')->count();
            self::assertEquals($countCourses - ($i + 1), $newCountCourses);

            // проверка что больше нету ссылки "пройти" удаленного курса
            $crawler->filter('.course')->each(function (Crawler $node, $i) use ($idCourse) {
                $hrefLink = $node->selectLink('Просмотреть')->attr('href');
                $id = explode('/', $hrefLink)[2];
                self::assertNotEquals($idCourse, $id);
            });

            // проверка что данный курс удален из базы данных
            $course = $em->getRepository(Course::class)->findOneBy(
                ['id' => $course->getId()]
            );
            self::assertNull($course);
        }
    }

    public function testCourseNew(): void
    {
        // авторизация под админом
        $this->authorization($this->arrUsers['admin@test.com']);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка перехода на страницу создания нового курса(/courses/new)
        $linkNewCourse = $crawler->selectLink('Новый курс')->link();
        $crawler = self::getClient()->click($linkNewCourse);
        self::assertEquals($this->urlBase . '/new', self::getClient()->getRequest()->getPathInfo());
        $this->assertResponseOk();

        // проверка формы на валидность данные
        $form = $crawler->selectButton('Сохранить')->form();
        foreach ($this->checkData as $i => $iValue) {
            $crawler = $this->checkForm($form, $iValue);
            foreach ($this->elementsForm as $value) {
                $errorCrawler = $crawler->filter("label[for=$value[labelFor]] .form-error-message");
                if ($errorCrawler->count() > 0) {
                    self::assertEquals($iValue[$value['name']]['request'], $errorCrawler->text());
                } else {
                    self::assertEquals($iValue[$value['name']]['request'], 0);
                }
                if ($i + 1 !== count($this->checkData)) {
                    self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
                } else {
                    $this->assertResponseRedirect();
                }
            }
        }

        // редирект на /courses
        $crawler = self::getClient()->followRedirect();
        self::assertEquals($this->urlBase . '/', self::getClient()->getRequest()->getPathInfo());
        self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());

        // проверка что новый курс создался на html странице и отображается
        $newCountCourses = $crawler->filter('.course')->count();
        self::assertEquals($countCourses + 1, $newCountCourses);

        $nameNewCourse = $crawler->filter('.course h3')->last()->text();
        self::assertEquals('TestNameCourse', $nameNewCourse);

        $descriptionNewCourse = $crawler->filter('.course .course-description')->last()->text();
        self::assertEquals('TestDescriptionCourse', $descriptionNewCourse);

        // проверка что данные добавлены верно в базу данных
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(
            [
                'code' => 'TestCodeCourse',
                'name' => 'TestNameCourse',
                'description' => 'TestDescriptionCourse',
            ]
        );
        self::assertNotNull($course);
    }

    private function checkForm(Form $form, array $data): Crawler
    {
        $form['course[code]'] = $data['code']['text'];
        $form['course[price]'] = $data['price']['text'];
        $form['course[type]'] = $data['type']['text'];
        $form['course[name]'] = $data['name']['text'];
        $form['course[description]'] = $data['description']['text'];

        return self::getClient()->submit($form);
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
