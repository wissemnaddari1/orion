<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white';

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'New password',
                    'attr' => [
                        'class' => $inputClass,
                        'placeholder' => 'Enter new password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm new password',
                    'attr' => [
                        'class' => $inputClass,
                        'placeholder' => 'Confirm new password',
                        'autocomplete' => 'new-password',
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
                    new Regex([
                        'pattern' => '/^[A-Z]/',
                        'message' => 'Password must start with an uppercase letter.',
                    ]),
                    new Regex([
                        'pattern' => '/[A-Z]/',
                        'message' => 'Password must contain at least one uppercase letter.',
                    ]),
                    new Regex([
                        'pattern' => '/[a-z]/',
                        'message' => 'Password must contain at least one lowercase letter.',
                    ]),
                    new Regex([
                        'pattern' => '/\d/',
                        'message' => 'Password must contain at least one digit.',
                    ]),
                    new Regex([
                        'pattern' => '/[^A-Za-z0-9]/',
                        'message' => 'Password must contain at least one special character.',
                    ]),
                ],
            ]);
    }
}
