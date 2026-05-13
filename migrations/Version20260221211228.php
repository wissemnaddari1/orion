<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221211228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ban fields to users (ban_note, ban_ends_at, banned_by_id, ban_type, ban_count), extend ban_reason to TEXT; create user_ban table for history.';
    }

    public function up(Schema $schema): void
    {
        $hasUserBanTable = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'user_ban'
        ") > 0;

        if (!$hasUserBanTable) {
            $this->addSql("CREATE TABLE user_ban (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                banned_by_id INT DEFAULT NULL,
                reason LONGTEXT NOT NULL,
                note LONGTEXT DEFAULT NULL,
                banned_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                ends_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                lifted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                lift_reason LONGTEXT DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                type VARCHAR(10) NOT NULL,
                INDEX idx_user_ban_user_id (user_id),
                INDEX idx_user_ban_is_active (is_active),
                INDEX IDX_89E8B16E386B8E7 (banned_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        }

        $hasUserBanUserFk = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'user_ban'
              AND constraint_name = 'FK_89E8B16EA76ED395'
        ") > 0;
        if (!$hasUserBanUserFk) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }

        $hasUserBanByFk = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'user_ban'
              AND constraint_name = 'FK_89E8B16E386B8E7'
        ") > 0;
        if (!$hasUserBanByFk) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16E386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }

        $hasBanNote = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'ban_note'
        ") > 0;
        if (!$hasBanNote) {
            $this->addSql('ALTER TABLE users ADD ban_note LONGTEXT DEFAULT NULL');
        }

        $hasBanEndsAt = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'ban_ends_at'
        ") > 0;
        if (!$hasBanEndsAt) {
            $this->addSql("ALTER TABLE users ADD ban_ends_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }

        $hasBanType = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'ban_type'
        ") > 0;
        if (!$hasBanType) {
            $this->addSql('ALTER TABLE users ADD ban_type VARCHAR(10) DEFAULT NULL');
        }

        $hasBanCount = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'ban_count'
        ") > 0;
        if (!$hasBanCount) {
            $this->addSql('ALTER TABLE users ADD ban_count INT DEFAULT 0 NOT NULL');
        }

        $hasBannedById = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'banned_by_id'
        ") > 0;
        if (!$hasBannedById) {
            $this->addSql('ALTER TABLE users ADD banned_by_id INT DEFAULT NULL');
        }

        $hasUsersBannedByFk = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND constraint_name = 'FK_1483A5E9386B8E7'
        ") > 0;
        if (!$hasUsersBannedByFk) {
            $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }

        $hasUsersBannedByIndex = (int) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND index_name = 'IDX_1483A5E9386B8E7'
        ") > 0;
        if (!$hasUsersBannedByIndex) {
            $this->addSql('CREATE INDEX IDX_1483A5E9386B8E7 ON users (banned_by_id)');
        }

        $banReasonType = (string) $this->connection->fetchOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = 'ban_reason'
        ");
        if ($banReasonType !== 'longtext') {
            $this->addSql('ALTER TABLE users CHANGE ban_reason ban_reason LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16EA76ED395');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16E386B8E7');
        $this->addSql('DROP TABLE user_ban');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9386B8E7');
        $this->addSql('DROP INDEX IDX_1483A5E9386B8E7 ON users');
        $this->addSql('ALTER TABLE users DROP ban_note, DROP ban_ends_at, DROP ban_type, DROP ban_count, DROP banned_by_id');
        $this->addSql('ALTER TABLE users CHANGE ban_reason ban_reason VARCHAR(500) DEFAULT NULL');
    }
}
