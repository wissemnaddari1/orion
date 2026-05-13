<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221200342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_ticket CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contract CHANGE currency currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, CHANGE client_signed_at client_signed_at DATETIME DEFAULT NULL, CHANGE client_signature_ip client_signature_ip VARCHAR(255) DEFAULT NULL, CHANGE worker_signed_at worker_signed_at DATETIME DEFAULT NULL, CHANGE worker_signature_ip worker_signature_ip VARCHAR(255) DEFAULT NULL, CHANGE signed_pdf_path signed_pdf_path VARCHAR(255) DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE cancelled_at cancelled_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE face_profiles CHANGE embedding embedding JSON NOT NULL, CHANGE last_matched_at last_matched_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE milestone CHANGE status status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL, CHANGE amount amount NUMERIC(10, 2) DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE negotiation CHANGE subject subject VARCHAR(255) DEFAULT NULL, CHANGE counter_price counter_price NUMERIC(10, 2) DEFAULT NULL, CHANGE extra_revision_fee extra_revision_fee NUMERIC(10, 2) DEFAULT NULL, CHANGE priority_level priority_level VARCHAR(20) DEFAULT NULL, CHANGE meeting_frequency meeting_frequency VARCHAR(20) DEFAULT NULL, CHANGE data_sensitivity_level data_sensitivity_level VARCHAR(20) DEFAULT NULL, CHANGE late_penalty_percent late_penalty_percent NUMERIC(5, 2) DEFAULT NULL, CHANGE expires_at expires_at DATETIME DEFAULT NULL, CHANGE last_action_at last_action_at DATETIME DEFAULT NULL');
        // notification + offer changes handled by Version20260221100000 (idempotent)
        $this->addSql('ALTER TABLE password_reset_tokens CHANGE used_at used_at DATETIME DEFAULT NULL');
        // RENAME INDEX not supported on this MariaDB version; index name left as-is (no functional impact)
        if ($this->hasColumn('service_request', 'freelancer_level')) {
            $this->addSql('ALTER TABLE service_request DROP freelancer_level');
        }
        $this->addSql('ALTER TABLE service_requirement CHANGE options_json options_json JSON NOT NULL');
        $this->addSql('ALTER TABLE sub_ticket CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE edited_at edited_at DATETIME DEFAULT NULL, CHANGE file_name file_name VARCHAR(255) DEFAULT NULL, CHANGE file_path file_path VARCHAR(255) DEFAULT NULL, CHANGE file_type file_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket CHANGE resolution resolution VARCHAR(255) DEFAULT NULL, CHANGE last_message_at last_message_at DATETIME DEFAULT NULL, CHANGE acknowledged_at acknowledged_at DATETIME DEFAULT NULL, CHANGE closed_at closed_at DATETIME DEFAULT NULL');
        if ($this->hasColumn('users', 'face_token')) {
            $this->addSql('ALTER TABLE users DROP face_token');
        }
        $this->addSql('ALTER TABLE users CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE last_ip last_ip VARCHAR(255) DEFAULT NULL, CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT NULL, CHANGE face_last_verified face_last_verified DATETIME DEFAULT NULL, CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT NULL, CHANGE face_locked_until face_locked_until DATETIME DEFAULT NULL, CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'USD\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE timezone timezone VARCHAR(64) DEFAULT NULL, CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT NULL, CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT NULL, CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT NULL, CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT NULL, CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT NULL, CHANGE email_verification_code email_verification_code VARCHAR(6) DEFAULT NULL, CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL, CHANGE login_locked_until login_locked_until DATETIME DEFAULT NULL, CHANGE last_failed_login_at last_failed_login_at DATETIME DEFAULT NULL, CHANGE banned_at banned_at DATETIME DEFAULT NULL, CHANGE ban_reason ban_reason VARCHAR(500) DEFAULT NULL, CHANGE face_enrolled_at face_enrolled_at DATETIME DEFAULT NULL, CHANGE two_factor_secret two_factor_secret VARCHAR(512) DEFAULT NULL, CHANGE two_factor_temp_secret two_factor_temp_secret VARCHAR(512) DEFAULT NULL, CHANGE two_factor_backup_codes two_factor_backup_codes JSON DEFAULT NULL, CHANGE two_factor_locked_until two_factor_locked_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_ticket CHANGE description description VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE contract CHANGE currency currency VARCHAR(3) DEFAULT \'\'\'USD\'\'\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'\'\'DRAFT\'\'\' NOT NULL, CHANGE client_signed_at client_signed_at DATETIME DEFAULT \'NULL\', CHANGE client_signature_ip client_signature_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE worker_signed_at worker_signed_at DATETIME DEFAULT \'NULL\', CHANGE worker_signature_ip worker_signature_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE signed_pdf_path signed_pdf_path VARCHAR(255) DEFAULT \'NULL\', CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\', CHANGE cancelled_at cancelled_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE face_profiles CHANGE embedding embedding LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE last_matched_at last_matched_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE milestone CHANGE status status VARCHAR(20) DEFAULT \'\'\'PENDING\'\'\' NOT NULL, CHANGE amount amount NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE negotiation CHANGE subject subject VARCHAR(255) DEFAULT \'NULL\', CHANGE counter_price counter_price NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE extra_revision_fee extra_revision_fee NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE priority_level priority_level VARCHAR(20) DEFAULT \'NULL\', CHANGE meeting_frequency meeting_frequency VARCHAR(20) DEFAULT \'NULL\', CHANGE data_sensitivity_level data_sensitivity_level VARCHAR(20) DEFAULT \'NULL\', CHANGE late_penalty_percent late_penalty_percent NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE expires_at expires_at DATETIME DEFAULT \'NULL\', CHANGE last_action_at last_action_at DATETIME DEFAULT \'NULL\'');
        // notification + offer rollback handled by Version20260221100000
        $this->addSql('ALTER TABLE password_reset_tokens CHANGE used_at used_at DATETIME DEFAULT \'NULL\'');
        // Index rename skipped in up(), so nothing to revert here
        if (!$this->hasColumn('service_request', 'freelancer_level')) {
            $this->addSql('ALTER TABLE service_request ADD freelancer_level VARCHAR(50) DEFAULT \'NULL\'');
        }
        $this->addSql('ALTER TABLE service_requirement CHANGE options_json options_json LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE sub_ticket CHANGE read_at read_at DATETIME DEFAULT \'NULL\', CHANGE edited_at edited_at DATETIME DEFAULT \'NULL\', CHANGE file_name file_name VARCHAR(255) DEFAULT \'NULL\', CHANGE file_path file_path VARCHAR(255) DEFAULT \'NULL\', CHANGE file_type file_type VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE ticket CHANGE resolution resolution VARCHAR(255) DEFAULT \'NULL\', CHANGE last_message_at last_message_at DATETIME DEFAULT \'NULL\', CHANGE acknowledged_at acknowledged_at DATETIME DEFAULT \'NULL\', CHANGE closed_at closed_at DATETIME DEFAULT \'NULL\'');
        if (!$this->hasColumn('users', 'face_token')) {
            $this->addSql('ALTER TABLE users ADD face_token VARCHAR(255) DEFAULT \'NULL\'');
        }
        $this->addSql('ALTER TABLE users CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE two_factor_secret two_factor_secret VARCHAR(512) DEFAULT \'NULL\', CHANGE two_factor_temp_secret two_factor_temp_secret VARCHAR(512) DEFAULT \'NULL\', CHANGE two_factor_backup_codes two_factor_backup_codes LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE two_factor_locked_until two_factor_locked_until DATETIME DEFAULT \'NULL\', CHANGE last_ip last_ip VARCHAR(255) DEFAULT \'NULL\', CHANGE last_login last_login DATETIME DEFAULT \'NULL\', CHANGE face_image_path face_image_path VARCHAR(255) DEFAULT \'NULL\', CHANGE face_last_verified face_last_verified DATETIME DEFAULT \'NULL\', CHANGE face_model_version face_model_version VARCHAR(255) DEFAULT \'NULL\', CHANGE face_locked_until face_locked_until DATETIME DEFAULT \'NULL\', CHANGE face_enrolled_at face_enrolled_at DATETIME DEFAULT \'NULL\', CHANGE login_locked_until login_locked_until DATETIME DEFAULT \'NULL\', CHANGE last_failed_login_at last_failed_login_at DATETIME DEFAULT \'NULL\', CHANGE banned_at banned_at DATETIME DEFAULT \'NULL\', CHANGE ban_reason ban_reason VARCHAR(500) DEFAULT \'NULL\', CHANGE wallet_currency wallet_currency VARCHAR(3) DEFAULT \'\'\'USD\'\'\' NOT NULL, CHANGE rating_avg rating_avg NUMERIC(3, 2) DEFAULT \'NULL\', CHANGE country country VARCHAR(255) DEFAULT \'NULL\', CHANGE city city VARCHAR(255) DEFAULT \'NULL\', CHANGE timezone timezone VARCHAR(64) DEFAULT \'NULL\', CHANGE certificate_path certificate_path VARCHAR(255) DEFAULT \'NULL\', CHANGE certificate_ai_verdict certificate_ai_verdict VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_status certificate_status VARCHAR(20) DEFAULT \'NULL\', CHANGE certificate_uploaded_at certificate_uploaded_at DATETIME DEFAULT \'NULL\', CHANGE certificate_approved_at certificate_approved_at DATETIME DEFAULT \'NULL\', CHANGE email_verification_code email_verification_code VARCHAR(6) DEFAULT \'NULL\', CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT \'NULL\'');
    }
}
