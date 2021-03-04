<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'code',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(['message' => 'Заполните code']),
                        new Length(
                            [
                                'min' => 1,
                                'max' => 255,
                                'minMessage' => 'Длина должна быть не менее  {{ limit }} символов',
                                'maxMessage' => 'Длина должна быть не более  {{ limit }} символов',
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'name',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(['message' => 'Заполните название']),
                        new Length(
                            [
                                'min' => 1,
                                'max' => 255,
                                'minMessage' => 'Длина должна быть не менее  {{ limit }} символов',
                                'maxMessage' => 'Длина должна быть не более  {{ limit }} символов',
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'description',
                TextareaType::class,
                [
                    'required' => false,
                    'constraints' => [
                        new Length(
                            [
                                'max' => 1000,
                                'maxMessage' => 'Длина должна быть не более  {{ limit }} символов',
                            ]
                        ),
                    ],
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => Course::class,
            ]
        );
    }
}
