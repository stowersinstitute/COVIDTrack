<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version119 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Data migration for new EmailNotification "discr" column value of CliaRecommendationViralNotification';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Update column `discr` for CLIA Recommendation Notifications
        $this->addSql('UPDATE study_coordinator_notifications SET discr="cliaViral" WHERE discr="clia"');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE study_coordinator_notifications SET discr="clia" WHERE discr="cliaViral"');
    }
}
