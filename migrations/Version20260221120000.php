<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add advanced fields to worker_profile and worker_category, create worker_education table.
 */
final class Version20260221120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WorkerProfile: skills, radius_km, slug, visibility, linkedin_url, website_url. WorkerCategory: slug, parent_id, suggested_min_rate, suggested_max_rate, keywords. WorkerEducation table.';
    }

    public function up(Schema $schema): void
    {
        // worker_profile: new columns
        $this->addSql('ALTER TABLE worker_profile ADD skills JSON DEFAULT NULL, ADD radius_km INT DEFAULT NULL, ADD slug VARCHAR(255) DEFAULT NULL, ADD visibility VARCHAR(50) DEFAULT \'draft\' NOT NULL, ADD linkedin_url VARCHAR(255) DEFAULT NULL, ADD website_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B5B8D142989D9B62 ON worker_profile (slug)');

        // worker_education table
        $this->addSql('CREATE TABLE worker_education (id INT AUTO_INCREMENT NOT NULL, worker_profile_id INT NOT NULL, institution VARCHAR(255) NOT NULL, diploma VARCHAR(255) NOT NULL, field VARCHAR(255) DEFAULT NULL, year_start SMALLINT DEFAULT NULL, year_end SMALLINT DEFAULT NULL, INDEX IDX_worker_education_profile (worker_profile_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE worker_education ADD CONSTRAINT FK_worker_education_profile FOREIGN KEY (worker_profile_id) REFERENCES worker_profile (id) ON DELETE CASCADE');

        // worker_category: new columns
        $this->addSql('ALTER TABLE worker_category ADD slug VARCHAR(255) DEFAULT NULL, ADD parent_id INT DEFAULT NULL, ADD suggested_min_rate NUMERIC(10, 2) DEFAULT NULL, ADD suggested_max_rate NUMERIC(10, 2) DEFAULT NULL, ADD keywords JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE worker_category CHANGE total_workers total_workers INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_worker_category_slug ON worker_category (slug)');
        $this->addSql('ALTER TABLE worker_category ADD CONSTRAINT FK_worker_category_parent FOREIGN KEY (parent_id) REFERENCES worker_category (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_category DROP FOREIGN KEY FK_worker_category_parent');
        $this->addSql('DROP INDEX UNIQ_worker_category_slug ON worker_category');
        $this->addSql('ALTER TABLE worker_category DROP slug, DROP parent_id, DROP suggested_min_rate, DROP suggested_max_rate, DROP keywords, CHANGE total_workers total_workers INT NOT NULL');

        $this->addSql('ALTER TABLE worker_education DROP FOREIGN KEY FK_worker_education_profile');
        $this->addSql('DROP TABLE worker_education');

        $this->addSql('DROP INDEX UNIQ_B5B8D142989D9B62 ON worker_profile');
        $this->addSql('ALTER TABLE worker_profile DROP skills, DROP radius_km, DROP slug, DROP visibility, DROP linkedin_url, DROP website_url');
    }
}
