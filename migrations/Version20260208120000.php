<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add face_image_path to users if missing (e.g. DB created before entity had this column).
 */
final class Version20260208120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face_image_path column to users table if missing';
    }

    public function up(Schema $schema): void
    {
        $hasColumn = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'face_image_path'"
        );

        if (!$hasColumn) {
            $this->addSql('ALTER TABLE users ADD face_image_path VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN face_image_path');
    }
}
