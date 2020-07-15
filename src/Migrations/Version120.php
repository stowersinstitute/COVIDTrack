<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version120 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Rename notification tables to not mention Study Coordinator';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // study_coordinator_notification_recommended_groups DROP FOREIGN KEY to study_coordinator_notifications table (which will be renamed email_notifications)
        $this->addSql('ALTER TABLE study_coordinator_notification_recommended_groups DROP FOREIGN KEY FK_B1D23227EF1A9D84');

        // Rename tables
        $this->addSql('RENAME TABLE study_coordinator_notifications TO email_notifications');
        $this->addSql('RENAME TABLE study_coordinator_notification_recommended_groups TO email_notification_recommended_groups');

        // email_notification_recommended_groups DROP FOREIGN KEY to participant_groups table
        $this->addSql('ALTER TABLE email_notification_recommended_groups DROP FOREIGN KEY FK_B1D23227F0155CBA');

        // email_notification_recommended_groups recreate INDEX
        $this->addSql('DROP INDEX idx_b1d23227ef1a9d84 ON email_notification_recommended_groups');
        $this->addSql('CREATE INDEX IDX_7FD65866EF1A9D84 ON email_notification_recommended_groups (notification_id)');
        $this->addSql('DROP INDEX idx_b1d23227f0155cba ON email_notification_recommended_groups');
        $this->addSql('CREATE INDEX IDX_7FD65866F0155CBA ON email_notification_recommended_groups (participant_group_id)');

        // email_notification_recommended_groups recreate FOREIGN KEY to email_notifications
        $this->addSql('ALTER TABLE email_notification_recommended_groups ADD CONSTRAINT FK_7FD65866EF1A9D84 FOREIGN KEY (notification_id) REFERENCES email_notifications (id) ON DELETE CASCADE');

        // email_notification_recommended_groups recreate FOREIGN KEY to participant_groups
        $this->addSql('ALTER TABLE email_notification_recommended_groups ADD CONSTRAINT FK_B1D23227F0155CBA FOREIGN KEY (participant_group_id) REFERENCES participant_groups (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup to downgrade');
    }
}
