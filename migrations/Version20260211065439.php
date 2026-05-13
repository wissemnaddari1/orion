<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211065439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // This migration was auto-generated, but in dev it can be common for a few tables
        // to already exist (e.g. created via schema:update or imported dump). Make it safe to run.

        $hasTable = function (string $tableName): bool {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$tableName]
            );

            return $count > 0;
        };

        $hasConstraint = function (string $tableName, string $constraintName): bool {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
                [$tableName, $constraintName]
            );

            return $count > 0;
        };

        if (!$hasTable('category_ticket')) {
            $this->addSql('CREATE TABLE category_ticket (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$hasTable('contract')) {
            $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, scope LONGTEXT NOT NULL, agreed_price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, client_signed TINYINT DEFAULT 0 NOT NULL, client_signed_at DATETIME DEFAULT NULL, client_signature_ip VARCHAR(255) DEFAULT NULL, worker_signed TINYINT DEFAULT 0 NOT NULL, worker_signed_at DATETIME DEFAULT NULL, worker_signature_ip VARCHAR(255) DEFAULT NULL, client_signature_data LONGTEXT DEFAULT NULL, worker_signature_data LONGTEXT DEFAULT NULL, signed_pdf_path VARCHAR(255) DEFAULT NULL, cancellation_reason LONGTEXT DEFAULT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_E98F285919EB6921 (client_id), INDEX IDX_E98F28596B20BA36 (worker_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$hasTable('milestone')) {
            $this->addSql('CREATE TABLE milestone (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, due_date DATE NOT NULL, order_index INT NOT NULL, status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, contract_id INT NOT NULL, INDEX IDX_4FAC83822576E0FD (contract_id), UNIQUE INDEX uq_milestone_order (contract_id, order_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$hasTable('sub_ticket')) {
            $this->addSql('CREATE TABLE sub_ticket (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, sender_role VARCHAR(20) NOT NULL, is_internal TINYINT NOT NULL, is_read TINYINT NOT NULL, read_at DATETIME DEFAULT NULL, is_edited TINYINT NOT NULL, edited_at DATETIME DEFAULT NULL, is_deleted TINYINT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, file_type VARCHAR(50) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_25F1E2EF700047D2 (ticket_id), INDEX IDX_25F1E2EFF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$hasTable('ticket')) {
            $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, resolution VARCHAR(255) DEFAULT NULL, last_message_at DATETIME DEFAULT NULL, message_count INT NOT NULL, acknowledged_by_ad TINYINT NOT NULL, acknowledged_at DATETIME DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, satisfaction_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, created_by_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_97A0ADA3B03A8386 (created_by_id), INDEX IDX_97A0ADA312469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$hasConstraint('contract', 'FK_E98F285919EB6921')) {
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F285919EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        }
        if (!$hasConstraint('contract', 'FK_E98F28596B20BA36')) {
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28596B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        }
        if (!$hasConstraint('milestone', 'FK_4FAC83822576E0FD')) {
            $this->addSql('ALTER TABLE milestone ADD CONSTRAINT FK_4FAC83822576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE CASCADE');
        }

        if (!$hasConstraint('sub_ticket', 'FK_25F1E2EF700047D2')) {
            $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EF700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        }
        if (!$hasConstraint('sub_ticket', 'FK_25F1E2EFF624B39D')) {
            $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EFF624B39D FOREIGN KEY (sender_id) REFERENCES users (id)');
        }

        if (!$hasConstraint('ticket', 'FK_97A0ADA3B03A8386')) {
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        }
        if (!$hasConstraint('ticket', 'FK_97A0ADA312469DE2')) {
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA312469DE2 FOREIGN KEY (category_id) REFERENCES category_ticket (id)');
        }

        // Keep the users table in sync with the current entity metadata.
        $this->addSql('ALTER TABLE users CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(255) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT NULL, CHANGE face_last_verified face_last_verified DATETIME DEFAULT NULL, CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT NULL, CHANGE face_locked_until face_locked_until DATETIME DEFAULT NULL, CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE timezone timezone VARCHAR(64) DEFAULT NULL, CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT NULL, CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT NULL, CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT NULL, CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT NULL, CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT NULL, CHANGE email_verification_code email_verification_code VARCHAR(6) DEFAULT NULL, CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285919EB6921');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28596B20BA36');
        $this->addSql('ALTER TABLE milestone DROP FOREIGN KEY FK_4FAC83822576E0FD');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EF700047D2');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EFF624B39D');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA312469DE2');
        $this->addSql('DROP TABLE category_ticket');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE milestone');
        $this->addSql('DROP TABLE sub_ticket');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('ALTER TABLE users CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE last_ip last_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT \'NULL\', CHANGE face_last_verified face_last_verified DATETIME DEFAULT \'NULL\', CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT \'NULL\', CHANGE face_locked_until face_locked_until DATETIME DEFAULT \'NULL\', CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'\'\'USD\'\'\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT \'NULL\', CHANGE country country VARCHAR(255) DEFAULT \'NULL\', CHANGE city city VARCHAR(255) DEFAULT \'NULL\', CHANGE timezone timezone VARCHAR(64) DEFAULT \'NULL\', CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT \'NULL\', CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT \'NULL\', CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT \'NULL\', CHANGE email_verification_code email_verification_code VARCHAR(6) DEFAULT \'NULL\', CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT \'NULL\'');
    }
}
