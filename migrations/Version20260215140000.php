<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add login lockout + ban fields to users; add password_reset_tokens table.
 */
final class Version20260215140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add failed_login_attempts, login_locked_until, last_failed_login_at, is_banned, banned_at, ban_reason to users; create password_reset_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD failed_login_attempts INT DEFAULT 0 NOT NULL, ADD login_locked_until DATETIME DEFAULT NULL, ADD last_failed_login_at DATETIME DEFAULT NULL, ADD is_banned TINYINT(1) DEFAULT 0 NOT NULL, ADD banned_at DATETIME DEFAULT NULL, ADD ban_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, INDEX IDX_password_reset_tokens_user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_password_reset_tokens_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_password_reset_tokens_user_id');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('ALTER TABLE users DROP failed_login_attempts, DROP login_locked_until, DROP last_failed_login_at, DROP is_banned, DROP banned_at, DROP ban_reason');
    }
}
