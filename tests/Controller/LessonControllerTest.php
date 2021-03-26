<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LessonControllerTest extends AbstractTest
{
    private $urlLessons;
    private $urlCourses;
    private $errorsForm;
    private $elementsForm;
    private $checkData;

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
        $this->elementsForm = [
            [
                'name' => 'name',
                'labelFor' => 'lesson_name',
            ],
            [
                'name' => 'content',
                'labelFor' => 'lesson_content',
            ],
            [
                'name' => 'number',
                'labelFor' => 'lesson_number',
            ],
        ];
        $this->errorsForm = [
            'name' => [
                'longLength' => 'Длина должна быть не более 255 символов',
                'empty' => 'Заполните название',
            ],
            'content' => ['empty' => 'Заполните содержимое урока'],
            'number' => [
                'longLength' => 'Разрешенное число от 1 до 10000',
                'empty' => 'Заполните номер урока',
                'errorType' => 'This value is not valid.',
            ],
        ];
        // последний элемент массива с верными данными
        $this->checkData = [
            [
                'name' => [
                    'text' => '',
                    'request' => $this->errorsForm['name']['empty'],
                ],
                'content' => [
                    'text' => '',
                    'request' => $this->errorsForm['content']['empty'],
                ],
                'number' => [
                    'text' => '',
                    'request' => $this->errorsForm['number']['empty'],
                ],
            ],
            [
                'name' => [
                    'text' => str_repeat('1', 256),
                    'request' => $this->errorsForm['name']['longLength'],
                ],
                'content' => [
                    'text' => 'TestContentLesson',
                    'request' => 0,
                ],
                'number' => [
                    'text' => '10001',
                    'request' => $this->errorsForm['number']['longLength'],
                ],
            ],
            [
                'name' => [
                    'text' => 'TestNameLesson',
                    'request' => 0,
                ],
                'content' => [
                    'text' => 'TestContentLesson',
                    'request' => 0,
                ],
                'number' => [
                    'text' => '13g5',
                    'request' => $this->errorsForm['number']['errorType'],
                ],
            ],
            [
                'name' => [
                    'text' => 'TestNameLesson',
                    'request' => 0,
                ],
                'content' => [
                    'text' => 'TestContentLesson',
                    'request' => 0,
                ],
                'number' => [
                    'text' => '10',
                    'request' => 0,
                ],
            ],
        ];
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
        $this->httpClient = self::$container->get('http_client');
        $this->serializer = self::$container->get('jms_serializer');
        $this->security = self::$container->get('security.helper');
        $this->billingUrlBase = 'billing.study-on.local';
        $this->billingApiVersion = 'v1';
        $this->urlCourses = '/courses';
        $this->urlLessons = '/lessons';
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

    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
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

    public function testLackOfAdminFunctionality()
    {
        // авторизация под user
        $this->authorization($this->dataUser);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        $courses = $em->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            /* @var Course $course */
            self::getClient()->request('GET', $this->urlLessons . '/new?course_id=' . $course->getId());
            $this->assertResponseForbidden();
        }

        $lessons = $em->getRepository(Lesson::class)->findAll();
        foreach ($lessons as $lesson) {
            /* @var Lesson $lesson */
            $crawler = self::getClient()->request('GET', $this->urlLessons . '/' . $lesson->getId());
            $this->assertResponseOk();

            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Редактировать')->count();
            self::assertEquals($button, 0);

            // проверка на отсутсвие кнопки
            $button = $crawler->selectLink('Удалить')->count();
            self::assertEquals($button, 0);

            self::getClient()->request('GET', $this->urlLessons . '/' . $lesson->getId() . '/edit');
            $this->assertResponseForbidden();

            self::getClient()->request('DELETE', $this->urlLessons . '/' . $lesson->getId());
            $this->assertResponseForbidden();
        }
    }

    public function testPageResponseOk()
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        $courses = $em->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            /* @var Course $course */
            self::getClient()->request('GET', $this->urlLessons . '/new?course_id=' . $course->getId());
            $this->assertResponseOk();
        }

        $lessons = $em->getRepository(Lesson::class)->findAll();
        foreach ($lessons as $lesson) {
            /* @var Lesson $lesson */
            self::getClient()->request('GET', $this->urlLessons . '/' . $lesson->getId());
            $this->assertResponseOk();

            self::getClient()->request('DELETE', $this->urlLessons . '/' . $lesson->getId());
            $this->assertResponseRedirect();

            self::getClient()->request('GET', $this->urlLessons . '/' . $lesson->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testPageResponseNotFound(): void
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $maxIdLesson = $em->getRepository(Lesson::class)->findMaxId();
        $maxIdCourse = $em->getRepository(Course::class)->findMaxId();

        // проверка по созданию урока у несуществующего курса
        self::getClient()->request('GET', $this->urlLessons . '/new?course_id=' . ($maxIdCourse + 1));
        $this->assertResponseNotFound();

        // проверка по переходу к несуществующему уроку
        self::getClient()->request('GET', $this->urlLessons . '/' . ($maxIdLesson + 1));
        $this->assertResponseNotFound();

        // проверка по переходу к несуществующему редактору урока
        self::getClient()->request('GET', $this->urlLessons . '/' . ($maxIdLesson + 1) . '/edit');
        $this->assertResponseNotFound();
    }

    public function testLessonNew(): void
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        // проверка перехода на страницу курсов
        $crawler = self::getClient()->request('GET', $this->urlCourses . '/');
        $this->assertResponseOk();

        // получение объекта Course к которому планируем перейти
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        /** @var Course $course */
        $course = $em->getRepository(Course::class)->findOneBy(
            ['code' => 'C202103011958AG']
        );
        self::assertNotNull($course);

        // получение в html нужного курса
        $crawlers = $crawler->filter('.course')->each(function (Crawler $node, $i) {
            return $node;
        });
        foreach ($crawlers as $node) {
            $hrefLink = $node->selectLink('Пройти')->attr('href');
            $id = explode('/', $hrefLink)[2];
            if ($course->getId() === (int) $id) {
                $crawler = $node;
            }
        }

        // переход на страницу курса(/courses/{id})
        $linkCourse = $crawler->selectLink('Пройти')->link();
        $crawler = self::getClient()->click($linkCourse);
        self::assertEquals(
            $this->urlCourses . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // переход на страницу добавление урока (/lessons/new?course_id={id})
        $linkNewLesson = $crawler->selectLink('Добавить урок')->link();
        $crawler = self::getClient()->click($linkNewLesson);
        self::assertEquals(
            $this->urlLessons . '/new?course_id=' . $course->getId(),
            self::getClient()->getRequest()->getRequestUri()
        );
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
            $this->urlCourses . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // проверка что данные добавлены верно в базу данных
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy(
            [
                'name' => 'TestNameLesson',
                'content' => 'TestContentLesson',
                'number' => 10,
                'course' => $course,
            ]
        );
        self::assertNotNull($lesson);

        // проверка что количество уроков в html совпадает с количество уроков в базе после добавления
        $course = $em->getRepository(Course::class)->findOneBy(
            ['id' => $course->getId()]
        );
        $countLessonsDB = count($course->getLessons());
        self::assertEquals($countLessonsDB, $crawler->filter('li')->count());

        // проверка что названия уроков в html совпадает с базой
        /** @var Lesson[] $lessons */
        $lessons = $em->getRepository(Lesson::class)->findBy(
            ['course' => $course],
            ['number' => 'ASC']
        );
        for ($j = 0; $j < $countLessonsDB; ++$j) {
            self::assertEquals(
                $lessons[$j]->getName(),
                $crawler->filter('li')->eq($j)->text()
            );
        }
    }

    public function testLessonShow(): void
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlCourses . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем просмотреть любой урок на любом курсе
        for ($i = 0; $i < $countCourses; ++$i) {
            $crawler = $crawler->filter('.course')->eq($i);
            $nameCourse = $crawler->filter('h3')->text();
            $hrefLink = $crawler->selectLink('Пройти')->attr('href');
            $idCourse = explode('/', $hrefLink)[2];

            // получение объекта Course к которому планируем перейти
            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(
                ['id' => $idCourse]
            );
            self::assertNotNull($course);

            // переход на страницу курса(/courses/{id})
            $linkCourse = $crawler->selectLink('Пройти')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlCourses . '/' . $course->getId(),
                self::getClient()->getRequest()->getPathInfo()
            );
            $this->assertResponseOk();

            // проверка всех уроков
            $countLesson = $crawler->filter('li')->count();
            for ($j = 0; $j < $countLesson; ++$j) {
                $nameLesson = $crawler->filter('li')->eq($j)->text();
                $hrefLink = $crawler->selectLink($nameLesson)->attr('href');
                $idLesson = explode('/', $hrefLink)[2];
                $linkLesson = $crawler->selectLink($nameLesson)->link();

                // получение объекта Lesson к которому планируем перейти
                /** @var EntityManagerInterface $em */
                $em = self::getEntityManager();
                /** @var Lesson $lesson */
                $lesson = $em->getRepository(Lesson::class)->findOneBy(
                    [
                        'id' => $idLesson,
                    ]
                );
                self::assertNotNull($lesson);

                // переход к уроку
                $crawler = self::getClient()->click($linkLesson);
                self::assertEquals(
                    $this->urlLessons . '/' . $lesson->getId(),
                    self::getClient()->getRequest()->getPathInfo()
                );
                $this->assertResponseOk();

                // проверка названия урока
                self::assertEquals($crawler->filter('h1')->text(), $nameLesson);

                // проверка названия курса
                self::assertEquals($crawler->filter('.link-course')->text(), $nameCourse);

                // проверка содержания урока
                self::assertEquals($crawler->filter('.lesson-content')->text(), $lesson->getContent());

                // проверка перехода обратно к урокам курса
                $linkCourse = $crawler->selectLink($nameCourse)->link();
                $crawler = self::getClient()->click($linkCourse);
                self::assertEquals(
                    $this->urlCourses . '/' . $course->getId(),
                    self::getClient()->getRequest()->getPathInfo()
                );
                $this->assertResponseOk();
            }
            // проверка что правильно перешли обратно ко всем курсам (/courses)
            $linkCourses = $crawler->selectLink('К списку курсов')->link();
            $crawler = self::getClient()->click($linkCourses);
            self::assertEquals($this->urlCourses . '/', self::getClient()->getRequest()->getPathInfo());
            $this->assertResponseOk();
        }
    }

    public function testLessonEdit(): void
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        // проверка перехода на страницу курсов
        $crawler = self::getClient()->request('GET', $this->urlCourses . '/');
        $this->assertResponseOk();

        // получение объекта Course к которому планируем перейти
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        /** @var Course $course */
        $course = $em->getRepository(Course::class)->findOneBy(
            ['code' => 'C202103011958AG']
        );
        self::assertNotNull($course);

        // получение в html нужного курса
        $crawlers = $crawler->filter('.course')->each(function (Crawler $node, $i) {
            return $node;
        });
        foreach ($crawlers as $node) {
            $hrefLink = $node->selectLink('Пройти')->attr('href');
            $id = explode('/', $hrefLink)[2];
            if ($course->getId() === (int) $id) {
                $crawler = $node;
            }
        }

        // переход на страницу курса(/courses/{id})
        $linkCourse = $crawler->selectLink('Пройти')->link();
        $crawler = self::getClient()->click($linkCourse);
        self::assertEquals(
            $this->urlCourses . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // переход на страницу урока
        $hrefLink = $crawler->selectLink('Введение в статистику')->attr('href');
        $idLesson = explode('/', $hrefLink)[2];
        /** @var Lesson $lesson */
        $lesson = $em->getRepository(Lesson::class)->findOneBy(
            ['id' => $idLesson]
        );
        $linkLesson = $crawler->selectLink('Введение в статистику')->link();
        $crawler = self::getClient()->click($linkLesson);
        self::assertEquals(
            $this->urlLessons . '/' . $lesson->getId(),
            self::getClient()->getRequest()->getRequestUri()
        );
        $this->assertResponseOk();

        // переход в редактирование урока
        $linkLesson = $crawler->selectLink('Редактировать')->link();
        $crawler = self::getClient()->click($linkLesson);
        self::assertEquals(
            $this->urlLessons . '/' . $lesson->getId() . '/edit',
            self::getClient()->getRequest()->getRequestUri()
        );
        $this->assertResponseOk();

        // проверка что данные в форме верно взяты(отображаются в html) из базы данных
        $form = $crawler->selectButton('Обновить')->form();
        self::assertEquals($lesson->getName(), $form['lesson[name]']->getValue());
        self::assertEquals($lesson->getContent(), $form['lesson[content]']->getValue());
        self::assertEquals($lesson->getNumber(), $form['lesson[number]']->getValue());
        self::assertEquals($course->getId(), $form['lesson[course]']->getValue());

        // проверка формы на валидность данные
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

        // редирект на /lesson/{id}
        $crawler = self::getClient()->followRedirect();
        self::assertEquals(
            $this->urlLessons . '/' . $lesson->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );

        // проверка что данные изменены верно в базе данных
        $em = self::getEntityManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy(
            [
                'name' => 'TestNameLesson',
                'content' => 'TestContentLesson',
                'number' => 10,
                'course' => $course,
            ]
        );
        self::assertNotNull($lesson);

        // проверка названия урока
        self::assertEquals($crawler->filter('h1')->text(), $lesson->getName());

        // проверка названия курса
        self::assertEquals($crawler->filter('.link-course')->text(), $lesson->getCourse()->getName());

        // проверка содержания урока
        self::assertEquals($crawler->filter('.lesson-content')->text(), $lesson->getContent());

        // проверка перехода обратно к урокам курса
        $linkCourse = $crawler->selectLink($lesson->getCourse()->getName())->link();
        $crawler = self::getClient()->click($linkCourse);
        self::assertEquals(
            $this->urlCourses . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // редирект на /courses/{id}
        self::assertEquals(
            $this->urlCourses . '/' . $course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // проверка что названия уроков в html совпадает с базой
        /** @var Lesson[] $lessons */
        $lessons = $em->getRepository(Lesson::class)->findBy(
            ['course' => $course],
            ['number' => 'ASC']
        );
        $countLesson = count($course->getLessons());
        for ($j = 0; $j < $countLesson; ++$j) {
            self::assertEquals(
                $lessons[$j]->getName(),
                $crawler->filter('li')->eq($j)->text()
            );
        }
    }

    public function testLessonDelete(): void
    {
        // авторизация под админом
        $this->authorization($this->dataAdmin);

        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlCourses . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем просмотреть любой урок на любом курсе
        for ($i = 0; $i < $countCourses; ++$i) {
            $crawler = $crawler->filter('.course')->eq($i);
            $nameCourse = $crawler->filter('h3')->text();
            $hrefLink = $crawler->selectLink('Пройти')->attr('href');
            $idCourse = explode('/', $hrefLink)[2];

            // получение объекта Course к которому планируем перейти
            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(
                ['id' => $idCourse]
            );
            self::assertNotNull($course);

            // переход на страницу курса(/courses/{id})
            $linkCourse = $crawler->selectLink('Пройти')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlCourses . '/' . $course->getId(),
                self::getClient()->getRequest()->getPathInfo()
            );
            $this->assertResponseOk();

            // проверка всех уроков
            $countLesson = $crawler->filter('li')->count();
            for ($j = 0; $j < $countLesson; ++$j) {
                $nameLesson = $crawler->filter('li')->eq(0)->text();
                $hrefLink = $crawler->selectLink($nameLesson)->attr('href');
                $idLesson = explode('/', $hrefLink)[2];
                $linkLesson = $crawler->selectLink($nameLesson)->link();

                // получение объекта Lesson к которому планируем перейти
                /** @var EntityManagerInterface $em */
                $em = self::getEntityManager();
                /** @var Lesson $lesson */
                $lesson = $em->getRepository(Lesson::class)->findOneBy(
                    [
                        'id' => $idLesson,
                    ]
                );
                self::assertNotNull($lesson);

                // переход к уроку
                $crawler = self::getClient()->click($linkLesson);
                self::assertEquals(
                    $this->urlLessons . '/' . $lesson->getId(),
                    self::getClient()->getRequest()->getPathInfo()
                );
                $this->assertResponseOk();

                // форма удаления урока
                $form = $crawler->selectButton('Удалить')->form();
                $crawler = self::getClient()->submit($form);
                // проверка редиректа
                $this->assertResponseRedirect();
                $crawler = self::getClient()->followRedirect();

                // проверка что правильно перешли к урокам курса
                $this->assertResponseOk();
                self::assertEquals(
                    $this->urlCourses . '/' . $course->getId(),
                    self::getClient()->getRequest()->getPathInfo()
                );

                // проверка что урок удалился на html странице и не отображается
                $newCountLesson = $crawler->filter('li')->count();
                self::assertEquals($countLesson - ($j + 1), $newCountLesson);

                // проверка что больше нету ссылки на удаленный урок
                $crawler->filter('li')->each(function (Crawler $node, $i) use ($idLesson) {
                    $nameLesson = $node->filter('li')->eq($i)->text();
                    $hrefLink = $node->selectLink($nameLesson)->attr('href');
                    $id = explode('/', $hrefLink)[2];
                    self::assertNotEquals($idLesson, $id);
                });

                // проверка что данный урок удален из базы данных
                $lesson = $em->getRepository(Course::class)->findOneBy(
                    ['id' => $lesson->getId()]
                );
                self::assertNull($lesson);
            }
            // проверка что правильно перешли обратно ко всем курсам (/courses)
            $linkCourses = $crawler->selectLink('К списку курсов')->link();
            $crawler = self::getClient()->click($linkCourses);
            self::assertEquals($this->urlCourses . '/', self::getClient()->getRequest()->getPathInfo());
            $this->assertResponseOk();
        }
    }

    private function checkForm(Form $form, array $data): Crawler
    {
        $form['lesson[name]'] = $data['name']['text'];
        $form['lesson[content]'] = $data['content']['text'];
        $form['lesson[number]'] = $data['number']['text'];

        return self::getClient()->submit($form);
    }
}
