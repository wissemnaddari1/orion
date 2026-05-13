<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form type for replying to a ticket
 * Implements strict server-side validation
 */
class SubTicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Your Reply',
                'attr' => [
                    'placeholder' => 'Type your reply here...',
                    'class' => 'form-control',
                    'rows' => 4
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a reply message.'
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 5000,
                        'minMessage' => 'Reply must be at least {{ limit }} characters.',
                        'maxMessage' => 'Reply cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Attachment (optional)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar'
                ],
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain',
                            'image/jpeg',
                            'image/png',
                            'application/zip',
                            'application/x-rar-compressed'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid document. Allowed: PDF, Word, Text, Image, ZIP/RAR. Max: 10MB.'
                    ])
                ]
            ]);

        // Add "internal note" checkbox only for admins
        if ($options['is_admin']) {
            $builder->add('isInternal', CheckboxType::class, [
                'label' => 'Internal Note (Admin only - not visible to user)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'subticket_item',
            'is_admin' => false  // Pass this option when creating the form
        ]);
    }
}
