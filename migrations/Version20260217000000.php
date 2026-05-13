<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 2FA fields: two_factor_secret, two_factor_temp_secret, two_factor_backup_codes, two_factor_failed_attempts, two_factor_locked_until to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(512) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS two_factor_temp_secret VARCHAR(512) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS two_factor_backup_codes JSON DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS two_factor_failed_attempts INT DEFAULT 0 NOT NULL,
            ADD COLUMN IF NOT EXISTS two_factor_locked_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            DROP COLUMN IF EXISTS two_factor_secret,
            DROP COLUMN IF EXISTS two_factor_temp_secret,
            DROP COLUMN IF EXISTS two_factor_backup_codes,
            DROP COLUMN IF EXISTS two_factor_failed_attempts,
            DROP COLUMN IF EXISTS two_factor_locked_until');
    }
}
