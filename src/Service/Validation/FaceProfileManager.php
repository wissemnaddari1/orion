<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\FaceProfile;

final class FaceProfileManager
{
    public function validate(FaceProfile $entity): bool
    {
        if ($entity->getUser() === null) {
            throw new \InvalidArgumentException('Utilisateur obligatoire pour le profil facial.');
        }
        $embedding = $entity->getEmbedding();
        if (!is_array($embedding) || empty($embedding)) {
            throw new \InvalidArgumentException('L\'embedding facial ne peut pas être vide.');
        }
        return true;
    }
}
