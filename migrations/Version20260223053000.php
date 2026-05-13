<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223053000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI risk_score and risk_level columns to the contract table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract ADD risk_score DOUBLE PRECISION DEFAULT NULL, ADD risk_level VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP risk_score, DROP risk_level');
    }
}
