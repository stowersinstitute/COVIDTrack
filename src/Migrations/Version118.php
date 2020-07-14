<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version118 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add ORM "discr" column to support subclassing EmailNotification class';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Create column `discr` and default all existing values to the only Notification class in prod
        $this->addSql('ALTER TABLE study_coordinator_notifications ADD discr VARCHAR(255) DEFAULT "clia"');

        // Update column `discr` to not allow NULL values
        $this->addSql('ALTER TABLE study_coordinator_notifications MODIFY discr VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE study_coordinator_notifications DROP discr');
    }
}
