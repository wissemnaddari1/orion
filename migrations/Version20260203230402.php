<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203230402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_verification (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(6) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_FE22358A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, phone VARCHAR(20) DEFAULT NULL, status VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, profile_picture VARCHAR(255) DEFAULT NULL, email_verified TINYINT DEFAULT 0 NOT NULL, phone_verified TINYINT DEFAULT 0 NOT NULL, two_factor_enabled TINYINT DEFAULT 0 NOT NULL, last_ip VARCHAR(255) DEFAULT NULL, last_login DATETIME DEFAULT NULL, face_image_path VARCHAR(255) DEFAULT NULL, face_embedding LONGBLOB DEFAULT NULL, face_last_verified DATETIME DEFAULT NULL, face_model_version VARCHAR(255) DEFAULT NULL, face_failed_attempts INT DEFAULT 0, face_locked_until DATETIME DEFAULT NULL, account_balance NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, rating_avg NUMERIC(3, 2) DEFAULT NULL, total_reviews INT DEFAULT 0, country VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, timezone VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_verification ADD CONSTRAINT FK_FE22358A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_verification DROP FOREIGN KEY FK_FE22358A76ED395');
        $this->addSql('DROP TABLE email_verification');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
