<?php

namespace App\Form;

use App\Entity\WorkerCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class WorkerCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'mt-1 block w-full rounded-xl border-gray-200 bg-gray-50/50 shadow-sm focus:border-[#0FAF7A] focus:ring-[#0FAF7A] focus:ring-opacity-50 transition-all duration-200 sm:text-sm py-3 px-4';
        $labelClass = 'block text-sm font-semibold text-gray-700 mb-1';

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => 'Ex: Plomberie, Jardinage...'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis.']),
                    new Length(['min' => 3, 'minMessage' => 'Min 3 caractères.', 'max' => 255]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'rows' => 4, 'placeholder' => 'Décrivez les services inclus dans cette catégorie...'],
                'constraints' => [
                    new NotBlank(['message' => 'La description est requise.']),
                    new Length(['min' => 10, 'minMessage' => 'Min 10 caractères.']),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'choices' => [
                    'Actif' => 'active',
                    'Inactif' => 'inactive',
                ],
                'attr' => ['class' => $inputClass],
                'constraints' => [
                    new NotBlank(['message' => 'Le statut est requis.']),
                ],
            ])
            ->add('display_order', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass],
                'constraints' => [
                    new NotBlank(['message' => 'L\'ordre est requis.']),
                    new PositiveOrZero(['message' => 'L\'ordre doit être positif.']),
                ],
            ])
            ->add('total_workers', IntegerType::class, [
                'label' => 'Compteur initial',
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass],
                'constraints' => [
                    new NotBlank(['message' => 'Le compteur est requis.']),
                    new PositiveOrZero(),
                ],
            ])
            ->add('average_hourly_rate', MoneyType::class, [
                'label' => 'Taux horaire moyen',
                'currency' => false,
                'label_attr' => ['class' => $labelClass],
                'required' => false,
                'attr' => ['class' => $inputClass, 'placeholder' => '0.00'],
                'constraints' => [
                    new NotBlank(['message' => 'Le taux est requis.']),
                    new PositiveOrZero(),
                ],
            ])
            ->add('iconFile', FileType::class, [
                'label' => 'Icône illustrative',
                'label_attr' => ['class' => $labelClass],
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-[#0FAF7A]/10 file:text-[#0FAF7A] hover:file:bg-[#0FAF7A]/20 transition-all cursor-pointer'],
                'constraints' => [
                    new File(
                        extension_loaded('fileinfo')
                            ? [
                                'maxSize' => '2M',
                                'mimeTypes' => ['image/jpeg', 'image/png', 'image/svg+xml'],
                                'mimeTypesMessage' => 'Format invalide (JPG, PNG, SVG)',
                            ]
                            : [
                                'maxSize' => '2M',
                                'extensions' => ['jpg', 'jpeg', 'png', 'svg'],
                                'extensionsMessage' => 'Format invalide (JPG, PNG, SVG)',
                            ]
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer la catégorie',
                'attr' => ['class' => 'w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-[#0FAF7A]/20 text-sm font-bold text-white bg-[#0FAF7A] hover:bg-[#0c8f63] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0FAF7A] transition-all duration-300 transform hover:-translate-y-0.5'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkerCategory::class,
            'attr' => ['data-ajax-form' => 'true', 'class' => 'space-y-6'],
        ]);
    }
}
