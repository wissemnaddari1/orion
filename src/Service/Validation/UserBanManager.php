<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\UserBan;

final class UserBanManager
{
    public function validate(UserBan $entity): bool
    {
        if ($entity->getUser() === null) {
            throw new \InvalidArgumentException('Utilisateur banni obligatoire.');
        }
        if (trim($entity->getReason() ?? '') === '') {
            throw new \InvalidArgumentException('Raison du bannissement obligatoire.');
        }
        $type = $entity->getType();
        if (!in_array($type, [UserBan::TYPE_TEMP, UserBan::TYPE_PERM], true)) {
            throw new \InvalidArgumentException('Type de bannissement invalide (TEMP ou PERM).');
        }
        if ($type === UserBan::TYPE_TEMP && $entity->getEndsAt() === null) {
            throw new \InvalidArgumentException('Un bannissement temporaire doit avoir une date de fin.');
        }
        if ($entity->getEndsAt() !== null && $entity->getBannedAt() !== null && $entity->getEndsAt() <= $entity->getBannedAt()) {
            throw new \InvalidArgumentException('La date de fin doit être après la date de bannissement.');
        }
        return true;
    }
}
