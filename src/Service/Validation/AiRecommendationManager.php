<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\AiRecommendation;

final class AiRecommendationManager
{
    public function validate(AiRecommendation $entity): bool
    {
        if ($entity->getServiceRequest() === null) {
            throw new \InvalidArgumentException('Demande de service obligatoire.');
        }
        if ($entity->getRecommendedUser() === null) {
            throw new \InvalidArgumentException('Utilisateur recommandé obligatoire.');
        }
        $score = $entity->getScore();
        if ($score < 0.0 || $score > 1.0) {
            throw new \InvalidArgumentException('Le score doit être entre 0 et 1.');
        }
        return true;
    }
}
