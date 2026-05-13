<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add upfront funding and milestone delivery tracking fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE contract ADD upfront_percent NUMERIC(5, 2) DEFAULT '30.00' NOT NULL, ADD upfront_paid TINYINT(1) DEFAULT 0 NOT NULL, ADD upfront_paid_at DATETIME DEFAULT NULL, ADD released_amount NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql('ALTER TABLE milestone ADD delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE milestone DROP delivered_at');
        $this->addSql('ALTER TABLE contract DROP upfront_percent, DROP upfront_paid, DROP upfront_paid_at, DROP released_amount');
    }
}

