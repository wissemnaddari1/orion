<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\SubTicket;

final class SubTicketManager
{
    public function validate(SubTicket $entity): bool
    {
        if (trim($entity->getMessage() ?? '') === '') {
            throw new \InvalidArgumentException('Message obligatoire.');
        }
        if ($entity->getTicket() === null) {
            throw new \InvalidArgumentException('Ticket obligatoire.');
        }
        if ($entity->getSender() === null) {
            throw new \InvalidArgumentException('Expéditeur obligatoire.');
        }
        return true;
    }
}
