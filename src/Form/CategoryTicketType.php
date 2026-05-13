<?php

namespace App\Form;

use App\Entity\CategoryTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form type for managing ticket categories (Admin only)
 * Implements server-side validation (controle de saisie)
 */
class CategoryTicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Category Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'e.g. Technical Issue',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Category name is required.'
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Category name must be at least {{ limit }} characters.',
                        'maxMessage' => 'Category name cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Short description of the category',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Description cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategoryTicket::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'category_ticket_item',
        ]);
    }
}
