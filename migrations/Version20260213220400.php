<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates negotiation, offer, and ensures service/worker tables exist (from GestionService merge).
 * All CREATE TABLE statements are guarded to avoid "table already exists" errors.
 */
final class Version20260213220400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create negotiation, offer tables and sync schema (safe / idempotent)';
    }

    private function hasConstraint(string $tableName, string $constraintName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$tableName, $constraintName]
        );
        return $count > 0;
    }

    public function up(Schema $schema): void
    {
        // ── New tables (negotiation, offer) ──

        if (!$schema->hasTable('negotiation')) {
            $this->addSql('CREATE TABLE negotiation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, counter_price NUMERIC(10, 2) DEFAULT NULL, timeline_days INT DEFAULT NULL, scope_details LONGTEXT DEFAULT NULL, deliverables_list LONGTEXT DEFAULT NULL, acceptance_criteria LONGTEXT DEFAULT NULL, included_revisions INT DEFAULT NULL, extra_revision_fee NUMERIC(10, 2) DEFAULT NULL, priority_level VARCHAR(20) DEFAULT NULL, meeting_frequency VARCHAR(20) DEFAULT NULL, nda_required TINYINT NOT NULL, data_sensitivity_level VARCHAR(20) DEFAULT NULL, late_penalty_percent NUMERIC(5, 2) DEFAULT NULL, expires_at DATETIME DEFAULT NULL, last_action_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, offer_id INT NOT NULL, opened_by_id INT NOT NULL, target_user_id INT NOT NULL, INDEX IDX_1798959853C674EE (offer_id), INDEX IDX_17989598AB159F5 (opened_by_id), INDEX IDX_179895986C066AFE (target_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('offer')) {
            $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, price NUMERIC(10, 2) NOT NULL, estimated_time_days INT NOT NULL, message LONGTEXT DEFAULT NULL, scope_summary LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, deliverables LONGTEXT DEFAULT NULL, acceptance_criteria LONGTEXT DEFAULT NULL, included_revisions INT NOT NULL, extra_revision_fee NUMERIC(10, 2) DEFAULT NULL, response_sla_hours INT DEFAULT NULL, start_date_available DATE DEFAULT NULL, delivery_date_estimated DATE DEFAULT NULL, priority_level VARCHAR(20) NOT NULL, is_urgent TINYINT NOT NULL, rush_fee NUMERIC(10, 2) DEFAULT NULL, service_request_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_29D6873ED42F8111 (service_request_id), INDEX IDX_29D6873E6B20BA36 (worker_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        // ── Tables that may already exist from earlier migrations ──

        if (!$schema->hasTable('service_request')) {
            $this->addSql('CREATE TABLE service_request (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, budget_min NUMERIC(10, 2) NOT NULL, budget_max NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, duration INT NOT NULL, client_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_F413DD0319EB6921 (client_id), INDEX IDX_F413DD0312469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('service_requirement')) {
            $this->addSql('CREATE TABLE service_requirement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, details LONGTEXT NOT NULL, requirement_type VARCHAR(255) NOT NULL, answer_format VARCHAR(255) NOT NULL, options_json JSON NOT NULL, is_mandatory TINYINT NOT NULL, priority_level INT NOT NULL, service_id INT NOT NULL, INDEX IDX_17A573FCED5CA9E6 (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('worker_category')) {
            $this->addSql('CREATE TABLE worker_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, display_order INT NOT NULL, total_workers INT NOT NULL, icon VARCHAR(255) NOT NULL, average_hourly_rate NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('worker_profile')) {
            $this->addSql('CREATE TABLE worker_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, bio LONGTEXT NOT NULL, hourly_rate VARCHAR(255) NOT NULL, experience_years INT NOT NULL, location VARCHAR(255) NOT NULL, verified TINYINT NOT NULL, availability_status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, worker_category_id INT DEFAULT NULL, INDEX IDX_B5B8D142DBCE8125 (worker_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        // ── Foreign keys (only if missing) ──

        if (!$this->hasConstraint('negotiation', 'FK_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        }
        if (!$this->hasConstraint('negotiation', 'FK_17989598AB159F5')) {
            $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598AB159F5 FOREIGN KEY (opened_by_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('negotiation', 'FK_179895986C066AFE')) {
            $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_179895986C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873ED42F8111')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873ED42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id)');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873E6B20BA36')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E6B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('service_request', 'FK_F413DD0319EB6921')) {
            $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0319EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('service_request', 'FK_F413DD0312469DE2')) {
            $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0312469DE2 FOREIGN KEY (category_id) REFERENCES worker_category (id)');
        }
        if (!$this->hasConstraint('service_requirement', 'FK_17A573FCED5CA9E6')) {
            $this->addSql('ALTER TABLE service_requirement ADD CONSTRAINT FK_17A573FCED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_request (id)');
        }
        if (!$this->hasConstraint('worker_profile', 'FK_B5B8D142DBCE8125')) {
            $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142DBCE8125 FOREIGN KEY (worker_category_id) REFERENCES worker_category (id)');
        }

        // ── Normalize existing column defaults (idempotent CHANGE statements) ──

        $this->addSql('ALTER TABLE category_ticket CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contract CHANGE currency currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, CHANGE client_signed_at client_signed_at DATETIME DEFAULT NULL, CHANGE client_signature_ip client_signature_ip VARCHAR(255) DEFAULT NULL, CHANGE worker_signed_at worker_signed_at DATETIME DEFAULT NULL, CHANGE worker_signature_ip worker_signature_ip VARCHAR(255) DEFAULT NULL, CHANGE signed_pdf_path signed_pdf_path VARCHAR(255) DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE cancelled_at cancelled_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE milestone CHANGE status status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, CHANGE amount amount NUMERIC(10, 2) DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_ticket CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE edited_at edited_at DATETIME DEFAULT NULL, CHANGE file_name file_name VARCHAR(255) DEFAULT NULL, CHANGE file_path file_path VARCHAR(255) DEFAULT NULL, CHANGE file_type file_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket CHANGE resolution resolution VARCHAR(255) DEFAULT NULL, CHANGE last_message_at last_message_at DATETIME DEFAULT NULL, CHANGE acknowledged_at acknowledged_at DATETIME DEFAULT NULL, CHANGE closed_at closed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(255) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT NULL, CHANGE face_last_verified face_last_verified DATETIME DEFAULT NULL, CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT NULL, CHANGE face_locked_until face_locked_until DATETIME DEFAULT NULL, CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE timezone timezone VARCHAR(64) DEFAULT NULL, CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT NULL, CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT NULL, CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT NULL, CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT NULL, CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT NULL, CHANGE email_verification_code email_verification_code VARCHAR(6) DEFAULT NULL, CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598AB159F5');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_179895986C066AFE');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873ED42F8111');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E6B20BA36');
        $this->addSql('DROP TABLE IF EXISTS negotiation');
        $this->addSql('DROP TABLE IF EXISTS offer');
        $this->addSql('DROP TABLE IF EXISTS service_request');
        $this->addSql('DROP TABLE IF EXISTS service_requirement');
        $this->addSql('DROP TABLE IF EXISTS worker_category');
        $this->addSql('DROP TABLE IF EXISTS worker_profile');
    }
}
