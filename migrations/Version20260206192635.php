<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206192635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $hasUsers = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'"
        );

        if (! $hasUsers) {
            $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, phone VARCHAR(20) DEFAULT NULL, status VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, profile_picture VARCHAR(255) DEFAULT NULL, email_verified TINYINT DEFAULT 0 NOT NULL, phone_verified TINYINT DEFAULT 0 NOT NULL, two_factor_enabled TINYINT DEFAULT 0 NOT NULL, last_ip VARCHAR(255) DEFAULT NULL, last_login DATETIME DEFAULT NULL, face_image_path VARCHAR(255) DEFAULT NULL, face_embedding LONGBLOB DEFAULT NULL, face_last_verified DATETIME DEFAULT NULL, face_model_version VARCHAR(255) DEFAULT NULL, face_failed_attempts INT DEFAULT 0, face_locked_until DATETIME DEFAULT NULL, account_balance NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, rating_avg NUMERIC(3, 2) DEFAULT NULL, total_reviews INT DEFAULT 0, country VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, timezone VARCHAR(64) DEFAULT NULL, certificate_path VARCHAR(255) DEFAULT NULL, certificate_ai_score INT DEFAULT NULL, certificate_ai_verdict VARCHAR(20) DEFAULT NULL, certificate_status VARCHAR(20) DEFAULT NULL, certificate_uploaded_at DATETIME DEFAULT NULL, certificate_approved_at DATETIME DEFAULT NULL, certificate_review_note LONGTEXT DEFAULT NULL, certificate_extracted_text LONGTEXT DEFAULT NULL, email_verification_code VARCHAR(6) DEFAULT NULL, email_verification_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE users');
    }
}
