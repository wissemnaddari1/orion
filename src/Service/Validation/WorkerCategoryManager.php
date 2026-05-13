<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\WorkerCategory;

final class WorkerCategoryManager
{
    public function validate(WorkerCategory $entity): bool
    {
        if (trim($entity->getName() ?? '') === '') {
            throw new \InvalidArgumentException('Nom de la catégorie obligatoire.');
        }
        if (trim($entity->getDescription() ?? '') === '') {
            throw new \InvalidArgumentException('Description de la catégorie obligatoire.');
        }
        if ($entity->getDisplayOrder() < 0) {
            throw new \InvalidArgumentException('L\'ordre d\'affichage doit être positif ou nul.');
        }
        $rate = (float) ($entity->getAverageHourlyRate() ?? 0);
        if ($rate < 0) {
            throw new \InvalidArgumentException('Le taux horaire moyen ne peut pas être négatif.');
        }
        return true;
    }
}
