<?php

namespace App\Form\DataTransformer;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CourseToNumberTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Transforms an object (Course) to a string (number).
     *
     * @param Course|null course
     */
    public function transform($course): string
    {
        if (null === $course) {
            return '';
        }

        return $course->getId();
    }

    /**
     * Transforms a string (number) to an object (Course).
     *
     * @param string $courseNumber
     *
     * @throws TransformationFailedException if object (Course) is not found
     */
    public function reverseTransform($courseNumber): ?Course
    {
        if (false === $courseNumber) {
            return null;
        }

        $course = $this->entityManager
            ->getRepository(Course::class)
            ->find($courseNumber);

        if (null === $course) {
            throw new TransformationFailedException(sprintf('An course with number "%s" does not exist!', $courseNumber));
        }

        return $course;
    }
}
