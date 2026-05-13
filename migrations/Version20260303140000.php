<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add conversation and conversation_message tables for Messagerie (client-worker chat).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation (
            id INT AUTO_INCREMENT NOT NULL,
            contract_id INT NOT NULL,
            client_id INT NOT NULL,
            worker_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            last_message_at DATETIME DEFAULT NULL,
            deleted_by_client_at DATETIME DEFAULT NULL,
            deleted_by_worker_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_conversation_contract (contract_id),
            INDEX IDX_conversation_contract_id (contract_id),
            INDEX IDX_conversation_client (client_id),
            INDEX IDX_conversation_worker (worker_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conv_contract FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conv_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conv_worker FOREIGN KEY (worker_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE conversation_message (
            id INT AUTO_INCREMENT NOT NULL,
            conversation_id INT NOT NULL,
            sender_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            INDEX IDX_conv_msg_conversation_created (conversation_id, created_at),
            INDEX IDX_conv_msg_sender (sender_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_conv_msg_conversation FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_conv_msg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_conv_msg_conversation');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_conv_msg_sender');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_conv_contract');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_conv_client');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_conv_worker');
        $this->addSql('DROP TABLE conversation');
    }
}
