<?php

namespace App\Form;

use App\Entity\WorkerCategory;
use App\Entity\WorkerProfile;
use App\Repository\WorkerCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class WorkerProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'mt-1 block w-full rounded-xl border-gray-200 bg-gray-50/50 shadow-sm focus:border-[#0FAF7A] focus:ring-[#0FAF7A] focus:ring-opacity-50 transition-all duration-200 sm:text-sm py-3 px-4';
        $labelClass = 'block text-sm font-bold text-gray-700 mb-1';

        $builder
            ->add('title', TextType::class, [
                'label' => 'Professional Title',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => 'e.g. Senior Electrician | Certified Plumber | UX Designer'],
                'constraints' => [
                    new NotBlank(['message' => 'Required.']),
                    new Length(['min' => 3]),
                ],
            ])
            ->add('workerCategory', EntityType::class, [
                'class' => WorkerCategory::class,
                'choice_label' => 'name',
                'label' => 'Specialty',
                'label_attr' => ['class' => $labelClass],
                'placeholder' => 'Select your field',
                'attr' => ['class' => $inputClass],
                'query_builder' => fn (WorkerCategoryRepository $repo) => $repo->createQueryBuilder('c')
                    ->andWhere('c.status = :status')
                    ->setParameter('status', 'active')
                    ->orderBy('c.display_order', 'ASC')
                    ->addOrderBy('c.name', 'ASC'),
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'About You',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'rows' => 5, 'placeholder' => 'Share your experience and skills...'],
                'constraints' => [
                    new NotBlank(['message' => 'Required.']),
                    new Length(['min' => 10]),
                ],
            ])
            ->add('hourly_rate', MoneyType::class, [
                'label' => 'Hourly Rate',
                'currency' => false,
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => '0.00'],
                'constraints' => [
                    new NotBlank(['message' => 'Required.']),
                    new PositiveOrZero(),
                ],
            ])
            ->add('experience_years', IntegerType::class, [
                'label' => 'Years of Experience',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass],
                'constraints' => [
                    new NotBlank(['message' => 'Required.']),
                    new PositiveOrZero(),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location (City)',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => 'e.g. Paris, London...'],
                'constraints' => [new NotBlank(['message' => 'Required.'])],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => '+33 6 12 34 56 78'],
                'constraints' => [
                    new Length(['max' => 20]),
                    new Regex([
                        'pattern' => '/^[0-9+()\-\s.]{6,20}$/',
                        'message' => 'Please enter a valid phone number.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Professional Email',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => 'name@example.com'],
            ])
            ->add('availability_status', ChoiceType::class, [
                'label' => 'Current Status',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'choices' => [
                    '🟢 Available' => 'available',
                    '🟡 Busy' => 'busy',
                    '🔴 Unavailable' => 'unavailable',
                ],
                'attr' => ['class' => $inputClass],
                'constraints' => [
                    new NotBlank(['message' => 'Required.']),
                ],
            ])
            ->add('verified', CheckboxType::class, [
                'label' => 'Mark as verified',
                'label_attr' => ['class' => 'font-medium text-gray-700 select-none cursor-pointer'],
                'required' => false,
                'attr' => ['class' => 'h-5 w-5 text-[#0FAF7A] focus:ring-[#0FAF7A] border-gray-300 rounded cursor-pointer transition-all'],
                'row_attr' => ['class' => 'flex items-center space-x-3 bg-gray-50 p-4 rounded-xl border border-gray-100'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Profile',
                'attr' => ['class' => 'w-full flex justify-center py-3.5 px-6 border border-transparent rounded-xl shadow-lg shadow-[#0FAF7A]/25 text-sm font-bold text-white bg-[#0FAF7A] hover:bg-[#0c8f63] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0FAF7A] transition-all duration-300 transform hover:-translate-y-0.5 text-base'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkerProfile::class,
            'attr' => ['data-ajax-form' => 'true', 'class' => 'space-y-6'],
        ]);
    }
}
