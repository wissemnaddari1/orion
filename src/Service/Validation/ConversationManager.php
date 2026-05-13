<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\Conversation;

final class ConversationManager
{
    public function validate(Conversation $entity): bool
    {
        if ($entity->getContract() === null) {
            throw new \InvalidArgumentException('Contrat obligatoire.');
        }
        if ($entity->getClient() === null) {
            throw new \InvalidArgumentException('Client obligatoire.');
        }
        if ($entity->getWorker() === null) {
            throw new \InvalidArgumentException('Worker obligatoire.');
        }
        return true;
    }
}
