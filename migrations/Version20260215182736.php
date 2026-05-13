<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215182736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function hasConstraint(string $tableName, string $constraintName): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$tableName, $constraintName]
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
        // negotiation: ensure unique index on offer_id (idempotent)
        if ($this->hasIndex('negotiation', 'IDX_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP INDEX IDX_1798959853C674EE, ADD UNIQUE INDEX UNIQ_1798959853C674EE (offer_id)');
        } elseif (!$this->hasIndex('negotiation', 'UNIQ_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation ADD UNIQUE INDEX UNIQ_1798959853C674EE (offer_id)');
        }
        if ($this->hasConstraint('negotiation', 'FK_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        }
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        if ($this->hasIndex('worker_profile', 'UNIQ_B5B8D142A76ED395')) {
            $this->addSql('DROP INDEX UNIQ_B5B8D142A76ED395 ON worker_profile');
        }
        // worker_profile: add/change columns idempotently (DB may lack latitude/longitude)
        if (!$this->hasColumn('worker_profile', 'cv_file_path')) {
            $this->addSql('ALTER TABLE worker_profile ADD cv_file_path VARCHAR(255) DEFAULT NULL');
        }
        if ($this->hasColumn('worker_profile', 'latitude')) {
            $this->addSql('ALTER TABLE worker_profile CHANGE latitude latitude NUMERIC(10, 7) DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE worker_profile ADD latitude NUMERIC(10, 7) DEFAULT NULL');
        }
        if ($this->hasColumn('worker_profile', 'longitude')) {
            $this->addSql('ALTER TABLE worker_profile CHANGE longitude longitude NUMERIC(10, 7) DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE worker_profile ADD longitude NUMERIC(10, 7) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        if ($this->hasIndex('negotiation', 'UNIQ_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP INDEX UNIQ_1798959853C674EE, ADD INDEX IDX_1798959853C674EE (offer_id)');
        } elseif (!$this->hasIndex('negotiation', 'IDX_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation ADD INDEX IDX_1798959853C674EE (offer_id)');
        }
        if ($this->hasConstraint('negotiation', 'FK_1798959853C674EE')) {
            $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959853C674EE');
        }
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        if ($this->hasColumn('worker_profile', 'cv_file_path')) {
            $this->addSql('ALTER TABLE worker_profile DROP cv_file_path');
        }
        if ($this->hasColumn('worker_profile', 'latitude')) {
            $this->addSql('ALTER TABLE worker_profile CHANGE latitude latitude NUMERIC(10, 8) DEFAULT NULL');
        }
        if ($this->hasColumn('worker_profile', 'longitude')) {
            $this->addSql('ALTER TABLE worker_profile CHANGE longitude longitude NUMERIC(11, 8) DEFAULT NULL');
        }
        if (!$this->hasIndex('worker_profile', 'UNIQ_B5B8D142A76ED395')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_B5B8D142A76ED395 ON worker_profile (user_id)');
        }
    }
}
