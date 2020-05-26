<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200526213628 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE kiosk_sessions (id INT AUTO_INCREMENT NOT NULL, kiosk_id INT DEFAULT NULL, drop_off_id INT DEFAULT NULL, participant_group_id INT DEFAULT NULL, most_recent_screen VARCHAR(255) NOT NULL, canceled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL, INDEX IDX_DD90E964C47A2102 (kiosk_id), INDEX IDX_DD90E964EBBCCCD7 (drop_off_id), INDEX IDX_DD90E964F0155CBA (participant_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kiosk_session_tubes (id INT AUTO_INCREMENT NOT NULL, kiosk_session_id INT DEFAULT NULL, tube_id INT DEFAULT NULL, tube_type VARCHAR(255) NOT NULL, tube_collected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_726A58465629F13B (kiosk_session_id), INDEX IDX_726A5846A8AE880A (tube_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kiosk_sessions ADD CONSTRAINT FK_DD90E964C47A2102 FOREIGN KEY (kiosk_id) REFERENCES kiosks (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kiosk_sessions ADD CONSTRAINT FK_DD90E964EBBCCCD7 FOREIGN KEY (drop_off_id) REFERENCES dropoffs (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kiosk_sessions ADD CONSTRAINT FK_DD90E964F0155CBA FOREIGN KEY (participant_group_id) REFERENCES participant_groups (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A58465629F13B FOREIGN KEY (kiosk_session_id) REFERENCES kiosk_sessions (id)');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A5846A8AE880A FOREIGN KEY (tube_id) REFERENCES tubes (id)');
        $this->addSql('ALTER TABLE dropoffs ADD kiosk_session_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD deleted_at DATETIME DEFAULT NULL, DROP status, DROP kiosk');
        $this->addSql('ALTER TABLE dropoffs ADD CONSTRAINT FK_9FB0510A5629F13B FOREIGN KEY (kiosk_session_id) REFERENCES kiosk_sessions (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9FB0510A5629F13B ON dropoffs (kiosk_session_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE dropoffs DROP FOREIGN KEY FK_9FB0510A5629F13B');
        $this->addSql('ALTER TABLE kiosk_session_tubes DROP FOREIGN KEY FK_726A58465629F13B');
        $this->addSql('DROP TABLE kiosk_sessions');
        $this->addSql('DROP TABLE kiosk_session_tubes');
        $this->addSql('DROP INDEX IDX_9FB0510A5629F13B ON dropoffs');
        $this->addSql('ALTER TABLE dropoffs ADD status VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, ADD kiosk VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, DROP kiosk_session_id, DROP created_at, DROP updated_at, DROP deleted_at');
    }
}
