<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Set database default collation to utf8mb4_unicode_ci for accurate Unicode sorting.
 * Resolves Doctrine Doctor: "Database using collation: utf8mb4_general_ci" and
 * aligns default with the 6 tables already using utf8mb4_unicode_ci.
 */
final class Version20260304120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set database collation to utf8mb4_unicode_ci.';
    }

    public function up(Schema $schema): void
    {
        $db = $this->connection->getDatabase();
        $this->addSql(sprintf('ALTER DATABASE `%s` COLLATE = utf8mb4_unicode_ci', $db));
    }

    public function down(Schema $schema): void
    {
        $db = $this->connection->getDatabase();
        $this->addSql(sprintf('ALTER DATABASE `%s` COLLATE = utf8mb4_general_ci', $db));
    }
}
