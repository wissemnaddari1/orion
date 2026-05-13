<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227043407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function hasTable(string $tableName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        ) > 0;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Skip full schema create if tables already exist (e.g. applied by earlier migrations)
        if ($this->hasTable('category_ticket')) {
            return;
        }
        $this->addSql('CREATE TABLE category_ticket (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, scope LONGTEXT NOT NULL, agreed_price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, upfront_percent NUMERIC(5, 2) DEFAULT \'30.00\' NOT NULL, upfront_paid TINYINT DEFAULT 0 NOT NULL, upfront_paid_at DATETIME DEFAULT NULL, released_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, client_signed TINYINT DEFAULT 0 NOT NULL, client_signed_at DATETIME DEFAULT NULL, client_signature_ip VARCHAR(255) DEFAULT NULL, worker_signed TINYINT DEFAULT 0 NOT NULL, worker_signed_at DATETIME DEFAULT NULL, worker_signature_ip VARCHAR(255) DEFAULT NULL, client_signature_data LONGTEXT DEFAULT NULL, worker_signature_data LONGTEXT DEFAULT NULL, signed_pdf_path VARCHAR(255) DEFAULT NULL, risk_score DOUBLE PRECISION DEFAULT NULL, risk_level VARCHAR(10) DEFAULT NULL, cancellation_reason LONGTEXT DEFAULT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_E98F285919EB6921 (client_id), INDEX IDX_E98F28596B20BA36 (worker_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE face_profiles (id INT AUTO_INCREMENT NOT NULL, embedding JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_matched_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_AF948895A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE milestone (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, due_date DATE NOT NULL, order_index INT NOT NULL, status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, contract_id INT NOT NULL, INDEX IDX_4FAC83822576E0FD (contract_id), UNIQUE INDEX uq_milestone_order (contract_id, order_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE negotiation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, counter_price NUMERIC(10, 2) DEFAULT NULL, timeline_days INT DEFAULT NULL, scope_details LONGTEXT DEFAULT NULL, deliverables_list LONGTEXT DEFAULT NULL, acceptance_criteria LONGTEXT DEFAULT NULL, included_revisions INT DEFAULT NULL, extra_revision_fee NUMERIC(10, 2) DEFAULT NULL, priority_level VARCHAR(20) DEFAULT NULL, meeting_frequency VARCHAR(20) DEFAULT NULL, nda_required TINYINT NOT NULL, data_sensitivity_level VARCHAR(20) DEFAULT NULL, late_penalty_percent NUMERIC(5, 2) DEFAULT NULL, expires_at DATETIME DEFAULT NULL, last_action_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, offer_id INT NOT NULL, opened_by_id INT NOT NULL, target_user_id INT NOT NULL, UNIQUE INDEX UNIQ_1798959853C674EE (offer_id), INDEX IDX_17989598AB159F5 (opened_by_id), INDEX IDX_179895986C066AFE (target_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, payload JSON DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_notification_user_read_created (user_id, is_read, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, price NUMERIC(10, 2) NOT NULL, estimated_time_days INT NOT NULL, message LONGTEXT DEFAULT NULL, scope_summary LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, match_score DOUBLE PRECISION DEFAULT NULL, proposed_budget NUMERIC(10, 2) DEFAULT NULL, proposed_deadline DATETIME DEFAULT NULL, deliverables LONGTEXT DEFAULT NULL, acceptance_criteria LONGTEXT DEFAULT NULL, included_revisions INT NOT NULL, extra_revision_fee NUMERIC(10, 2) DEFAULT NULL, response_sla_hours INT DEFAULT NULL, start_date_available DATE DEFAULT NULL, delivery_date_estimated DATE DEFAULT NULL, priority_level VARCHAR(20) NOT NULL, is_urgent TINYINT NOT NULL, rush_fee NUMERIC(10, 2) DEFAULT NULL, service_request_id INT NOT NULL, worker_id INT NOT NULL, client_id INT DEFAULT NULL, INDEX IDX_29D6873ED42F8111 (service_request_id), INDEX IDX_29D6873E6B20BA36 (worker_id), INDEX IDX_29D6873E19EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_3967A216A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_request (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, budget_min NUMERIC(10, 2) NOT NULL, budget_max NUMERIC(10, 2) NOT NULL, status VARCHAR(50) DEFAULT \'OPEN\' NOT NULL, created_at DATETIME NOT NULL, duration INT NOT NULL, level VARCHAR(50) DEFAULT \'Entry\' NOT NULL, client_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_F413DD0319EB6921 (client_id), INDEX IDX_F413DD0312469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_requirement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, details LONGTEXT NOT NULL, requirement_type VARCHAR(255) NOT NULL, answer_format VARCHAR(255) NOT NULL, options_json JSON NOT NULL, is_mandatory TINYINT NOT NULL, priority_level INT NOT NULL, service_id INT NOT NULL, INDEX IDX_17A573FCED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sub_ticket (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, sender_role VARCHAR(20) NOT NULL, is_internal TINYINT NOT NULL, is_read TINYINT NOT NULL, read_at DATETIME DEFAULT NULL, is_edited TINYINT NOT NULL, edited_at DATETIME DEFAULT NULL, is_deleted TINYINT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, file_type VARCHAR(50) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_25F1E2EF700047D2 (ticket_id), INDEX IDX_25F1E2EFF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, resolution VARCHAR(255) DEFAULT NULL, last_message_at DATETIME DEFAULT NULL, message_count INT NOT NULL, acknowledged_by_ad TINYINT NOT NULL, acknowledged_at DATETIME DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, satisfaction_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, ai_sentiment VARCHAR(16) DEFAULT NULL, ai_urgency VARCHAR(16) DEFAULT NULL, ai_suggested_priority VARCHAR(16) DEFAULT NULL, ai_summary LONGTEXT DEFAULT NULL, created_by_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_97A0ADA3B03A8386 (created_by_id), INDEX IDX_97A0ADA312469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_ban (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, note LONGTEXT DEFAULT NULL, banned_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, lifted_at DATETIME DEFAULT NULL, lift_reason LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, type VARCHAR(10) NOT NULL, user_id INT NOT NULL, banned_by_id INT DEFAULT NULL, INDEX IDX_89E8B16E386B8E7 (banned_by_id), INDEX idx_user_ban_user_id (user_id), INDEX idx_user_ban_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, phone VARCHAR(20) DEFAULT NULL, status VARCHAR(20) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, profile_picture VARCHAR(255) DEFAULT NULL, email_verified TINYINT DEFAULT 0 NOT NULL, phone_verified TINYINT DEFAULT 0 NOT NULL, two_factor_enabled TINYINT DEFAULT 0 NOT NULL, two_factor_secret VARCHAR(512) DEFAULT NULL, two_factor_temp_secret VARCHAR(512) DEFAULT NULL, two_factor_backup_codes JSON DEFAULT NULL, two_factor_failed_attempts INT DEFAULT 0 NOT NULL, two_factor_locked_until DATETIME DEFAULT NULL, last_ip VARCHAR(255) DEFAULT NULL, last_login DATETIME DEFAULT NULL, face_image_path VARCHAR(255) DEFAULT NULL, face_embedding LONGBLOB DEFAULT NULL, face_last_verified DATETIME DEFAULT NULL, face_model_version VARCHAR(255) DEFAULT NULL, face_failed_attempts INT DEFAULT 0, face_locked_until DATETIME DEFAULT NULL, face_enrolled_at DATETIME DEFAULT NULL, failed_login_attempts INT DEFAULT 0 NOT NULL, login_locked_until DATETIME DEFAULT NULL, last_failed_login_at DATETIME DEFAULT NULL, is_banned TINYINT DEFAULT 0 NOT NULL, ban_reason LONGTEXT DEFAULT NULL, ban_note LONGTEXT DEFAULT NULL, banned_at DATETIME DEFAULT NULL, ban_ends_at DATETIME DEFAULT NULL, ban_type VARCHAR(10) DEFAULT NULL, ban_count INT DEFAULT 0 NOT NULL, account_balance NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, rating_avg NUMERIC(3, 2) DEFAULT NULL, total_reviews INT DEFAULT 0, country VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, timezone VARCHAR(64) DEFAULT NULL, certificate_path VARCHAR(255) DEFAULT NULL, certificate_ai_score INT DEFAULT NULL, certificate_ai_verdict VARCHAR(20) DEFAULT NULL, certificate_status VARCHAR(20) DEFAULT NULL, certificate_uploaded_at DATETIME DEFAULT NULL, certificate_approved_at DATETIME DEFAULT NULL, certificate_review_note LONGTEXT DEFAULT NULL, certificate_extracted_text LONGTEXT DEFAULT NULL, email_verification_code VARCHAR(6) DEFAULT NULL, email_verification_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, banned_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), INDEX IDX_1483A5E9386B8E7 (banned_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, display_order INT NOT NULL, total_workers INT NOT NULL, icon VARCHAR(255) NOT NULL, average_hourly_rate NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, bio LONGTEXT NOT NULL, hourly_rate VARCHAR(255) NOT NULL, experience_years INT NOT NULL, location VARCHAR(255) NOT NULL, verified TINYINT NOT NULL, availability_status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, worker_category_id INT DEFAULT NULL, INDEX IDX_B5B8D142DBCE8125 (worker_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F285919EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28596B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE face_profiles ADD CONSTRAINT FK_AF948895A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE milestone ADD CONSTRAINT FK_4FAC83822576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598AB159F5 FOREIGN KEY (opened_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_179895986C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873ED42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E6B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E19EB6921 FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0319EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0312469DE2 FOREIGN KEY (category_id) REFERENCES worker_category (id)');
        $this->addSql('ALTER TABLE service_requirement ADD CONSTRAINT FK_17A573FCED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_request (id)');
        $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EF700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EFF624B39D FOREIGN KEY (sender_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA312469DE2 FOREIGN KEY (category_id) REFERENCES category_ticket (id)');
        $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16E386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142DBCE8125 FOREIGN KEY (worker_category_id) REFERENCES worker_category (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Only drop schema if this migration created it (tables exist)
        if (!$this->hasTable('category_ticket')) {
            return;
        }
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285919EB6921');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28596B20BA36');
        $this->addSql('ALTER TABLE face_profiles DROP FOREIGN KEY FK_AF948895A76ED395');
        $this->addSql('ALTER TABLE milestone DROP FOREIGN KEY FK_4FAC83822576E0FD');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598AB159F5');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_179895986C066AFE');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873ED42F8111');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E6B20BA36');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E19EB6921');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0319EB6921');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0312469DE2');
        $this->addSql('ALTER TABLE service_requirement DROP FOREIGN KEY FK_17A573FCED5CA9E6');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EF700047D2');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EFF624B39D');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA312469DE2');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16EA76ED395');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16E386B8E7');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9386B8E7');
        $this->addSql('ALTER TABLE worker_profile DROP FOREIGN KEY FK_B5B8D142DBCE8125');
        $this->addSql('DROP TABLE category_ticket');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE face_profiles');
        $this->addSql('DROP TABLE milestone');
        $this->addSql('DROP TABLE negotiation');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE service_request');
        $this->addSql('DROP TABLE service_requirement');
        $this->addSql('DROP TABLE sub_ticket');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE user_ban');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE worker_category');
        $this->addSql('DROP TABLE worker_profile');
    }
}
