<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $data = ['courses' => [
                [
                    'name' => 'Deep Learning (семестр 1, весна 2021): базовый поток',
                    'descriptions' => 'Школа глубокого обучения (Deep Learning School) – учебная организация' .
                        ' на базе Физтех-школы, прикладной математики и информатики Московского' .
                        ' физико-технического института.',
                    'code' => 'C202103011957AG',
                    'lessons' => [
                        [
                            'name' => 'Введение',
                            'content' => 'Глубокое обучение (глубинное обучение; англ. Deep learning) —' .
                                ' совокупность методов машинного обучения',
                            'number' => 1,
                        ],
                        [
                            'name' => 'Что такое Deep Learning?',
                            'content' => 'Несмотря на то что термин «глубокое обучение» появился в научном' .
                                ' сообществе машинного обучения только в q1986 году после работы Рины Дехтер[4]',
                            'number' => 2,
                        ],
                    ],
                ],
                [
                    'name' => 'Принципы дизайна исследований и статистики в медицине',
                    'descriptions' => 'Статистика и исследования – это не скучно, а про кото-вероятности и' .
                        ' шансы убежать от зомби!',
                    'code' => 'C202103011958AG',
                    'lessons' => [
                        [
                            'name' => 'Введение в статистику',
                            'content' => 'Стати́стика — отрасль знаний, наука, в которой излагаются общие' .
                                ' вопросы сбора',
                            'number' => 1,
                        ],
                        [
                            'name' => 'Математические анализ',
                            'content' => 'Золотое сечение (золотая пропорция, деление в крайнем и' .
                                ' среднем отношении, гармоническое деление) — соотношение двух величин',
                            'number' => 2,
                        ],
                    ],
                ],
                [
                    'name' => 'C# для продвинутых',
                    'descriptions' => 'Если знаешь основы программирования и изучаешь самостоятельно язык' .
                        ' программирования C#' .
                        'Если готовишься к собеседованиям на роль C# программиста',
                    'code' => 'C202103012023AG',
                    'lessons' => [
                        [
                            'name' => 'Windows Forms - основы',
                            'content' => 'Windows Forms — интерфейс программирования приложений (API),' .
                                ' отвечающий за графический интерфейс пользователя и являющийся частью' .
                                ' Microsoft .NET Framework.',
                            'number' => 1,
                        ],
                        [
                            'name' => 'WPF - основы',
                            'content' => 'Windows Presentation Foundation (WPF) — аналог WinForms, система для' .
                                ' построения клиентских приложений Windows с визуально привлекательными' .
                                ' возможностями взаимодействия с пользователем, графическая (презентационная)' .
                                ' подсистема в составе .NET Framework (начиная с версии 3.0), использующая' .
                                ' язык XAML[1].',
                            'number' => 2,
                        ],
                    ],
                ],
            ]];

        foreach ($data['courses'] as $dataCourse) {
            $course = new Course();
            $course->setCode($dataCourse['code']);
            $course->setName($dataCourse['name']);
            $course->setDescription($dataCourse['descriptions']);
            $manager->persist($course);
            foreach ($dataCourse['lessons'] as $dataLesson) {
                $lesson = $this->createLesson(
                    $course,
                    $dataLesson['number'],
                    $dataLesson['name'],
                    $dataLesson['content']
                );
                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }

    /**
     * Создание урока.
     */
    private function createLesson(Course $course, int $number, string $name, string $content): Lesson
    {
        $lesson = new Lesson();
        $lesson->setCourse($course);
        $lesson->setNumber($number);
        $lesson->setName($name);
        $lesson->setContent($content);

        return $lesson;
    }
}
