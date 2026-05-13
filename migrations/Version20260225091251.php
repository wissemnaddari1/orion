<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225091251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function hasTable(string $tableName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );
        return $count > 0;
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

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
        return $count > 0;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if (!$this->hasTable('face_profiles')) {
            $this->addSql('CREATE TABLE face_profiles (id INT AUTO_INCREMENT NOT NULL, embedding JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_matched_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_AF948895A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasConstraint('face_profiles', 'FK_AF948895A76ED395')) {
            $this->addSql('ALTER TABLE face_profiles ADD CONSTRAINT FK_AF948895A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasTable('notification')) {
            $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, payload JSON DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_notification_user_read_created (user_id, is_read, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasTable('password_reset_tokens')) {
            $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_3967A216A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasTable('user_ban')) {
            $this->addSql('CREATE TABLE user_ban (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, note LONGTEXT DEFAULT NULL, banned_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, lifted_at DATETIME DEFAULT NULL, lift_reason LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, type VARCHAR(10) NOT NULL, user_id INT NOT NULL, banned_by_id INT DEFAULT NULL, INDEX IDX_89E8B16E386B8E7 (banned_by_id), INDEX idx_user_ban_user_id (user_id), INDEX idx_user_ban_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasConstraint('notification', 'FK_BF5476CAA76ED395')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('password_reset_tokens', 'FK_3967A216A76ED395')) {
            $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('user_ban', 'FK_89E8B16EA76ED395')) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('user_ban', 'FK_89E8B16E386B8E7')) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16E386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }
        if ($this->hasTable('ml_offer_training')) {
            $this->addSql('DROP TABLE ml_offer_training');
        }
        if (!$this->hasColumn('contract', 'upfront_percent')) {
            $this->addSql('ALTER TABLE contract ADD upfront_percent NUMERIC(5, 2) DEFAULT \'30.00\' NOT NULL, ADD upfront_paid TINYINT DEFAULT 0 NOT NULL, ADD upfront_paid_at DATETIME DEFAULT NULL, ADD released_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, ADD risk_score DOUBLE PRECISION DEFAULT NULL, ADD risk_level VARCHAR(10) DEFAULT NULL');
        }
        if (!$this->hasColumn('milestone', 'delivered_at')) {
            $this->addSql('ALTER TABLE milestone ADD delivered_at DATETIME DEFAULT NULL');
        }
        if (!$this->hasColumn('offer', 'updated_at')) {
            $this->addSql('ALTER TABLE offer ADD updated_at DATETIME DEFAULT NULL, ADD match_score DOUBLE PRECISION DEFAULT NULL, ADD proposed_budget NUMERIC(10, 2) DEFAULT NULL, ADD proposed_deadline DATETIME DEFAULT NULL, ADD client_id INT DEFAULT NULL');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873E19EB6921')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E19EB6921 FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasIndex('offer', 'IDX_29D6873E19EB6921')) {
            $this->addSql('CREATE INDEX IDX_29D6873E19EB6921 ON offer (client_id)');
        }
        if (!$this->hasColumn('users', 'two_factor_secret')) {
            $this->addSql('ALTER TABLE users ADD two_factor_secret VARCHAR(512) DEFAULT NULL, ADD two_factor_temp_secret VARCHAR(512) DEFAULT NULL, ADD two_factor_backup_codes JSON DEFAULT NULL, ADD two_factor_failed_attempts INT DEFAULT 0 NOT NULL, ADD two_factor_locked_until DATETIME DEFAULT NULL, ADD face_enrolled_at DATETIME DEFAULT NULL, ADD failed_login_attempts INT DEFAULT 0 NOT NULL, ADD login_locked_until DATETIME DEFAULT NULL, ADD last_failed_login_at DATETIME DEFAULT NULL, ADD is_banned TINYINT DEFAULT 0 NOT NULL, ADD ban_reason LONGTEXT DEFAULT NULL, ADD ban_note LONGTEXT DEFAULT NULL, ADD banned_at DATETIME DEFAULT NULL, ADD ban_ends_at DATETIME DEFAULT NULL, ADD ban_type VARCHAR(10) DEFAULT NULL, ADD ban_count INT DEFAULT 0 NOT NULL, ADD banned_by_id INT DEFAULT NULL');
        }
        if (!$this->hasConstraint('users', 'FK_1483A5E9386B8E7')) {
            $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }
        if (!$this->hasIndex('users', 'IDX_1483A5E9386B8E7')) {
            $this->addSql('CREATE INDEX IDX_1483A5E9386B8E7 ON users (banned_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ml_offer_training (id INT AUTO_INCREMENT NOT NULL, offer_id INT DEFAULT NULL, service_request_id INT DEFAULT NULL, worker_id INT DEFAULT NULL, price_ratio NUMERIC(10, 4) DEFAULT NULL COMMENT \'offer.price / budget_max\', budget_position NUMERIC(10, 4) DEFAULT NULL COMMENT \'(price - budget_min) / (budget_max - budget_min)\', message_length INT DEFAULT NULL, deliverables_length INT DEFAULT NULL, has_deliverables TINYINT DEFAULT NULL, timeline_ratio NUMERIC(10, 4) DEFAULT NULL COMMENT \'estimated_days / request_duration\', included_revisions INT DEFAULT NULL, worker_rating_avg NUMERIC(3, 2) DEFAULT NULL, total_reviews INT DEFAULT NULL, category_id INT DEFAULT NULL, is_urgent TINYINT DEFAULT 0, priority_level INT DEFAULT 2, is_accepted TINYINT DEFAULT NULL, source_type ENUM(\'synthetic\', \'real\') CHARACTER SET utf8mb4 DEFAULT \'synthetic\' COLLATE `utf8mb4_general_ci`, model_version VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, predicted_probability NUMERIC(5, 4) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_source_type (source_type), INDEX idx_created_at (created_at), INDEX idx_offer_id (offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        if ($this->hasTable('face_profiles')) {
            if ($this->hasConstraint('face_profiles', 'FK_AF948895A76ED395')) {
                $this->addSql('ALTER TABLE face_profiles DROP FOREIGN KEY FK_AF948895A76ED395');
            }
            $this->addSql('DROP TABLE face_profiles');
        }
        if ($this->hasTable('notification')) {
            if ($this->hasConstraint('notification', 'FK_BF5476CAA76ED395')) {
                $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
            }
            $this->addSql('DROP TABLE notification');
        }
        if ($this->hasTable('password_reset_tokens')) {
            if ($this->hasConstraint('password_reset_tokens', 'FK_3967A216A76ED395')) {
                $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
            }
            $this->addSql('DROP TABLE password_reset_tokens');
        }
        if ($this->hasTable('user_ban')) {
            if ($this->hasConstraint('user_ban', 'FK_89E8B16EA76ED395')) {
                $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16EA76ED395');
            }
            if ($this->hasConstraint('user_ban', 'FK_89E8B16E386B8E7')) {
                $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16E386B8E7');
            }
            $this->addSql('DROP TABLE user_ban');
        }
        $this->addSql('ALTER TABLE contract DROP upfront_percent, DROP upfront_paid, DROP upfront_paid_at, DROP released_amount, DROP risk_score, DROP risk_level');
        $this->addSql('ALTER TABLE milestone DROP delivered_at');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E19EB6921');
        $this->addSql('DROP INDEX IDX_29D6873E19EB6921 ON offer');
        $this->addSql('ALTER TABLE offer DROP updated_at, DROP match_score, DROP proposed_budget, DROP proposed_deadline, DROP client_id');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9386B8E7');
        $this->addSql('DROP INDEX IDX_1483A5E9386B8E7 ON users');
        $this->addSql('ALTER TABLE users DROP two_factor_secret, DROP two_factor_temp_secret, DROP two_factor_backup_codes, DROP two_factor_failed_attempts, DROP two_factor_locked_until, DROP face_enrolled_at, DROP failed_login_attempts, DROP login_locked_until, DROP last_failed_login_at, DROP is_banned, DROP ban_reason, DROP ban_note, DROP banned_at, DROP ban_ends_at, DROP ban_type, DROP ban_count, DROP banned_by_id');
    }
}
