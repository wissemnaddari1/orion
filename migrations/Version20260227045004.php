<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add service_request.level column if missing (fixes "Unknown column 't0.level'").
 */
final class Version20260227045004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add service_request.level column if missing';
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) > 0;
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasColumn('service_request', 'level')) {
            $this->addSql('ALTER TABLE service_request ADD level VARCHAR(50) DEFAULT \'Entry\' NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->hasColumn('service_request', 'level')) {
            $this->addSql('ALTER TABLE service_request DROP level');
        }
    }
}
