<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for submitting satisfaction rating after ticket closure
 * Server-side validation for rating and comment
 */
class SatisfactionRatingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('satisfactionRating', ChoiceType::class, [
                'label' => 'How satisfied are you with the support?',
                'choices' => [
                    '⭐ 1 - Very Unsatisfied' => 1,
                    '⭐⭐ 2 - Unsatisfied' => 2,
                    '⭐⭐⭐ 3 - Neutral' => 3,
                    '⭐⭐⭐⭐ 4 - Satisfied' => 4,
                    '⭐⭐⭐⭐⭐ 5 - Very Satisfied' => 5
                ],
                'placeholder' => 'Select a rating',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a rating.'
                    ]),
                    new Assert\Choice([
                        'choices' => [1, 2, 3, 4, 5],
                        'message' => 'Invalid rating value.'
                    ]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 5,
                        'notInRangeMessage' => 'Rating must be between {{ min }} and {{ max }}.'
                    ])
                ]
            ])
            ->add('satisfactionComment', TextareaType::class, [
                'label' => 'Additional Comments (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Tell us more about your experience...',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Comment cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'satisfaction_rating',
        ]);
    }
}
