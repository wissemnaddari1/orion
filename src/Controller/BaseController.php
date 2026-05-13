<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Shared controller base for the application.
 */
abstract class BaseController extends AbstractController
{
    /**
     * Returns the current application user (concrete User entity).
     * Use this when you need User-specific methods (getFullName, getUsername, etc.).
     *
     * @throws \LogicException when the current user is not authenticated or not an App\Entity\User
     */
    protected function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Expected authenticated User.');
        }
        return $user;
    }
}

