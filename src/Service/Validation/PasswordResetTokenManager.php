<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\PasswordResetToken;

final class PasswordResetTokenManager
{
    public function validate(PasswordResetToken $entity): bool
    {
        if (trim($entity->getTokenHash() ?? '') === '') {
            throw new \InvalidArgumentException('Token obligatoire.');
        }
        $expiresAt = $entity->getExpiresAt();
        $requestedAt = $entity->getRequestedAt();
        if ($expiresAt !== null && $requestedAt !== null && $expiresAt <= $requestedAt) {
            throw new \InvalidArgumentException('La date d\'expiration doit être après la date de demande.');
        }
        return true;
    }
}
