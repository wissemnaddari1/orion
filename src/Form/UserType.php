<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Enum\WalletCurrency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter username'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Username is required']),
                    new Length(['min' => 3, 'max' => 50])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter email'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Length(['max' => 180])
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter first name'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'First name is required'])
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter last name'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required'])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => '+21612345678'
                ]
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Super Admin' => UserRole::SUPER_ADMIN->value,
                    'Admin' => UserRole::ADMIN->value,
                    'Client' => UserRole::CLIENT->value,
                    'Worker' => UserRole::WORKER->value,
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white'
                ],
                'placeholder' => 'Select a role',
                'getter' => function (User $user): string {
                    return $user->getRole()->value;
                },
                'setter' => function (User &$user, ?string $role): void {
                    $user->setRole($role);
                }
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => UserStatus::ACTIVE->value,
                    'Suspended' => UserStatus::SUSPENDED->value,
                    'Pending' => UserStatus::PENDING->value,
                    'Banned' => UserStatus::BANNED->value,
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white'
                ],
                'placeholder' => 'Select a status',
                'getter' => function (User $user): string {
                    return $user->getStatus()->value;
                },
                'setter' => function (User &$user, ?string $status): void {
                    $user->setStatus($status);
                }
            ])
            ->add('country', TextType::class, [
                'label' => 'Country',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter country'
                ]
            ])
            ->add('city', TextType::class, [
                'label' => 'City',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => 'Enter city'
                ]
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'Timezone',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white'
                ],
                'placeholder' => 'Select a timezone'
            ])
            ->add('accountBalance', NumberType::class, [
                'label' => 'Account Balance',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0'
                ]
            ])
            ->add('walletCurrency', ChoiceType::class, [
                'label' => 'Wallet Currency',
                'choices' => WalletCurrency::cases(),
                'choice_value' => 'value',
                'choice_label' => 'value',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white'
                ],
                'placeholder' => 'Select currency'
            ])
            ->add('emailVerified', CheckboxType::class, [
                'label' => 'Email Verified',
                'required' => false,
                'attr' => [
                    'class' => 'w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:border-slate-700'
                ]
            ])
            ->add('phoneVerified', CheckboxType::class, [
                'label' => 'Phone Verified',
                'required' => false,
                'attr' => [
                    'class' => 'w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:border-slate-700'
                ]
            ])
            ->add('twoFactorEnabled', CheckboxType::class, [
                'label' => 'Two Factor Enabled',
                'required' => false,
                'attr' => [
                    'class' => 'w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:border-slate-700'
                ]
            ])
            ->add('profilePictureFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'w-full text-sm text-slate-900 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none dark:text-slate-400 dark:bg-slate-900 dark:border-slate-700',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    // File (not Image) avoids MIME guessing; Image defaults mimeTypes to image/* and requires fileinfo
                    new File(['maxSize' => '5M'])
                ]
            ]);

        // Password field (required on create, optional on edit)
        if (!$isEdit) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => true,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                        'placeholder' => 'Enter password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                        'placeholder' => 'Repeat password'
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Password is required']),
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least {{ limit }} characters'])
                ]
            ]);
        } else {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Password (leave blank to keep current)',
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                        'placeholder' => 'Enter new password or leave blank'
                    ],
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                        'placeholder' => 'Repeat new password'
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least {{ limit }} characters'])
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
