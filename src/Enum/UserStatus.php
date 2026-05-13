<?php

namespace App\Enum;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case PENDING = 'PENDING';
    case BANNED = 'BANNED';

    /**
     * Get status from string (for form/validation)
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper($value));
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
