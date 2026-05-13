<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226022133 extends AbstractMigration
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

    private function hasConstraint(string $tableName, string $constraintName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$tableName, $constraintName]
        ) > 0;
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tableName, $columnName]
        ) > 0;
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        ) > 0;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if (!$this->hasTable('notification')) {
            $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, payload JSON DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_notification_user_read_created (user_id, is_read, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasTable('user_ban')) {
            $this->addSql('CREATE TABLE user_ban (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, note LONGTEXT DEFAULT NULL, banned_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, lifted_at DATETIME DEFAULT NULL, lift_reason LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, type VARCHAR(10) NOT NULL, user_id INT NOT NULL, banned_by_id INT DEFAULT NULL, INDEX IDX_89E8B16E386B8E7 (banned_by_id), INDEX idx_user_ban_user_id (user_id), INDEX idx_user_ban_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$this->hasConstraint('notification', 'FK_BF5476CAA76ED395')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('user_ban', 'FK_89E8B16EA76ED395')) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('user_ban', 'FK_89E8B16E386B8E7')) {
            $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT FK_89E8B16E386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }
        if (!$this->hasColumn('contract', 'upfront_percent')) {
            $this->addSql('ALTER TABLE contract ADD upfront_percent NUMERIC(5, 2) DEFAULT \'30.00\' NOT NULL, ADD upfront_paid TINYINT DEFAULT 0 NOT NULL, ADD upfront_paid_at DATETIME DEFAULT NULL, ADD released_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, ADD risk_score DOUBLE PRECISION DEFAULT NULL, ADD risk_level VARCHAR(10) DEFAULT NULL');
        }
        if (!$this->hasColumn('milestone', 'delivered_at')) {
            $this->addSql('ALTER TABLE milestone ADD delivered_at DATETIME DEFAULT NULL');
        }
        if ($this->hasIndex('negotiation', 'IDX_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP INDEX IDX_1798959853C674EE, ADD UNIQUE INDEX UNIQ_1798959853C674EE (offer_id)');
        } elseif (!$this->hasIndex('negotiation', 'UNIQ_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation ADD UNIQUE INDEX UNIQ_1798959853C674EE (offer_id)');
        }
        if ($this->hasConstraint('negotiation', 'FK_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        }
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        if (!$this->hasConstraint('negotiation', 'FK_17989598AB159F5')) {
            $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598AB159F5 FOREIGN KEY (opened_by_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('negotiation', 'FK_179895986C066AFE')) {
            $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_179895986C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id)');
        }
        if (!$this->hasColumn('offer', 'updated_at')) {
            $this->addSql('ALTER TABLE offer ADD updated_at DATETIME DEFAULT NULL, ADD match_score DOUBLE PRECISION DEFAULT NULL, ADD proposed_budget NUMERIC(10, 2) DEFAULT NULL, ADD proposed_deadline DATETIME DEFAULT NULL, ADD client_id INT DEFAULT NULL');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873ED42F8111')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873ED42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873E6B20BA36')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E6B20BA36 FOREIGN KEY (worker_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('offer', 'FK_29D6873E19EB6921')) {
            $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E19EB6921 FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasIndex('offer', 'IDX_29D6873E19EB6921')) {
            $this->addSql('CREATE INDEX IDX_29D6873E19EB6921 ON offer (client_id)');
        }
        if ($this->hasConstraint('password_reset_tokens', 'FK_password_reset_tokens_user_id')) {
            $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `FK_password_reset_tokens_user_id`');
        }
        if ($this->hasConstraint('password_reset_tokens', 'FK_3967A216A76ED395')) {
            $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY `FK_3967A216A76ED395`');
        }
        if ($this->hasIndex('password_reset_tokens', 'idx_password_reset_tokens_user_id')) {
            $this->addSql('DROP INDEX idx_password_reset_tokens_user_id ON password_reset_tokens');
        }
        if (!$this->hasIndex('password_reset_tokens', 'IDX_3967A216A76ED395')) {
            $this->addSql('CREATE INDEX IDX_3967A216A76ED395 ON password_reset_tokens (user_id)');
        }
        if (!$this->hasConstraint('password_reset_tokens', 'FK_3967A216A76ED395')) {
            $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT `FK_3967A216A76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        if (!$this->hasConstraint('password_reset_tokens', 'FK_password_reset_tokens_user_id')) {
            $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT `FK_password_reset_tokens_user_id` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
        $this->addSql('ALTER TABLE service_request CHANGE status status VARCHAR(50) DEFAULT \'OPEN\' NOT NULL');
        if (!$this->hasConstraint('service_request', 'FK_F413DD0319EB6921')) {
            $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0319EB6921 FOREIGN KEY (client_id) REFERENCES users (id)');
        }
        if (!$this->hasConstraint('service_request', 'FK_F413DD0312469DE2')) {
            $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0312469DE2 FOREIGN KEY (category_id) REFERENCES worker_category (id)');
        }
        if (!$this->hasConstraint('service_requirement', 'FK_17A573FCED5CA9E6')) {
            $this->addSql('ALTER TABLE service_requirement ADD CONSTRAINT FK_17A573FCED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_request (id)');
        }
        if (!$this->hasColumn('ticket', 'ai_sentiment')) {
            $this->addSql('ALTER TABLE ticket ADD ai_sentiment VARCHAR(16) DEFAULT NULL, ADD ai_urgency VARCHAR(16) DEFAULT NULL, ADD ai_suggested_priority VARCHAR(16) DEFAULT NULL, ADD ai_summary LONGTEXT DEFAULT NULL');
        }
        if (!$this->hasColumn('users', 'ban_note')) {
            $this->addSql('ALTER TABLE users ADD ban_note LONGTEXT DEFAULT NULL, ADD ban_ends_at DATETIME DEFAULT NULL, ADD ban_type VARCHAR(10) DEFAULT NULL, ADD ban_count INT DEFAULT 0 NOT NULL, ADD banned_by_id INT DEFAULT NULL, CHANGE ban_reason ban_reason LONGTEXT DEFAULT NULL');
        }
        if (!$this->hasConstraint('users', 'FK_1483A5E9386B8E7')) {
            $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        }
        if (!$this->hasIndex('users', 'IDX_1483A5E9386B8E7')) {
            $this->addSql('CREATE INDEX IDX_1483A5E9386B8E7 ON users (banned_by_id)');
        }
        if (!$this->hasConstraint('worker_profile', 'FK_B5B8D142DBCE8125')) {
            $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142DBCE8125 FOREIGN KEY (worker_category_id) REFERENCES worker_category (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16EA76ED395');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY FK_89E8B16E386B8E7');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE user_ban');
        $this->addSql('ALTER TABLE contract DROP upfront_percent, DROP upfront_paid, DROP upfront_paid_at, DROP released_amount, DROP risk_score, DROP risk_level');
        $this->addSql('ALTER TABLE milestone DROP delivered_at');
        $this->addSql('ALTER TABLE negotiation DROP INDEX UNIQ_1798959853C674EE, ADD INDEX IDX_1798959853C674EE (offer_id)');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598AB159F5');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_179895986C066AFE');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873ED42F8111');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E6B20BA36');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E19EB6921');
        $this->addSql('DROP INDEX IDX_29D6873E19EB6921 ON offer');
        $this->addSql('ALTER TABLE offer DROP updated_at, DROP match_score, DROP proposed_budget, DROP proposed_deadline, DROP client_id');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('DROP INDEX idx_3967a216a76ed395 ON password_reset_tokens');
        $this->addSql('CREATE INDEX IDX_password_reset_tokens_user_id ON password_reset_tokens (user_id)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0319EB6921');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD0312469DE2');
        $this->addSql('ALTER TABLE service_request CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE service_requirement DROP FOREIGN KEY FK_17A573FCED5CA9E6');
        $this->addSql('ALTER TABLE ticket DROP ai_sentiment, DROP ai_urgency, DROP ai_suggested_priority, DROP ai_summary');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9386B8E7');
        $this->addSql('DROP INDEX IDX_1483A5E9386B8E7 ON users');
        $this->addSql('ALTER TABLE users DROP ban_note, DROP ban_ends_at, DROP ban_type, DROP ban_count, DROP banned_by_id, CHANGE ban_reason ban_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE worker_profile DROP FOREIGN KEY FK_B5B8D142DBCE8125');
    }
}
