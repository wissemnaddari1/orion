<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Notification;

final class NotificationManager
{
    private const VALID_TYPES = [
        Notification::TYPE_MATCH_FOUND,
        Notification::TYPE_OFFER_RECEIVED,
        Notification::TYPE_OFFER_STATUS_UPDATED,
        Notification::TYPE_NEGOTIATION_MESSAGE,
    ];

    public function validate(Notification $entity): bool
    {
        if ($entity->getUser() === null) {
            throw new \InvalidArgumentException('Utilisateur obligatoire.');
        }
        if (trim($entity->getType() ?? '') === '') {
            throw new \InvalidArgumentException('Type de notification obligatoire.');
        }
        if (!in_array($entity->getType(), self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException('Type de notification invalide.');
        }
        if (trim($entity->getTitle() ?? '') === '') {
            throw new \InvalidArgumentException('Titre de la notification obligatoire.');
        }
        return true;
    }
}
