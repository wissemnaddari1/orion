<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class BanUserType extends AbstractType
{
    public const PRESET_2H = '2h';
    public const PRESET_1D = '1d';
    public const PRESET_2D = '2d';
    public const PRESET_3D = '3d';
    public const PRESET_7D = '7d';
    public const PRESET_30D = '30d';
    public const PRESET_PERM = 'perm';
    public const PRESET_CUSTOM = 'custom';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', TextareaType::class, [
                'label' => 'Reason (required)',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'rows' => 3,
                    'placeholder' => 'e.g. Terms of service violation',
                ],
                'constraints' => [new NotBlank(['message' => 'Ban reason is required.'])],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Internal note (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'rows' => 2,
                    'placeholder' => 'Admin-only note, not shown to user',
                ],
            ])
            ->add('duration_preset', ChoiceType::class, [
                'label' => 'Duration',
                'choices' => [
                    '2 hours' => self::PRESET_2H,
                    '1 day' => self::PRESET_1D,
                    '2 days' => self::PRESET_2D,
                    '3 days' => self::PRESET_3D,
                    '7 days' => self::PRESET_7D,
                    '30 days' => self::PRESET_30D,
                    'Permanent' => self::PRESET_PERM,
                    'Custom' => self::PRESET_CUSTOM,
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'id' => 'ban_duration_preset',
                ],
            ])
            ->add('custom_value', IntegerType::class, [
                'label' => 'Custom duration (number)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                    'min' => 1,
                    'placeholder' => 'e.g. 5',
                ],
                'constraints' => [new GreaterThan(0)],
            ])
            ->add('custom_unit', ChoiceType::class, [
                'label' => 'Unit',
                'required' => false,
                'choices' => [
                    'Hours' => 'hours',
                    'Days' => 'days',
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'space-y-4'],
        ]);
    }
}
