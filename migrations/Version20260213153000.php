<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI insight fields to ticket table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD ai_sentiment VARCHAR(16) DEFAULT NULL, ADD ai_urgency VARCHAR(16) DEFAULT NULL, ADD ai_suggested_priority VARCHAR(16) DEFAULT NULL, ADD ai_summary LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP ai_sentiment, DROP ai_urgency, DROP ai_suggested_priority, DROP ai_summary');
    }
}
