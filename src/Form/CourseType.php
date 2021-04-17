<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
                    'empty_data' => '',
                    'required' => false,
                    'constraints' => [
                        new NotBlank(['message' => 'Заполните код']),
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
                'price',
                NumberType::class,
                [
                    'attr' => ['value' => $options['price']],
                    'mapped' => false,
                    'empty_data' => '',
                    'required' => false,
                    'constraints' => [new NotBlank(['message' => 'Заполните цену'])],
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'data' => $options['type'],
                    'mapped' => false,
                    'choices' => [
                        'Бесплатный' => 'free',
                        'Покупка' => 'buy',
                        'Аренда' => 'rent',
                    ],
                ]
            )
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
                'description',
                TextareaType::class,
                [
                    'empty_data' => '',
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
                'price' => 0.0,
                'type' => 'free',
            ]
        );
        $resolver->setAllowedTypes('price', 'float');
        $resolver->setAllowedTypes('type', 'string');
    }
}
