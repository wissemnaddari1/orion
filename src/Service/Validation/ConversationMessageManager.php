<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\ConversationMessage;

final class ConversationMessageManager
{
    public function validate(ConversationMessage $entity): bool
    {
        if (trim($entity->getContent() ?? '') === '') {
            throw new \InvalidArgumentException('Contenu du message obligatoire.');
        }
        if ($entity->getConversation() === null) {
            throw new \InvalidArgumentException('Conversation obligatoire.');
        }
        if ($entity->getSender() === null) {
            throw new \InvalidArgumentException('Expéditeur obligatoire.');
        }
        return true;
    }
}
