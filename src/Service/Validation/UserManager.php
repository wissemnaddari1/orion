<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;

final class UserManager
{
    private const USERNAME_REGEX = '/^[A-Za-z0-9._-]+$/';

    public function validate(User $entity): bool
    {
        $email = $entity->getEmail();
        if ($email === null || $email === '') {
            throw new \InvalidArgumentException('Email obligatoire.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide.');
        }

        $username = $entity->getUsername();
        if ($username === null || $username === '') {
            throw new \InvalidArgumentException('Username obligatoire.');
        }
        $len = strlen($username);
        if ($len < 3 || $len > 50) {
            throw new \InvalidArgumentException('Username doit faire entre 3 et 50 caractères.');
        }
        if (!preg_match(self::USERNAME_REGEX, $username)) {
            throw new \InvalidArgumentException('Username peut contenir uniquement lettres, chiffres, point, tiret et underscore.');
        }

        $role = $entity->getRole();
        $roleValue = $role instanceof UserRole ? $role->value : (string) $role;
        $allowedRoles = array_map(fn (UserRole $r) => $r->value, UserRole::cases());
        if (!in_array($roleValue, $allowedRoles, true)) {
            throw new \InvalidArgumentException('Rôle invalide.');
        }

        $status = $entity->getStatus();
        $statusValue = $status instanceof UserStatus ? $status->value : (string) $status;
        $allowedStatuses = array_map(fn (UserStatus $s) => $s->value, UserStatus::cases());
        if (!in_array($statusValue, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        if (trim($entity->getFirstName() ?? '') === '') {
            throw new \InvalidArgumentException('Prénom obligatoire.');
        }
        if (trim($entity->getLastName() ?? '') === '') {
            throw new \InvalidArgumentException('Nom obligatoire.');
        }

        return true;
    }
}
