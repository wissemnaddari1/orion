<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Milestone;

final class MilestoneManager
{
    public function validate(Milestone $entity): bool
    {
        if (trim($entity->getTitle() ?? '') === '') {
            throw new \InvalidArgumentException('Titre du jalon obligatoire.');
        }

        if ($entity->getContract() === null) {
            throw new \InvalidArgumentException('Contrat obligatoire.');
        }

        $orderIndex = $entity->getOrderIndex() ?? 0;
        if ($orderIndex < 0) {
            throw new \InvalidArgumentException('L\'index d\'ordre doit être positif ou nul.');
        }

        if (!in_array($entity->getStatus(), Milestone::STATUSES, true)) {
            throw new \InvalidArgumentException('Statut du jalon invalide.');
        }

        $amount = $entity->getAmount();
        if ($amount !== null && (float) $amount < 0) {
            throw new \InvalidArgumentException('Le montant du jalon ne peut pas être négatif.');
        }

        return true;
    }
}
