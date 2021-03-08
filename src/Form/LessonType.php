<?php

namespace App\Form;

use App\Entity\Lesson;
use App\Form\DataTransformer\CourseToNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LessonType extends AbstractType
{
    private $transformer;

    public function __construct(CourseToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'empty_data' => '',
                    'required' => false,
                    'constraints' => [
                        new NotBlank(['message' => 'Заполните название']),
                        new Length(
                            [
                                'max' => 255,
                                'maxMessage' => 'Длина должна быть не более  {{ limit }} символов',
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'content',
                TextareaType::class,
                [
                    'required' => false,
                    'constraints' => [new NotBlank(['message' => 'Заполните содержимое урока'])],
                ]
            )
            ->add(
                'number',
                NumberType::class,
                [
                    'empty_data' => '',
                    'required' => false,
                    'constraints' => [
                        new NotBlank(['message' => 'Заполните номер урока']),
                    ],
                ]
            )
            ->add(
                'course',
                HiddenType::class,
                ['attr' => ['value' => $options['course_id']]]
            );

        $builder->get('course')
            ->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => Lesson::class,
                'course_id' => '',
            ]
        );
        $resolver->setAllowedTypes('course_id', 'string');
    }
}
