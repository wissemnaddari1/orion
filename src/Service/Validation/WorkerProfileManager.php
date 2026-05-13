<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\WorkerProfile;

final class WorkerProfileManager
{
    public function validate(WorkerProfile $entity): bool
    {
        if ($entity->getUser() === null) {
            throw new \InvalidArgumentException('Utilisateur obligatoire.');
        }
        if (trim($entity->getTitle() ?? '') === '') {
            throw new \InvalidArgumentException('Titre professionnel obligatoire.');
        }
        if (trim($entity->getBio() ?? '') === '') {
            throw new \InvalidArgumentException('Bio obligatoire.');
        }
        $hourlyRate = (float) ($entity->getHourlyRate() ?? 0);
        if ($hourlyRate < 0) {
            throw new \InvalidArgumentException('Le taux horaire ne peut pas être négatif.');
        }
        if (($entity->getExperienceYears() ?? -1) < 0) {
            throw new \InvalidArgumentException('Les années d\'expérience ne peuvent pas être négatives.');
        }
        return true;
    }
}
