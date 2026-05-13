<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206195657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Only drop email_verification table if it exists (table may not exist if DB was built from different migration path)
        $hasEmailVerification = (bool) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification'");
        if ($hasEmailVerification) {
            $this->addSql('ALTER TABLE email_verification DROP FOREIGN KEY `FK_FE22358A76ED395`');
            $this->addSql('DROP TABLE email_verification');
        }

        $this->addSql('ALTER TABLE users ADD certificate_extracted_text LONGTEXT DEFAULT NULL, ADD email_verification_code VARCHAR(6) DEFAULT NULL, ADD email_verification_expires_at DATETIME DEFAULT NULL, CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(255) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT NULL, CHANGE face_last_verified face_last_verified DATETIME DEFAULT NULL, CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT NULL, CHANGE face_locked_until face_locked_until DATETIME DEFAULT NULL, CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE timezone timezone VARCHAR(64) DEFAULT NULL, CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT NULL, CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT NULL, CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT NULL, CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT NULL, CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_verification (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(6) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, expires_at DATETIME NOT NULL, used TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_FE22358A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE email_verification ADD CONSTRAINT `FK_FE22358A76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users DROP certificate_extracted_text, DROP email_verification_code, DROP email_verification_expires_at, CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE last_ip last_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT \'NULL\', CHANGE face_last_verified face_last_verified DATETIME DEFAULT \'NULL\', CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT \'NULL\', CHANGE face_locked_until face_locked_until DATETIME DEFAULT \'NULL\', CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'\'\'USD\'\'\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT \'NULL\', CHANGE country country VARCHAR(255) DEFAULT \'NULL\', CHANGE city city VARCHAR(255) DEFAULT \'NULL\', CHANGE timezone timezone VARCHAR(64) DEFAULT \'NULL\', CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT \'NULL\', CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT \'NULL\', CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT \'NULL\'');
    }
}
