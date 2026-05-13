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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin form type for editing ticket details
 */
class AdminTicketEditType extends AbstractType
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
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'required' => false,
                'choices' => [
                    'Open' => 'OPEN',
                    'In Progress' => 'IN_PROGRESS',
                    'Waiting User' => 'WAITING_USER',
                    'Closed' => 'CLOSED'
                ],
                'placeholder' => 'Select status',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a status.'
                    ]),
                    new Assert\Choice([
                        'choices' => ['OPEN', 'IN_PROGRESS', 'WAITING_USER', 'CLOSED'],
                        'message' => 'Invalid status value.'
                    ])
                ]
            ])
            ->add('resolution', TextareaType::class, [
                'label' => 'Resolution (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Resolution note for closed ticket',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Resolution cannot be longer than {{ limit }} characters.'
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
            'csrf_token_id' => 'admin_ticket_edit',
        ]);
    }
}
