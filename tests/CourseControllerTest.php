<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CourseControllerTest extends AbstractTest
{
    private $urlBase = '/courses';

    private $errors = [
        'code' => [
            'uniqueFalse' => 'Данный код уже занят',
            'longLength' => 'Длина должна быть не более 255 символов',
            'empty' => 'Заполните код',
        ],
        'name' => [
            'longLength' => 'Длина должна быть не более 255 символов',
            'empty' => 'Заполните название',
        ],
        'description' => [
            'longLength' => 'Длина должна быть не более 1000 символов',
        ],
    ];

    public function testPageResponseOk()
    {
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        self::getClient()->request('GET', $this->urlBase . '/new');
        $this->assertResponseOk();

        foreach ($courses as $course) {
            /** @var Course $course */
            self::getClient()->request('GET', $this->urlBase . '/' . $course->getId());
            $this->assertResponseOk();

            self::getClient()->request('GET', $this->urlBase . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testPageResponseNotFound(): void
    {
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
        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
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
            if ($course->getId() === (int)$id) {
                $crawler = $node;
            }
        }

        // переход на страницу курса(/courses/{id})
        $linkCourse = $crawler->selectLink('Пройти')->link();
        $crawler = self::getClient()->click($linkCourse);
        self::assertEquals(
            $this->urlBase . '/'.$course->getId(),
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // переход на страницу редактирования курса(/courses/{id}/edit)
        $linkEditCourse = $crawler->selectLink('Редактировать курс')->link();
        $crawler = self::getClient()->click($linkEditCourse);
        self::assertEquals(
            $this->urlBase . '/'.$course->getId() . '/edit',
            self::getClient()->getRequest()->getPathInfo()
        );
        $this->assertResponseOk();

        // проверка что данные в форме верно взяты(отображаются в html) из базы данных
        $form = $crawler->selectButton('Обновить')->form();
        self::assertEquals($course->getCode(), $form['course[code]']->getValue());
        self::assertEquals($course->getName(), $form['course[name]']->getValue());
        self::assertEquals($course->getDescription(), $form['course[description]']->getValue());

        // проверка формы на валидность данные
        // последний элемент массива с верными данными
        $checkData = [
            [
                'code' =>
                    [
                        'text' => '',
                        'request' => $this->errors['code']['empty'],
                    ],
                'name' =>
                    [
                        'text' => '',
                        'request' => $this->errors['name']['empty'],
                    ],
                'description' =>
                    [
                        'text' => str_repeat('1', 1001),
                        'request' => $this->errors['description']['longLength'],
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => 'C202103011957AG',
                        'request' => $this->errors['code']['uniqueFalse'],
                    ],
                'name' =>
                    [
                        'text' => str_repeat('1', 256),
                        'request' => $this->errors['name']['longLength'],
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => str_repeat('1', 256),
                        'request' => $this->errors['code']['longLength'],
                    ],
                'name' =>
                    [
                        'text' => 'TestNameCourse',
                        'request' => 0,
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => 'TestCodeCourse',
                        'request' => 0,
                    ],
                'name' =>
                    [
                        'text' => 'TestNameCourse',
                        'request' => 0,
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
        ];
        foreach ($checkData as $i => $iValue) {
            $crawler = $this->checkForm($form, $iValue);

            $errorCrawler = $crawler->filter("label[for='course_code'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['code']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['code']['request'], 0);
            }

            $errorCrawler = $crawler->filter("label[for='course_name'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['name']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['name']['request'], 0);
            }

            $errorCrawler = $crawler->filter("label[for='course_description'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['description']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['description']['request'], 0);
            }
            if ($i + 1 != count($checkData)) {
                self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
            } else {
                $this->assertResponseRedirect();
            }
        }

        // редирект на /courses/{id}
        $crawler = self::getClient()->followRedirect();
        self::assertEquals(
            $this->urlBase . '/'.$course->getId(),
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
        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем просмотреть любой из курсов
        for ($i = 0; $i < $countCourses; $i++) {
            $crawler = $crawler->filter('.course')->eq($i);
            $nameCourse = $crawler->filter('h3')->text();
            $descriptionCourse = $crawler->filter('.course-description')->text();
            $hrefLink = $crawler->selectLink('Пройти')->attr('href');
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
            $linkCourse = $crawler->selectLink('Пройти')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlBase . '/'.$course->getId(),
                self::getClient()->getRequest()->getPathInfo()
            );
            $this->assertResponseOk();
            ;

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
        // проверка перехода на страницу курсов (/courses)
        $crawler = self::getClient()->request('GET', $this->urlBase . '/');
        $this->assertResponseOk();

        // получение количества курсов
        $countCourses = $crawler->filter('.course')->count();

        // проверка что можем удалить любой из курсов
        for ($i = 0; $i < $countCourses; $i++) {
            $crawler = $crawler->filter('.course')->eq(0);
            $hrefLink = $crawler->selectLink('Пройти')->attr('href');
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
            $linkCourse = $crawler->selectLink('Пройти')->link();
            $crawler = self::getClient()->click($linkCourse);

            // проверка что перешли на верный курс (/courses/{id})
            self::assertEquals(
                $this->urlBase . '/'.$course->getId(),
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
                $hrefLink = $node->selectLink('Пройти')->attr('href');
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
        // последний элемент массива с верными данными
        $checkData = [
            [
                'code' =>
                    [
                        'text' => '',
                        'request' => $this->errors['code']['empty'],
                    ],
                'name' =>
                    [
                        'text' => '',
                        'request' => $this->errors['name']['empty'],
                    ],
                'description' =>
                    [
                        'text' => str_repeat('1', 1001),
                        'request' => $this->errors['description']['longLength'],
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => 'C202103011957AG',
                        'request' => $this->errors['code']['uniqueFalse'],
                    ],
                'name' =>
                    [
                        'text' => str_repeat('1', 256),
                        'request' => $this->errors['name']['longLength'],
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => str_repeat('1', 256),
                        'request' => $this->errors['code']['longLength'],
                    ],
                'name' =>
                    [
                        'text' => 'TestNameCourse',
                        'request' => 0,
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
            [
                'code' =>
                    [
                        'text' => 'TestCodeCourse',
                        'request' => 0,
                    ],
                'name' =>
                    [
                        'text' => 'TestNameCourse',
                        'request' => 0,
                    ],
                'description' =>
                    [
                        'text' => 'TestDescriptionCourse',
                        'request' => 0,
                    ],
            ],
        ];
        $form = $crawler->selectButton('Сохранить')->form();
        foreach ($checkData as $i => $iValue) {
            $crawler = $this->checkForm($form, $iValue);

            $errorCrawler = $crawler->filter("label[for='course_code'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['code']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['code']['request'], 0);
            }

            $errorCrawler = $crawler->filter("label[for='course_name'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['name']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['name']['request'], 0);
            }

            $errorCrawler = $crawler->filter("label[for='course_description'] .form-error-message");
            if ($errorCrawler->count() > 0) {
                self::assertEquals($iValue['description']['request'], $errorCrawler->text());
            } else {
                self::assertEquals($iValue['description']['request'], 0);
            }
            if ($i + 1 != count($checkData)) {
                self::assertNotInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
            } else {
                $this->assertResponseRedirect();
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
        $form['course[name]'] = $data['name']['text'];
        $form['course[description]'] = $data['description']['text'];
        return self::getClient()->submit($form);
    }

    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }
}
