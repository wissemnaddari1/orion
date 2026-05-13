<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206021543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
                // Keep the existing FK-related index to avoid MySQL error 1553.
        $this->addSql('ALTER TABLE users ADD certificate_path VARCHAR(255) DEFAULT NULL, ADD certificate_ai_score INT DEFAULT NULL, ADD certificate_ai_verdict VARCHAR(20) DEFAULT NULL, ADD certificate_status VARCHAR(20) DEFAULT NULL, ADD certificate_uploaded_at DATETIME DEFAULT NULL, ADD certificate_approved_at DATETIME DEFAULT NULL, ADD certificate_review_note LONGTEXT DEFAULT NULL, CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(255) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT NULL, CHANGE face_last_verified face_last_verified DATETIME DEFAULT NULL, CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT NULL, CHANGE face_locked_until face_locked_until DATETIME DEFAULT NULL, CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE timezone timezone VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_FE223940A76ED395 ON email_verification (user_id)');
        $this->addSql('ALTER TABLE users DROP certificate_path, DROP certificate_ai_score, DROP certificate_ai_verdict, DROP certificate_status, DROP certificate_uploaded_at, DROP certificate_approved_at, DROP certificate_review_note, CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE last_ip last_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT \'NULL\', CHANGE face_last_verified face_last_verified DATETIME DEFAULT \'NULL\', CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT \'NULL\', CHANGE face_locked_until face_locked_until DATETIME DEFAULT \'NULL\', CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'\'\'USD\'\'\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT \'NULL\', CHANGE country country VARCHAR(255) DEFAULT \'NULL\', CHANGE city city VARCHAR(255) DEFAULT \'NULL\', CHANGE timezone timezone VARCHAR(64) DEFAULT \'NULL\'');
    }
}
