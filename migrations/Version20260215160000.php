<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face_token and face_enrolled_at for Face++ facial login.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD face_token VARCHAR(255) DEFAULT NULL, ADD face_enrolled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP face_token, DROP face_enrolled_at');
    }
}
