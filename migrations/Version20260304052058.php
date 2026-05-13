<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304052058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blameable columns and nullable updated_at audit fields for Doctrine Doctor integrity fixes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_recommendation ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE ai_recommendation SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE ai_recommendation ADD CONSTRAINT FK_910E9413B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ai_recommendation ADD CONSTRAINT FK_910E9413896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_910E9413B03A8386 ON ai_recommendation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_910E9413896DBBDE ON ai_recommendation (updated_by_id)');

        $this->addSql('ALTER TABLE category_ticket ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE category_ticket SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE category_ticket ADD CONSTRAINT FK_984867D8B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category_ticket ADD CONSTRAINT FK_984867D8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_984867D8B03A8386 ON category_ticket (created_by_id)');
        $this->addSql('CREATE INDEX IDX_984867D8896DBBDE ON category_ticket (updated_by_id)');

        $this->addSql('ALTER TABLE contract ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E98F2859B03A8386 ON contract (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E98F2859896DBBDE ON contract (updated_by_id)');

        $this->addSql('ALTER TABLE conversation ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE conversation SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8A8E26E9B03A8386 ON conversation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9896DBBDE ON conversation (updated_by_id)');

        $this->addSql('ALTER TABLE conversation_message ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE conversation_message SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E75B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E75896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2DEB3E75B03A8386 ON conversation_message (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2DEB3E75896DBBDE ON conversation_message (updated_by_id)');

        $this->addSql('ALTER TABLE face_profiles ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE face_profiles ADD CONSTRAINT FK_AF948895B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE face_profiles ADD CONSTRAINT FK_AF948895896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_AF948895B03A8386 ON face_profiles (created_by_id)');
        $this->addSql('CREATE INDEX IDX_AF948895896DBBDE ON face_profiles (updated_by_id)');

        $this->addSql('ALTER TABLE milestone ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE milestone ADD CONSTRAINT FK_4FAC8382B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE milestone ADD CONSTRAINT FK_4FAC8382896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4FAC8382B03A8386 ON milestone (created_by_id)');
        $this->addSql('CREATE INDEX IDX_4FAC8382896DBBDE ON milestone (updated_by_id)');

        $this->addSql('ALTER TABLE negotiation ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_17989598B03A8386 ON negotiation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_17989598896DBBDE ON negotiation (updated_by_id)');

        $this->addSql('ALTER TABLE notification ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE notification SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BF5476CAB03A8386 ON notification (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA896DBBDE ON notification (updated_by_id)');

        $this->addSql('ALTER TABLE offer ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_29D6873EB03A8386 ON offer (created_by_id)');
        $this->addSql('CREATE INDEX IDX_29D6873E896DBBDE ON offer (updated_by_id)');

        $this->addSql('ALTER TABLE service_request ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE service_request SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F413DD03B03A8386 ON service_request (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F413DD03896DBBDE ON service_request (updated_by_id)');

        $this->addSql('ALTER TABLE sub_ticket ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE sub_ticket SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EFB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sub_ticket ADD CONSTRAINT FK_25F1E2EF896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_25F1E2EFB03A8386 ON sub_ticket (created_by_id)');
        $this->addSql('CREATE INDEX IDX_25F1E2EF896DBBDE ON sub_ticket (updated_by_id)');

        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket CHANGE created_by_id created_by_id INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE ticket SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_97A0ADA3896DBBDE ON ticket (updated_by_id)');

        $this->addSql('ALTER TABLE users ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1483A5E9B03A8386 ON users (created_by_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9896DBBDE ON users (updated_by_id)');

        $this->addSql('ALTER TABLE worker_category ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE worker_category ADD CONSTRAINT FK_E15CB97B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE worker_category ADD CONSTRAINT FK_E15CB97896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E15CB97B03A8386 ON worker_category (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E15CB97896DBBDE ON worker_category (updated_by_id)');

        $this->addSql('ALTER TABLE worker_profile ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE worker_profile ADD CONSTRAINT FK_B5B8D142896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B5B8D142B03A8386 ON worker_profile (created_by_id)');
        $this->addSql('CREATE INDEX IDX_B5B8D142896DBBDE ON worker_profile (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_recommendation DROP FOREIGN KEY FK_910E9413B03A8386');
        $this->addSql('ALTER TABLE ai_recommendation DROP FOREIGN KEY FK_910E9413896DBBDE');
        $this->addSql('DROP INDEX IDX_910E9413B03A8386 ON ai_recommendation');
        $this->addSql('DROP INDEX IDX_910E9413896DBBDE ON ai_recommendation');
        $this->addSql('ALTER TABLE ai_recommendation DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE category_ticket DROP FOREIGN KEY FK_984867D8B03A8386');
        $this->addSql('ALTER TABLE category_ticket DROP FOREIGN KEY FK_984867D8896DBBDE');
        $this->addSql('DROP INDEX IDX_984867D8B03A8386 ON category_ticket');
        $this->addSql('DROP INDEX IDX_984867D8896DBBDE ON category_ticket');
        $this->addSql('ALTER TABLE category_ticket DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B03A8386');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859896DBBDE');
        $this->addSql('DROP INDEX IDX_E98F2859B03A8386 ON contract');
        $this->addSql('DROP INDEX IDX_E98F2859896DBBDE ON contract');
        $this->addSql('ALTER TABLE contract DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9B03A8386');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9896DBBDE');
        $this->addSql('DROP INDEX IDX_8A8E26E9B03A8386 ON conversation');
        $this->addSql('DROP INDEX IDX_8A8E26E9896DBBDE ON conversation');
        $this->addSql('ALTER TABLE conversation DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E75B03A8386');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E75896DBBDE');
        $this->addSql('DROP INDEX IDX_2DEB3E75B03A8386 ON conversation_message');
        $this->addSql('DROP INDEX IDX_2DEB3E75896DBBDE ON conversation_message');
        $this->addSql('ALTER TABLE conversation_message DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE face_profiles DROP FOREIGN KEY FK_AF948895B03A8386');
        $this->addSql('ALTER TABLE face_profiles DROP FOREIGN KEY FK_AF948895896DBBDE');
        $this->addSql('DROP INDEX IDX_AF948895B03A8386 ON face_profiles');
        $this->addSql('DROP INDEX IDX_AF948895896DBBDE ON face_profiles');
        $this->addSql('ALTER TABLE face_profiles DROP created_by_id, DROP updated_by_id, CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE milestone DROP FOREIGN KEY FK_4FAC8382B03A8386');
        $this->addSql('ALTER TABLE milestone DROP FOREIGN KEY FK_4FAC8382896DBBDE');
        $this->addSql('DROP INDEX IDX_4FAC8382B03A8386 ON milestone');
        $this->addSql('DROP INDEX IDX_4FAC8382896DBBDE ON milestone');
        $this->addSql('ALTER TABLE milestone DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598B03A8386');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598896DBBDE');
        $this->addSql('DROP INDEX IDX_17989598B03A8386 ON negotiation');
        $this->addSql('DROP INDEX IDX_17989598896DBBDE ON negotiation');
        $this->addSql('ALTER TABLE negotiation DROP created_by_id, DROP updated_by_id, CHANGE updated_at updated_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB03A8386');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA896DBBDE');
        $this->addSql('DROP INDEX IDX_BF5476CAB03A8386 ON notification');
        $this->addSql('DROP INDEX IDX_BF5476CA896DBBDE ON notification');
        $this->addSql('ALTER TABLE notification DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873EB03A8386');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E896DBBDE');
        $this->addSql('DROP INDEX IDX_29D6873EB03A8386 ON offer');
        $this->addSql('DROP INDEX IDX_29D6873E896DBBDE ON offer');
        $this->addSql('ALTER TABLE offer DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03B03A8386');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03896DBBDE');
        $this->addSql('DROP INDEX IDX_F413DD03B03A8386 ON service_request');
        $this->addSql('DROP INDEX IDX_F413DD03896DBBDE ON service_request');
        $this->addSql('ALTER TABLE service_request DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EFB03A8386');
        $this->addSql('ALTER TABLE sub_ticket DROP FOREIGN KEY FK_25F1E2EF896DBBDE');
        $this->addSql('DROP INDEX IDX_25F1E2EFB03A8386 ON sub_ticket');
        $this->addSql('DROP INDEX IDX_25F1E2EF896DBBDE ON sub_ticket');
        $this->addSql('ALTER TABLE sub_ticket DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3896DBBDE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('DROP INDEX IDX_97A0ADA3896DBBDE ON ticket');
        $this->addSql('ALTER TABLE ticket DROP updated_at, DROP updated_by_id, CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');

        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9B03A8386');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9896DBBDE');
        $this->addSql('DROP INDEX IDX_1483A5E9B03A8386 ON users');
        $this->addSql('DROP INDEX IDX_1483A5E9896DBBDE ON users');
        $this->addSql('ALTER TABLE users DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE worker_category DROP FOREIGN KEY FK_E15CB97B03A8386');
        $this->addSql('ALTER TABLE worker_category DROP FOREIGN KEY FK_E15CB97896DBBDE');
        $this->addSql('DROP INDEX IDX_E15CB97B03A8386 ON worker_category');
        $this->addSql('DROP INDEX IDX_E15CB97896DBBDE ON worker_category');
        $this->addSql('ALTER TABLE worker_category DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE worker_profile DROP FOREIGN KEY FK_B5B8D142B03A8386');
        $this->addSql('ALTER TABLE worker_profile DROP FOREIGN KEY FK_B5B8D142896DBBDE');
        $this->addSql('DROP INDEX IDX_B5B8D142B03A8386 ON worker_profile');
        $this->addSql('DROP INDEX IDX_B5B8D142896DBBDE ON worker_profile');
        $this->addSql('ALTER TABLE worker_profile DROP created_by_id, DROP updated_by_id');
    }
}
