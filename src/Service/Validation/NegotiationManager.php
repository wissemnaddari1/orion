<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Negotiation;

final class NegotiationManager
{
    private const VALID_STATUSES = ['OPEN', 'COUNTERED', 'ACCEPTED', 'REJECTED', 'EXPIRED'];

    public function validate(Negotiation $entity): bool
    {
        if ($entity->getOffer() === null) {
            throw new \InvalidArgumentException('Offre obligatoire.');
        }
        if ($entity->getOpenedBy() === null) {
            throw new \InvalidArgumentException('Ouvert par (utilisateur) obligatoire.');
        }
        $status = $entity->getStatus();
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Statut de la négociation invalide.');
        }
        $counterPrice = $entity->getCounterPrice();
        if ($counterPrice !== null && (float) $counterPrice < 0) {
            throw new \InvalidArgumentException('Le contre-prix ne peut pas être négatif.');
        }
        return true;
    }
}
