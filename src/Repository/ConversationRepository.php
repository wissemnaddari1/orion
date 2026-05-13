<?php

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Find or create conversation for a fully-signed contract.
     */
    public function getOrCreateForContract(Contract $contract): Conversation
    {
        $existing = $this->findOneBy(['contract' => $contract]);
        if ($existing !== null) {
            return $existing;
        }
        $conversation = new Conversation();
        $conversation->setContract($contract);
        $conversation->setClient($contract->getClient());
        $conversation->setWorker($contract->getWorker());
        return $conversation;
    }

    /**
     * Ensure a Conversation exists for every fully-signed contract where user is client or worker.
     * Call before findForUser when opening messagerie.
     */
    public function ensureConversationsForUser(User $user): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        // Race-safe creation using SQL (avoids unique constraint errors if multiple requests run in parallel).
        // MySQL/MariaDB: INSERT IGNORE skips duplicates on the UNIQUE(contract_id) index.
        $sql = <<<SQL
INSERT IGNORE INTO conversation
    (contract_id, client_id, worker_id, created_at, last_message_at, deleted_by_client_at, deleted_by_worker_at)
SELECT
    c.id, c.client_id, c.worker_id, NOW(), NULL, NULL, NULL
FROM contract c
WHERE
    (c.client_id = :uid OR c.worker_id = :uid)
    AND c.client_signed = 1
    AND c.worker_signed = 1
SQL;

        $this->_em->getConnection()->executeStatement($sql, ['uid' => $userId]);
    }

    /**
     * List conversations for user (client or worker), excluding those deleted by this user.
     * Only contracts that are/were fully signed.
     *
     * @return Conversation[]
     */
    public function findForUser(User $user, int $page = 1, int $limit = 20): array
    {
        $userId = $user->getId();
        if ($userId === null) {
            return [];
        }
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.contract', 'contract')
            ->addSelect('contract')
            ->innerJoin('c.client', 'client')
            ->addSelect('client')
            ->innerJoin('c.worker', 'worker')
            ->addSelect('worker')
            ->andWhere('((IDENTITY(c.client) = :uid AND c.deletedByClientAt IS NULL) OR (IDENTITY(c.worker) = :uid AND c.deletedByWorkerAt IS NULL))')
            ->setParameter('uid', $userId)
            ->andWhere('contract.clientSigned = true AND contract.workerSigned = true')
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function findOneByIdForParticipant(int $id, User $user): ?Conversation
    {
        $conversation = $this->find($id);
        if ($conversation === null || !$conversation->isParticipant($user) || $conversation->isDeletedBy($user)) {
            return null;
        }
        return $conversation;
    }

    public function findOneByContract(Contract $contract): ?Conversation
    {
        return $this->findOneBy(['contract' => $contract]);
    }
}
