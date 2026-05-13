<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification table and offer fields (client_id, match_score, proposed_budget, proposed_deadline, updated_at).';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $schemaManager = method_exists($conn, 'createSchemaManager')
            ? $conn->createSchemaManager()
            : $conn->getSchemaManager();
        $tables = $schemaManager->listTableNames();
        $tablesLower = array_map('strtolower', $tables);

        // Notification: create if missing, or migrate old columns (message/data -> body/payload) if present
        if (!in_array('notification', $tablesLower, true)) {
            $this->addSql('CREATE TABLE notification (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                type VARCHAR(64) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body LONGTEXT DEFAULT NULL,
                payload JSON DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_notification_user_read_created (user_id, is_read, created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        } else {
            $notifColumns = array_map(static fn ($c) => strtolower($c->getName()), $schemaManager->listTableColumns('notification'));
            if (in_array('message', $notifColumns, true) || in_array('data', $notifColumns, true)) {
                $this->addSql('ALTER TABLE notification ADD body LONGTEXT DEFAULT NULL, ADD payload JSON DEFAULT NULL, DROP message, DROP data');
            }
            $indexes = $schemaManager->listTableIndexes('notification');
            $hasIdx = false;
            foreach ($indexes as $idx) {
                $name = strtolower((string) $idx->getName());
                if ($name === 'idx_notification_user_read_created') {
                    $hasIdx = true;
                    break;
                }
            }
            if (!$hasIdx) {
                $this->addSql('CREATE INDEX idx_notification_user_read_created ON notification (user_id, is_read, created_at)');
            }
        }

        // Only add offer columns if client_id does not exist (idempotent)
        $offerColumns = $schemaManager->listTableColumns('offer');
        $columnNames = array_map(static fn ($c) => strtolower($c->getName()), $offerColumns);
        if (!in_array('client_id', $columnNames, true)) {
            $this->addSql('ALTER TABLE offer ADD updated_at DATETIME DEFAULT NULL, ADD match_score DOUBLE PRECISION DEFAULT NULL, ADD proposed_budget NUMERIC(10, 2) DEFAULT NULL, ADD proposed_deadline DATETIME DEFAULT NULL, ADD client_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_offer_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_offer_client ON offer (client_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notification');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_offer_client');
        $this->addSql('DROP INDEX IDX_offer_client ON offer');
        $this->addSql('ALTER TABLE offer DROP updated_at, DROP match_score, DROP proposed_budget, DROP proposed_deadline, DROP client_id');
    }
}
