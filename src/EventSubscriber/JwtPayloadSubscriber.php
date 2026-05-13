<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds custom claims to the JWT payload when a token is created.
 * Payload includes: id, username, email, profile_picture, status, roles (exp/iat added by encoder).
 */
final class JwtPayloadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => ['onJwtCreated', 0],
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['username'] = $user->getUsername();
        $payload['profile_picture'] = $user->getProfilePicture();
        $payload['status'] = $user->getStatus()->value;

        $event->setData($payload);
    }
}
