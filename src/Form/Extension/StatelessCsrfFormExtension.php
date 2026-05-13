<?php

namespace App\Form\Extension;

use App\Security\Csrf\StatelessCsrfTokenManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Stateless CSRF form extension - works without PHP sessions
 */
class StatelessCsrfFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly StatelessCsrfTokenManager $csrfTokenManager
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_manager' => $this->csrfTokenManager,
            'csrf_token_id' => null,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['csrf_protection']) {
            return;
        }

        if (!($options['compound'] ?? false)) {
            return;
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $tokenId = $options['csrf_token_id'] ?? $form->getName() ?: 'form';
            
            if (!$form->has($options['csrf_field_name'])) {
                $form->add($options['csrf_field_name'], \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, [
                    'mapped' => false,
                    'data' => $this->csrfTokenManager->getToken($tokenId)->getValue(),
                ]);
            }
        });
    }
}
