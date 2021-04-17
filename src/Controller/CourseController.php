<?php

namespace App\Controller;

use App\DTO\Course as CourseDto;
use App\DTO\Pay as PayDto;
use App\DTO\Transaction as TransactionDto;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use DateInterval;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    private $billingClient;

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }

    /**
     * @Route("/", name="course_index", methods={"GET"})
     */
    public function index(CourseRepository $courseRepository): Response
    {
        try {
            /** @var CourseDto[] $coursesDto */
            $coursesDto = $this->billingClient->listCourses();
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = [];

            // если пользователь авторизирован, то делаем запрос по транзакциям
            if ($this->getUser()) {
                $queryFilter = 'type=payment&skip_expired=1';
                $transactionsDto = $this->billingClient->transactionHistory($queryFilter);
            }

            /** @var Course[] $courses */
            $courses = $courseRepository->findBy([], ['id' => 'ASC']);

            $infoPrices = []; // информация по ценам
            foreach ($coursesDto as $courseDto) {
                $key = $courseDto->getCode();
                $infoPrices[$key] = $courseDto;
            }

            $infoPurchases = []; // информация о покупе(если была совершена)
            foreach ($transactionsDto as &$transactionDto) {
                // добавляем к времени 1 неделю(аренда курса)
                $dateTime = $transactionDto->getCreatedAt();
                if ($dateTime) {
                    $newDateTime = (new DateTime($dateTime))->add(new DateInterval('P1W')); //  +1 неделя
                    $transactionDto->setCreatedAt($newDateTime->format('Y-m-d T H:i:s'));
                }
                $key = $transactionDto->getCourseCode();
                $infoPurchases[$key] = $transactionDto;
            }
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render(
            'course/index.html.twig',
            [
                'courses' => $courses,
                'infoPrices' => $infoPrices,
                'infoPurchases' => $infoPurchases,
            ]
        );
    }

    /**
     * @Route("/pay", name="course_pay", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function pay(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        $courseCode = $request->get('course_code');
        try {
            /** @var PayDto $payDto */
            $payDto = $this->billingClient->payCourse($courseCode);
            $this->addFlash('success', 'Успешное выполнение операции!');
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($referer);
    }

    /**
     * @Route("/new", name="course_new", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();
                $courseDto->setCode($form->get('code')->getData());
                $typeCourse = $form->get('type')->getData();
                $courseDto->setType($typeCourse);

                if ('free' === $typeCourse) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }
                $courseDto->setTitle($form->get('name')->getData());

                // запрос к билинг сервису
                $responseDto = $this->billingClient->newCourses($courseDto);

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($course);
                $entityManager->flush();
            } catch (FailureResponseException $e) {
                return $this->render('course/new.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            } catch (BillingUnavailableException $e) {
                return $this->render('course/new.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }

            return $this->redirectToRoute('course_index');
        }

        return $this->render(
            'course/new.html.twig',
            [
                'course' => $course,
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/{id}", name="course_show", methods={"GET"})
     */
    public function show(Course $course): Response
    {
        try {
            $codeCourse = $course->getCode();
            /** @var CourseDto $courseDto */
            $courseDto = $this->billingClient->oneCourse($codeCourse);

            $queryFilter = "type=payment&course_code=$codeCourse&skip_expired=1";
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = $this->billingClient->transactionHistory($queryFilter);

            $infoPrices[$codeCourse] = $courseDto; // информация по ценам

            // добавляем к времени 1 неделю(аренда курса)
            if (0 < count($transactionsDto)) {
                $dateTime = $transactionsDto[0]->getCreatedAt();
                if ($dateTime) {
                    $newDateTime = (new DateTime($dateTime))->add(new DateInterval('P1W')); //  +1 неделя
                    $transactionsDto[0]->setCreatedAt($newDateTime->format('Y-m-d T H:i:s'));
                }
                $key = $transactionsDto[0]->getCourseCode();
                $infoPurchases[$key] = $transactionsDto[0]; // информаация о покупке
            }
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render(
            'course/show.html.twig',
            [
                'infoPrices' => $infoPrices,
                'infoPurchases' => $infoPurchases ?? [],
                'course' => $course,
                'lessons' => $this->getDoctrine()
                    ->getRepository(Lesson::class)
                    ->findBy(
                        ['course' => $course],
                        ['number' => 'ASC']
                    ),
            ]
        );
    }

    /**
     * @Route("/{id}/edit", name="course_edit", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Course $course): Response
    {
        $codeCourse = $course->getCode();
        try {
            // данные с билинга по курсу
            /** @var CourseDto $courseDto */
            $courseDto = $this->billingClient->oneCourse($codeCourse);
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        $form = $this->createForm(
            CourseType::class,
            $course,
            [
                'price' => $courseDto->getPrice(),
                'type' => $courseDto->getType(),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();
                $courseDto->setCode($form->get('code')->getData());
                $typeCourse = $form->get('type')->getData();
                $courseDto->setType($typeCourse);

                if ('free' === $typeCourse) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }
                $courseDto->setTitle($form->get('name')->getData());

                // запрос к билинг сервису
                $responseDto = $this->billingClient->editCourses($courseDto);

                $this->getDoctrine()->getManager()->flush();
            } catch (FailureResponseException $e) {
                return $this->render('course/edit.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            } catch (BillingUnavailableException $e) {
                return $this->render('course/edit.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }

            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
        }

        return $this->render(
            'course/edit.html.twig',
            [
                'course' => $course,
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/{id}", name="course_delete", methods={"DELETE"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Course $course): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('course_index');
    }
}
