<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates worker_category and worker_profile tables (from GestionService merge).
 * Skips tables that already exist (messenger_messages, etc.).
 */
final class Version20260202165236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create worker_category and worker_profile tables (safe / idempotent)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('worker_category')) {
            $this->addSql('CREATE TABLE worker_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, display_order INT NOT NULL, total_workers INT NOT NULL, icon VARCHAR(255) NOT NULL, average_hourly_rate NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('worker_profile')) {
            $this->addSql('CREATE TABLE worker_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, bio LONGTEXT NOT NULL, hourly_rate VARCHAR(255) NOT NULL, experience_years INT NOT NULL, location VARCHAR(255) NOT NULL, verified TINYINT NOT NULL, availability_status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, worker_category_id INT DEFAULT NULL, INDEX IDX_B5B8D142DBCE8125 (worker_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142DBCE8125 FOREIGN KEY (worker_category_id) REFERENCES worker_category (id)');
        }

        // messenger_messages already exists in the Orion database – skip.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_profile DROP FOREIGN KEY FK_B5B8D142DBCE8125');
        $this->addSql('DROP TABLE IF EXISTS worker_profile');
        $this->addSql('DROP TABLE IF EXISTS worker_category');
    }
}
