<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align ORM/DB relation constraints for Doctrine Doctor warnings.
 */
final class Version20260304043622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add worker_profile.user_id FK/index and enforce ON DELETE CASCADE on service_requirement.service_id';
    }

    public function up(Schema $schema): void
    {
        // Ensure index for relation lookups.
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_B5B8D142A76ED395 ON worker_profile (user_id)');

        // Ensure worker_profile.user_id foreign key exists with ON DELETE CASCADE.
        $this->addSql(<<<'SQL'
SET @fk_exists := (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'worker_profile'
      AND COLUMN_NAME = 'user_id'
      AND REFERENCED_TABLE_NAME = 'users'
      AND REFERENCED_COLUMN_NAME = 'id'
);
SQL);
        $this->addSql(<<<'SQL'
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
    'SELECT 1'
);
SQL);
        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        // Recreate service_requirement -> service_request FK with ON DELETE CASCADE.
        $this->addSql(<<<'SQL'
SET @sr_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'service_requirement'
      AND CONSTRAINT_NAME = 'FK_17A573FCED5CA9E6'
);
SQL);
        $this->addSql("SET @sql := IF(@sr_fk_exists = 1, 'ALTER TABLE service_requirement DROP FOREIGN KEY FK_17A573FCED5CA9E6', 'SELECT 1')");
        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');
        $this->addSql('ALTER TABLE service_requirement ADD CONSTRAINT FK_17A573FCED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_request (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Keep down migration intentionally minimal and safe.
        $this->addSql('ALTER TABLE service_requirement DROP FOREIGN KEY FK_17A573FCED5CA9E6');
        $this->addSql('ALTER TABLE service_requirement ADD CONSTRAINT FK_17A573FCED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_request (id)');
    }
}
