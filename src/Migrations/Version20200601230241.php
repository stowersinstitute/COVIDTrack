<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200601230241 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Study Coordinator can be notified about new groups recommended for testing';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE study_coordinator_notifications (id INT AUTO_INCREMENT NOT NULL, fromAddresses LONGTEXT DEFAULT NULL, toAddresses LONGTEXT DEFAULT NULL, subject LONGTEXT DEFAULT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE study_coordinator_notification_recommended_groups (notification_id INT NOT NULL, participant_group_id INT NOT NULL, INDEX IDX_B1D23227EF1A9D84 (notification_id), INDEX IDX_B1D23227F0155CBA (participant_group_id), PRIMARY KEY(notification_id, participant_group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE study_coordinator_notification_recommended_groups ADD CONSTRAINT FK_B1D23227EF1A9D84 FOREIGN KEY (notification_id) REFERENCES study_coordinator_notifications (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE study_coordinator_notification_recommended_groups ADD CONSTRAINT FK_B1D23227F0155CBA FOREIGN KEY (participant_group_id) REFERENCES participant_groups (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX specimen_results_created_at_idx ON specimen_results (created_at)');
        $this->addSql('CREATE INDEX specimen_results_conclusion_idx ON specimen_results (conclusion)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE study_coordinator_notification_recommended_groups DROP FOREIGN KEY FK_B1D23227EF1A9D84');
        $this->addSql('DROP TABLE study_coordinator_notifications');
        $this->addSql('DROP TABLE study_coordinator_notification_recommended_groups');
        $this->addSql('DROP INDEX specimen_results_created_at_idx ON specimen_results');
        $this->addSql('DROP INDEX specimen_results_conclusion_idx ON specimen_results');
    }
}
