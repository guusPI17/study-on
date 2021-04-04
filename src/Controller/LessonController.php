<?php

namespace App\Controller;

use App\DTO\Course as CourseDto;
use App\DTO\Transaction as TransactionDto;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Form\LessonType;
use App\Service\BillingClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/lessons")
 */
class LessonController extends AbstractController
{
    private $billingClient;

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }

    /**
     * @Route("/new", name="lesson_new", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request): Response
    {
        $courseId = $request->get('course_id');
        $course = $this->getDoctrine()->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException('Курс с id ' . $courseId . ' не найден');
        }

        $lesson = new Lesson();
        $form = $this->createForm(
            LessonType::class,
            $lesson,
            ['course_id' => $courseId]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute(
                'course_show',
                ['id' => $lesson->getCourse()->getId()]
            );
        }

        return $this->render(
            'lesson/new.html.twig',
            [
                'lesson' => $lesson,
                'form' => $form->createView(),
                'course' => $this->getDoctrine()
                    ->getRepository(Course::class)
                    ->find($request->query->get('course_id')),
            ]
        );
    }

    /**
     * @Route("/{id}", name="lesson_show", methods={"GET"})
     */
    public function show(Lesson $lesson): Response
    {
        try {
            $codeCourse = $lesson->getCourse()->getCode();
            /** @var CourseDto $courseDto */
            $courseDto = $this->billingClient->oneCourse($codeCourse);
            if ('free' !== $courseDto->getType()) {
                $queryFilter = "type=payment&course_code=$codeCourse&skip_expired=1";
                /** @var TransactionDto[] $transactionsDto */
                $transactionsDto = $this->billingClient->transactionHistory($queryFilter);

                if (0 == count($transactionsDto)) {
                    throw new AccessDeniedException('Данный урок вам не доступен!');
                }
            }
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render(
            'lesson/show.html.twig',
            ['lesson' => $lesson]
        );
    }

    /**
     * @Route("/{id}/edit", name="lesson_edit", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Lesson $lesson): Response
    {
        $form = $this->createForm(
            LessonType::class,
            $lesson,
            ['course_id' => (string) $lesson->getCourse()->getId()]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
        }

        return $this->render(
            'lesson/edit.html.twig',
            [
                'lesson' => $lesson,
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/{id}", name="lesson_delete", methods={"DELETE"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Lesson $lesson): Response
    {
        if ($this->isCsrfTokenValid('delete' . $lesson->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($lesson);
            $entityManager->flush();
        }

        return $this->redirectToRoute(
            'course_show',
            ['id' => $lesson->getCourse()->getId()]
        );
    }
}
