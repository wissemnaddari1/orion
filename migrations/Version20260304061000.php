<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304061000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure SYSTEM user exists and backfill NULL created_by_id values.';
    }

    public function up(Schema $schema): void
    {
        $systemUserId = $this->ensureSystemUserId();

        foreach ($this->creatorTables() as $table) {
            $this->connection->executeStatement(sprintf(
                'UPDATE %s SET created_by_id = :system_user_id WHERE created_by_id IS NULL',
                $table
            ), ['system_user_id' => $systemUserId]);
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally irreversible: restoring previous NULL creators is unsafe.
    }

    private function ensureSystemUserId(): int
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => 'system@orion.local']
        );

        if ($existing !== false) {
            return (int) $existing;
        }

        $username = $this->nextAvailableUsername('system');
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('users', [
            'username' => $username,
            'email' => 'system@orion.local',
            'password_hash' => $passwordHash,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'first_name' => 'System',
            'last_name' => 'Account',
            'email_verified' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inserted = $this->connection->fetchOne(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => 'system@orion.local']
        );

        if ($inserted === false) {
            throw new \RuntimeException('Failed to create or locate SYSTEM user.');
        }

        return (int) $inserted;
    }

    private function nextAvailableUsername(string $base): string
    {
        $candidate = $base;
        $suffix = 0;

        while ($this->connection->fetchOne(
            'SELECT id FROM users WHERE username = :username LIMIT 1',
            ['username' => $candidate]
        ) !== false) {
            ++$suffix;
            $candidate = sprintf('%s.%d', $base, $suffix);
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function creatorTables(): array
    {
        return [
            'ai_recommendation',
            'category_ticket',
            'contract',
            'conversation',
            'conversation_message',
            'face_profiles',
            'milestone',
            'negotiation',
            'notification',
            'offer',
            'service_request',
            'sub_ticket',
            'ticket',
            'users',
            'worker_category',
            'worker_profile',
        ];
    }
}
