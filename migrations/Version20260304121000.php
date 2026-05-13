<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align table collations with database default (utf8mb4_unicode_ci).
 * Fixes Doctrine Doctor: "tables with different collation than database".
 */
final class Version20260304121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert utf8mb4_general_ci tables to utf8mb4_unicode_ci.';
    }

    public function up(Schema $schema): void
    {
        $db = $this->connection->getDatabase();

        /** @var string[] $tables */
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = :db
  AND TABLE_COLLATION = 'utf8mb4_general_ci'
SQL,
            ['db' => $db]
        );

        foreach ($tables as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '``', $table)
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $db = $this->connection->getDatabase();

        /** @var string[] $tables */
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = :db
  AND TABLE_COLLATION = 'utf8mb4_unicode_ci'
SQL,
            ['db' => $db]
        );

        foreach ($tables as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
                str_replace('`', '``', $table)
            ));
        }
    }
}

