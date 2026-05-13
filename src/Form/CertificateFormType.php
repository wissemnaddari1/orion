<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class CertificateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('certificateFile', FileType::class, [
                'label' => 'Professional Certificate',
                'required' => true,
                'attr' => [
                    'class' => 'w-full text-sm text-slate-900 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none dark:text-slate-400 dark:bg-slate-900 dark:border-slate-700',
                    'accept' => '.pdf,.jpg,.jpeg,.png,.webp',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please upload your professional certificate.']),
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid document (PDF, JPEG, PNG, or WebP).',
                    ]),
                ],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'certificate';
    }
}
