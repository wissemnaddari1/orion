<?php

namespace App\Enum;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case CLIENT = 'CLIENT';
    case WORKER = 'WORKER';

    /**
     * Get Symfony role string for security (prefixed with ROLE_)
     */
    public function getRole(): string
    {
        return 'ROLE_' . $this->value;
    }

    /**
     * Get role from string (for form/validation)
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper($value));
    }
}
