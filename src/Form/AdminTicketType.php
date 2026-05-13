<?php

namespace App\Form;

use App\Entity\CategoryTicket;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin form type for creating a ticket on behalf of a user
 * Includes server-side validation (controle de saisie)
 */
class AdminTicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('createdBy', EntityType::class, [
                'label' => 'User (Client/Worker)',
                'class' => User::class,
                'required' => false,
                'choice_label' => function (User $user): string {
                    return sprintf(
                        '%s %s (%s) [%s]',
                        $user->getFirstName(),
                        $user->getLastName(),
                        $user->getEmail(),
                        $user->getRole()->value
                    );
                },
                'placeholder' => 'Select a user',
                'query_builder' => function (EntityRepository $repo) {
                    return $repo->createQueryBuilder('u')
                        ->where('u.role IN (:roles)')
                        ->andWhere('u.status = :status')
                        ->setParameter('roles', [UserRole::CLIENT->value, UserRole::WORKER->value])
                        ->setParameter('status', UserStatus::ACTIVE->value)
                        ->orderBy('u.firstName', 'ASC');
                },
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a user.'
                    ])
                ]
            ])
            ->add('subject', TextType::class, [
                'label' => 'Subject',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter ticket subject',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a subject for the ticket.'
                    ]),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Subject must be at least {{ limit }} characters.',
                        'maxMessage' => 'Subject cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('category', EntityType::class, [
                'label' => 'Category',
                'class' => CategoryTicket::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a category.'
                    ])
                ]
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priority',
                'required' => false,
                'choices' => [
                    'Low' => 'LOW',
                    'Medium' => 'MEDIUM',
                    'High' => 'HIGH',
                    'Urgent' => 'URGENT'
                ],
                'placeholder' => 'Select priority',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a priority level.'
                    ]),
                    new Assert\Choice([
                        'choices' => ['LOW', 'MEDIUM', 'HIGH', 'URGENT'],
                        'message' => 'Invalid priority value.'
                    ])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Describe the issue or question in detail',
                    'class' => 'form-control',
                    'rows' => 5
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a message.'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'Message must be at least {{ limit }} characters.',
                        'maxMessage' => 'Message cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Attachment (optional)',
                'mapped' => false,
                'required' => false,
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
                        'mimeTypesMessage' => 'Please upload a valid document (PDF, Word, Text, Image, or ZIP/RAR). Max size: 10MB.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'admin_ticket_item',
        ]);
    }
}
