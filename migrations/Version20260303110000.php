<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_recommendation table for persisted matchmaking snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_recommendation (id INT AUTO_INCREMENT NOT NULL, service_request_id INT NOT NULL, recommended_user_id INT NOT NULL, requested_by_id INT DEFAULT NULL, score DOUBLE PRECISION NOT NULL, explanations JSON NOT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E807B918D42F8111 (service_request_id), INDEX IDX_E807B9182140D1B5 (recommended_user_id), INDEX IDX_E807B918C7C4A32C (requested_by_id), INDEX idx_ai_rec_service_created (service_request_id, created_at), INDEX idx_ai_rec_service_user (service_request_id, recommended_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ai_recommendation ADD CONSTRAINT FK_E807B918D42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_recommendation ADD CONSTRAINT FK_E807B9182140D1B5 FOREIGN KEY (recommended_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_recommendation ADD CONSTRAINT FK_E807B918C7C4A32C FOREIGN KEY (requested_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_recommendation DROP FOREIGN KEY FK_E807B918D42F8111');
        $this->addSql('ALTER TABLE ai_recommendation DROP FOREIGN KEY FK_E807B9182140D1B5');
        $this->addSql('ALTER TABLE ai_recommendation DROP FOREIGN KEY FK_E807B918C7C4A32C');
        $this->addSql('DROP TABLE ai_recommendation');
    }
}

