<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidated migration from GestionService merge.
 * Creates ticket/contract/service tables and adds certificate columns to users.
 * All CREATE TABLE statements are guarded to avoid "table already exists" errors.
 */
final class Version20260211023705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ticket, contract, milestone, service tables and add certificate columns (safe / idempotent)';
    }

    private function hasConstraint(string $tableName, string $constraintName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$tableName, $constraintName]
        );
        return $count > 0;
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tableName, $columnName]
        );
        return $count > 0;
    }

    public function up(Schema $schema): void
    {
        // ── Tables ──

        if (!$schema->hasTable('category_ticket')) {
            $this->addSql('CREATE TABLE category_ticket (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('contract')) {
            $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, scope LONGTEXT NOT NULL, agreed_price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, client_signed TINYINT DEFAULT 0 NOT NULL, client_signed_at DATETIME DEFAULT NULL, client_signature_ip VARCHAR(255) DEFAULT NULL, worker_signed TINYINT DEFAULT 0 NOT NULL, worker_signed_at DATETIME DEFAULT NULL, worker_signature_ip VARCHAR(255) DEFAULT NULL, client_signature_data LONGTEXT DEFAULT NULL, worker_signature_data LONGTEXT DEFAULT NULL, signed_pdf_path VARCHAR(255) DEFAULT NULL, cancellation_reason LONGTEXT DEFAULT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_E98F285919EB6921 (client_id), INDEX IDX_E98F28596B20BA36 (worker_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('milestone')) {
            $this->addSql('CREATE TABLE milestone (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, due_date DATE NOT NULL, order_index INT NOT NULL, status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, contract_id INT NOT NULL, INDEX IDX_4FAC83822576E0FD (contract_id), UNIQUE INDEX uq_milestone_order (contract_id, order_index), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('service_request')) {
            $this->addSql('CREATE TABLE service_request (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, budget_min NUMERIC(10, 2) NOT NULL, budget_max NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, duration INT NOT NULL, client_id INT DEFAULT NULL, category_id INT DEFAULT NULL, INDEX IDX_F413DD0319EB6921 (client_id), INDEX IDX_F413DD0312469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('service_requirement')) {
            $this->addSql('CREATE TABLE service_requirement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, details LONGTEXT NOT NULL, requirement_type VARCHAR(255) NOT NULL, answer_format VARCHAR(255) NOT NULL, options_json JSON NOT NULL, is_mandatory TINYINT NOT NULL, priority_level VARCHAR(255) NOT NULL, service_id INT NOT NULL, INDEX IDX_17A573FCED5CA9E6 (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('sub_ticket')) {
            $this->addSql('CREATE TABLE sub_ticket (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, sender_role VARCHAR(20) NOT NULL, is_internal TINYINT NOT NULL, is_read TINYINT NOT NULL, read_at DATETIME DEFAULT NULL, is_edited TINYINT NOT NULL, edited_at DATETIME DEFAULT NULL, is_deleted TINYINT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, file_type VARCHAR(50) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_25F1E2EF700047D2 (ticket_id), INDEX IDX_25F1E2EFF624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('ticket')) {
            $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, resolution VARCHAR(255) DEFAULT NULL, last_message_at DATETIME DEFAULT NULL, message_count INT NOT NULL, acknowledged_by_ad TINYINT NOT NULL, acknowledged_at DATETIME DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, satisfaction_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, created_by_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_97A0ADA3B03A8386 (created_by_id), INDEX IDX_97A0ADA312469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        // ── Foreign keys (only if missing) ──

        if (!$this->hasConstraint('contract', 'FK_E98F285919EB6921')) {
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F285919EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('contract', 'FK_E98F28596B20BA36')) {
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28596B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('milestone', 'FK_4FAC83822576E0FD')) {
            $this->addSql('ALTER TABLE milestone ADD CONSTRAINT FK_4FAC83822576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE CASCADE');
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
        if (!$this->hasConstraint('sub_ticket', 'FK_25F1E2EF700047D2')) {
            $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EF700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        }
        if (!$this->hasConstraint('sub_ticket', 'FK_25F1E2EFF624B39D')) {
            $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EFF624B39D FOREIGN KEY (sender_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('ticket', 'FK_97A0ADA3B03A8386')) {
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('ticket', 'FK_97A0ADA312469DE2')) {
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA312469DE2 FOREIGN KEY (category_id) REFERENCES category_ticket (id)');
        }

        // ── Drop legacy email_verification table if it exists ──
        if ($schema->hasTable('email_verification')) {
            $this->addSql('DROP TABLE email_verification');
        }

        // ── Add certificate columns to users (if not already present) ──
        if (!$this->hasColumn('users', 'certificate_path')) {
            $this->addSql('ALTER TABLE users ADD certificate_path VARCHAR(255) DEFAULT NULL, ADD certificate_ai_score INT DEFAULT NULL, ADD certificate_ai_verdict VARCHAR(20) DEFAULT NULL, ADD certificate_status VARCHAR(20) DEFAULT NULL, ADD certificate_uploaded_at DATETIME DEFAULT NULL, ADD certificate_approved_at DATETIME DEFAULT NULL, ADD certificate_review_note LONGTEXT DEFAULT NULL, ADD certificate_extracted_text LONGTEXT DEFAULT NULL, ADD email_verification_code VARCHAR(6) DEFAULT NULL, ADD email_verification_expires_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285919EB6921');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28596B20BA36');
        $this->addSql('ALTER TABLE milestone DROP FOREIGN KEY FK_4FAC83822576E0FD');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0319EB6921');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0312469DE2');
        $this->addSql('ALTER TABLE service_requirement DROP FOREIGN KEY FK_17A573FCED5CA9E6');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EF700047D2');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EFF624B39D');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA312469DE2');
        $this->addSql('DROP TABLE IF EXISTS category_ticket');
        $this->addSql('DROP TABLE IF EXISTS contract');
        $this->addSql('DROP TABLE IF EXISTS milestone');
        $this->addSql('DROP TABLE IF EXISTS service_request');
        $this->addSql('DROP TABLE IF EXISTS service_requirement');
        $this->addSql('DROP TABLE IF EXISTS sub_ticket');
        $this->addSql('DROP TABLE IF EXISTS ticket');
    }
}
