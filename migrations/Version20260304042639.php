<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doctrine Doctor fixes:
 *  - Add missing performance indexes on offer table
 *  - Fix nullable types on user_ban and users.ban_ends_at
 *  - Fix ai_recommendation.created_at type
 *  - Drop unused columns from worker_profile
 */
final class Version20260304042639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Doctrine Doctor performance & integrity fixes: offer indexes, schema type alignment';
    }

    public function up(Schema $schema): void
    {
        // Add missing performance indexes on offer table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_offer_status ON offer (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_offer_created_at ON offer (created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_offer_price ON offer (price)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_offer_estimated_time ON offer (estimated_time_days)');

        // Fix nullable datetime column types (remove immutable DC2Type comment mismatch)
        $this->addSql("ALTER TABLE users CHANGE ban_ends_at ban_ends_at DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE user_ban 
            CHANGE banned_at banned_at DATETIME NOT NULL,
            CHANGE ends_at ends_at DATETIME DEFAULT NULL,
            CHANGE lifted_at lifted_at DATETIME DEFAULT NULL");

        // Fix ai_recommendation.created_at (was COMMENT DC2Type:datetime_immutable, now plain DATETIME)
        $this->addSql('ALTER TABLE ai_recommendation CHANGE created_at created_at DATETIME NOT NULL');

        // Drop unused columns from worker_profile if they still exist
        $this->addSql('ALTER TABLE worker_profile DROP COLUMN IF EXISTS cv_file_path');
        $this->addSql('ALTER TABLE worker_profile DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE worker_profile DROP COLUMN IF EXISTS longitude');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_offer_status ON offer');
        $this->addSql('DROP INDEX IF EXISTS idx_offer_created_at ON offer');
        $this->addSql('DROP INDEX IF EXISTS idx_offer_price ON offer');
        $this->addSql('DROP INDEX IF EXISTS idx_offer_estimated_time ON offer');
    }
}
