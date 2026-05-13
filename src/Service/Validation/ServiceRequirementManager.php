<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\ServiceRequirement;

final class ServiceRequirementManager
{
    public function validate(ServiceRequirement $entity): bool
    {
        if (trim($entity->getTitle() ?? '') === '') {
            throw new \InvalidArgumentException('Titre de l\'exigence obligatoire.');
        }
        if (trim($entity->getDetails() ?? '') === '') {
            throw new \InvalidArgumentException('Détails de l\'exigence obligatoires.');
        }
        if ($entity->getService() === null) {
            throw new \InvalidArgumentException('Demande de service obligatoire.');
        }
        if (($entity->getPriorityLevel() ?? 0) < 0) {
            throw new \InvalidArgumentException('Le niveau de priorité doit être positif ou nul.');
        }
        return true;
    }
}
