<?php

namespace App\Form;

use App\Entity\Offer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[#000000] focus:border-[#0FAF7A] focus:ring-2 focus:ring-[#0FAF7A]/20 dark:bg-slate-800 dark:border-slate-700 dark:text-white';

        $builder
            ->add('price', TextType::class, [
                'label' => 'Price (USD)',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => '2400.00',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Price is required.']),
                    new Assert\Regex([
                        'pattern' => '/^\d+(\.\d{1,2})?$/',
                        'message' => 'Price must be a valid number (e.g. 2400 or 2400.50).',
                    ]),
                    new Assert\GreaterThan([
                        'value' => 0,
                        'message' => 'Price must be greater than 0.',
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 999999.99,
                        'message' => 'Price cannot exceed $999,999.99.',
                    ]),
                ],
            ])
            ->add('estimatedTimeDays', IntegerType::class, [
                'label' => 'Estimated Time (days)',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'e.g. 28',
                    'min' => 1,
                    'max' => 365,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Estimated time is required.']),
                    new Assert\Positive(['message' => 'Must be at least 1 day.']),
                    new Assert\LessThanOrEqual([
                        'value' => 365,
                        'message' => 'Cannot exceed 365 days.',
                    ]),
                ],
            ])
            ->add('includedRevisions', IntegerType::class, [
                'label' => 'Revisions included',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'min' => 0,
                    'max' => 50,
                    'placeholder' => '0',
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Revisions cannot be negative.']),
                    new Assert\LessThanOrEqual([
                        'value' => 50,
                        'message' => 'Cannot exceed 50 revisions.',
                    ]),
                ],
            ])
            ->add('responseSlaHours', IntegerType::class, [
                'label' => 'Response SLA (hours)',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'e.g. 24',
                    'min' => 1,
                    'max' => 720,
                ],
                'constraints' => [
                    new Assert\Positive(['message' => 'SLA hours must be at least 1.']),
                    new Assert\LessThanOrEqual([
                        'value' => 720,
                        'message' => 'SLA cannot exceed 720 hours (30 days).',
                    ]),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message to client',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'rows' => 4,
                    'placeholder' => 'Brief pitch and approach',
                    'maxlength' => 2000,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'Message cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('scopeSummary', TextareaType::class, [
                'label' => 'Scope Summary',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'rows' => 3,
                    'maxlength' => 3000,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 3000,
                        'maxMessage' => 'Scope summary cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('deliverables', TextareaType::class, [
                'label' => 'Deliverables',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'rows' => 3,
                    'maxlength' => 3000,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 3000,
                        'maxMessage' => 'Deliverables cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('acceptanceCriteria', TextareaType::class, [
                'label' => 'Acceptance Criteria',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'rows' => 3,
                    'maxlength' => 3000,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 3000,
                        'maxMessage' => 'Acceptance criteria cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('extraRevisionFee', TextType::class, [
                'label' => 'Extra Revision Fee (USD)',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => '50.00',
                ],
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^\d+(\.\d{1,2})?$/',
                        'message' => 'Must be a valid amount (e.g. 50 or 50.00).',
                    ]),
                    new Assert\PositiveOrZero(['message' => 'Fee cannot be negative.']),
                    new Assert\LessThanOrEqual([
                        'value' => 99999.99,
                        'message' => 'Fee cannot exceed $99,999.99.',
                    ]),
                ],
            ])
            ->add('priorityLevel', ChoiceType::class, [
                'label' => 'Priority Level',
                'choices' => [
                    'Low' => 'LOW',
                    'Medium' => 'MEDIUM',
                    'High' => 'HIGH',
                ],
                'attr' => [
                    'class' => $inputClass,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Priority level is required.']),
                    new Assert\Choice([
                        'choices' => ['LOW', 'MEDIUM', 'HIGH'],
                        'message' => 'Invalid priority level.',
                    ]),
                ],
            ])
            ->add('isUrgent', CheckboxType::class, [
                'label' => 'Urgent',
                'required' => false,
            ])
            ->add('rushFee', TextType::class, [
                'label' => 'Rush Fee (USD)',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => '200.00',
                ],
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^\d+(\.\d{1,2})?$/',
                        'message' => 'Must be a valid amount (e.g. 200 or 200.00).',
                    ]),
                    new Assert\PositiveOrZero(['message' => 'Rush fee cannot be negative.']),
                    new Assert\LessThanOrEqual([
                        'value' => 99999.99,
                        'message' => 'Rush fee cannot exceed $99,999.99.',
                    ]),
                ],
            ])
            ->add('startDateAvailable', DateType::class, [
                'label' => 'Start Date Available',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => $inputClass,
                ],
                'constraints' => [
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'Start date cannot be in the past.',
                        'groups' => ['create'],
                    ]),
                ],
            ])
            ->add('deliveryDateEstimated', DateType::class, [
                'label' => 'Estimated Delivery Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => $inputClass,
                ],
                'constraints' => [
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'Delivery date cannot be in the past.',
                        'groups' => ['create'],
                    ]),
                ],
            ])
        ;

        // Only show status field when editing
        if ($options['is_edit']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'PENDING',
                    'Accepted' => 'ACCEPTED',
                    'Rejected' => 'REJECTED',
                    'Expired' => 'EXPIRED',
                ],
                'attr' => [
                    'class' => $inputClass,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Status is required.']),
                    new Assert\Choice([
                        'choices' => ['PENDING', 'ACCEPTED', 'REJECTED', 'EXPIRED'],
                        'message' => 'Invalid status.',
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offer::class,
            'is_edit' => false,
        ]);
    }
}
