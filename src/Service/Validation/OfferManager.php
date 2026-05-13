<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Offer;

final class OfferManager
{
    private const VALID_STATUSES = [
        Offer::STATUS_PENDING,
        Offer::STATUS_ACCEPTED,
        Offer::STATUS_DECLINED,
        Offer::STATUS_REJECTED,
        Offer::STATUS_NEGOTIATING,
        'EXPIRED',
    ];

    public function validate(Offer $entity): bool
    {
        $price = (float) $entity->getPrice();
        if ($price < 0) {
            throw new \InvalidArgumentException('Le prix doit être positif ou nul.');
        }

        $estimatedDays = $entity->getEstimatedTimeDays();
        if ($estimatedDays <= 0) {
            throw new \InvalidArgumentException('Le délai estimé (jours) doit être strictement positif.');
        }

        $status = $entity->getStatus();
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Statut de l\'offre invalide.');
        }

        if ($entity->getServiceRequest() === null) {
            throw new \InvalidArgumentException('Demande de service obligatoire.');
        }
        if ($entity->getWorker() === null) {
            throw new \InvalidArgumentException('Worker obligatoire.');
        }

        return true;
    }
}
