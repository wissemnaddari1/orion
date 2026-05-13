<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\CategoryTicket;

final class CategoryTicketManager
{
    public function validate(CategoryTicket $entity): bool
    {
        if (trim($entity->getName() ?? '') === '') {
            throw new \InvalidArgumentException('Nom de la catégorie obligatoire.');
        }
        return true;
    }
}
