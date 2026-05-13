<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Ticket;

final class TicketManager
{
    private const VALID_STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
    private const VALID_PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public function validate(Ticket $entity): bool
    {
        if (trim($entity->getSubject() ?? '') === '') {
            throw new \InvalidArgumentException('Sujet du ticket obligatoire.');
        }
        if ($entity->getCategory() === null) {
            throw new \InvalidArgumentException('Catégorie du ticket obligatoire.');
        }
        $status = $entity->getStatus();
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Statut du ticket invalide.');
        }
        $priority = $entity->getPriority();
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException('Priorité du ticket invalide.');
        }
        return true;
    }
}
