<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white';

        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Choose a username',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a username.']),
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Username must be at least {{ limit }} characters.',
                        'maxMessage' => 'Username cannot exceed {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z0-9._-]+$/',
                        'message' => 'Username can only contain letters, numbers, dots, underscores, and dashes.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Enter your email',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your email address.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Enter your first name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your first name.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Enter your last name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your last name.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'class' => $inputClass,
                        'placeholder' => 'Create a password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => [
                        'class' => $inputClass,
                        'placeholder' => 'Confirm your password',
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters.',
                        'max' => 128,
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'I agree to the Terms of Service and Privacy Policy',
                'constraints' => [
                    new IsTrue(['message' => 'You must agree to the terms and conditions.']),
                ],
                'attr' => [
                    'class' => 'w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
