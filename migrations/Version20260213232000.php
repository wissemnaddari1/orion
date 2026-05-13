<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add ON DELETE CASCADE to offer.service_request_id FK (Offer -> ServiceRequest).
 * No other tables or columns changed.
 */
final class Version20260213232000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to offer.service_request_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873ED42F8111');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873ED42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873ED42F8111');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873ED42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id)');
    }
}
