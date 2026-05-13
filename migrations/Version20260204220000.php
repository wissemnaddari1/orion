<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cleanup migration to converge schema to current Doctrine ORM mapping:
 * - the real user table is `users` (see App\Entity\User)
 * - `email_verification.user_id` must reference `users.id`
 * - drop the legacy/stray `user` table if present
 */
final class Version20260204220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cleanup legacy user table and fix email_verification FK to users';
    }

    public function up(Schema $schema): void
    {
        // If database is fresh (no tables), do nothing (initial migration will create correct schema).
        $hasUsers = (bool) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
        $hasEmailVerification = (bool) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification'");

        if ($hasEmailVerification) {
            // Drop FK to legacy `user` table if it exists.
            $fkToUser = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'email_verification'
                   AND REFERENCED_TABLE_NAME = 'user'
                 LIMIT 1"
            );

            if (is_string($fkToUser) && $fkToUser !== '') {
                $this->addSql(sprintf('ALTER TABLE email_verification DROP FOREIGN KEY `%s`', $fkToUser));
            }

            // Ensure FK to `users` exists.
            if ($hasUsers) {
                $fkToUsers = $this->connection->fetchOne(
                    "SELECT CONSTRAINT_NAME
                     FROM information_schema.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'email_verification'
                       AND REFERENCED_TABLE_NAME = 'users'
                     LIMIT 1"
                );

                if (! (is_string($fkToUsers) && $fkToUsers !== '')) {
                    // Ensure there is an index on user_id (MySQL requires it for FK).
                    $hasIndex = $this->connection->fetchOne(
                        "SELECT COUNT(*)
                         FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'email_verification'
                           AND COLUMN_NAME = 'user_id'"
                    );

                    if ((int) $hasIndex === 0) {
                        $this->addSql('CREATE INDEX IDX_EMAIL_VERIFICATION_USER_ID ON email_verification (user_id)');
                    }

                    $this->addSql('ALTER TABLE email_verification ADD CONSTRAINT FK_EMAIL_VERIFICATION_USER_ID FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
                }
            }
        }

        // Drop legacy `user` table if it exists and nothing references it anymore.
        $hasUser = (bool) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user'");
        if ($hasUser) {
            $refCount = $this->connection->fetchOne(
                "SELECT COUNT(*)
                 FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME = 'user'"
            );
            if ((int) $refCount === 0) {
                $this->addSql('DROP TABLE `user`');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible cleanup in practice. Keeping down() empty is safest.
        $this->abortIf(true, 'This migration is not safely reversible.');
    }
}

