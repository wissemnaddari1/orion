<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Contract;

final class ContractManager
{
    public function validate(Contract $entity): bool
    {
        if (trim($entity->getTitle() ?? '') === '') {
            throw new \InvalidArgumentException('Titre du contrat obligatoire.');
        }
        if (trim($entity->getScope() ?? '') === '') {
            throw new \InvalidArgumentException('Périmètre (scope) obligatoire.');
        }

        if ($entity->getClient() === null) {
            throw new \InvalidArgumentException('Client obligatoire.');
        }
        if ($entity->getWorker() === null) {
            throw new \InvalidArgumentException('Worker obligatoire.');
        }

        $price = (float) ($entity->getAgreedPrice() ?? 0);
        if ($price < 0) {
            throw new \InvalidArgumentException('Le prix convenu doit être positif ou nul.');
        }

        $start = $entity->getStartDate();
        $end = $entity->getEndDate();
        if ($start !== null && $end !== null && $start > $end) {
            throw new \InvalidArgumentException('La date de début ne peut pas être après la date de fin.');
        }

        if (!in_array($entity->getStatus(), Contract::STATUSES, true)) {
            throw new \InvalidArgumentException('Statut du contrat invalide.');
        }

        return true;
    }
}
