<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\LessonType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/lessons")
 */
class LessonController extends AbstractController
{
    /**
     * @Route("/new", name="lesson_new", methods={"GET","POST"})
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
        return $this->render(
            'lesson/show.html.twig',
            ['lesson' => $lesson]
        );
    }

    /**
     * @Route("/{id}/edit", name="lesson_edit", methods={"GET","POST"})
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
