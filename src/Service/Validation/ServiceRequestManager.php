<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\ServiceRequest;

final class ServiceRequestManager
{
    private const TITLE_MIN_LENGTH = 5;

    public function validate(ServiceRequest $entity): bool
    {
        $title = trim($entity->getTitle() ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Titre obligatoire.');
        }
        if (strlen($title) < self::TITLE_MIN_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Le titre doit faire au moins %d caractères.', self::TITLE_MIN_LENGTH));
        }

        if ($entity->getClient() === null) {
            throw new \InvalidArgumentException('Client obligatoire.');
        }
        if ($entity->getCategory() === null) {
            throw new \InvalidArgumentException('Catégorie obligatoire.');
        }

        $budgetMin = (float) ($entity->getBudgetMin() ?? 0);
        $budgetMax = (float) ($entity->getBudgetMax() ?? 0);
        if ($budgetMin < 0 || $budgetMax < 0) {
            throw new \InvalidArgumentException('Les montants du budget doivent être positifs ou nuls.');
        }
        if ($budgetMin > $budgetMax) {
            throw new \InvalidArgumentException('Le budget minimum ne peut pas dépasser le budget maximum.');
        }

        $duration = $entity->getDuration() ?? 0;
        if ($duration <= 0) {
            throw new \InvalidArgumentException('La durée doit être strictement positive.');
        }

        return true;
    }
}
