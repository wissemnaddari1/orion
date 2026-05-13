<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304062000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce NOT NULL on created_by_id for blameable entities.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->creatorTables() as $table) {
            $this->dropCreatedByForeignKeys($table);
            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` CHANGE created_by_id created_by_id INT NOT NULL',
                $table
            ));
            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` ADD FOREIGN KEY (created_by_id) REFERENCES users (id)',
                $table
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->creatorTables() as $table) {
            $this->dropCreatedByForeignKeys($table);
            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` CHANGE created_by_id created_by_id INT DEFAULT NULL',
                $table
            ));
            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` ADD FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL',
                $table
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function creatorTables(): array
    {
        return [
            'ai_recommendation',
            'category_ticket',
            'contract',
            'conversation',
            'conversation_message',
            'face_profiles',
            'milestone',
            'negotiation',
            'notification',
            'offer',
            'service_request',
            'sub_ticket',
            'ticket',
            'users',
            'worker_category',
            'worker_profile',
        ];
    }

    private function dropCreatedByForeignKeys(string $table): void
    {
        $constraints = $this->connection->fetchFirstColumn(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [
                'table_name' => $table,
                'column_name' => 'created_by_id',
            ]
        );

        foreach ($constraints as $constraintName) {
            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $table,
                $constraintName
            ));
        }
    }
}
