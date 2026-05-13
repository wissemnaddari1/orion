<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationMessage>
 */
class ConversationMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationMessage::class);
    }

    /**
     * @return ConversationMessage[]
     */
    public function findByConversation(Conversation $conversation, ?int $afterId = null, ?string $afterTimestamp = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit);

        if ($afterId !== null) {
            $qb->andWhere('m.id > :afterId')->setParameter('afterId', $afterId);
        }
        if ($afterTimestamp !== null) {
            $qb->andWhere('m.createdAt > :afterTs')->setParameter('afterTs', $afterTimestamp);
        }

        return $qb->getQuery()->getResult();
    }
}
