<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Tube;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201008151034 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add Tube.qualityCheckStatus';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Add qualityCheckStatus, for now allows NULL
        $this->addSql('ALTER TABLE tubes ADD qualityCheckStatus VARCHAR(255) DEFAULT NULL');

        // Default value for all existing records
        $this->addSql(sprintf('UPDATE tubes SET qualityCheckStatus="%s"', Tube::QUALITY_UNKNOWN));

        // Convert status="ACCEPTED" to (status="RETURNED" and qualityCheckStatus="ACCEPTED")
        $this->addSql(sprintf('
        UPDATE tubes
        SET
          qualityCheckStatus="%s",
          `status`="%s"
        WHERE
          `status`="%s"', Tube::QUALITY_ACCEPTED, Tube::STATUS_RETURNED, Tube::STATUS_ACCEPTED));

        // Convert status="REJECTED" to (status="RETURNED" and qualityCheckStatus="REJECTED")
        $this->addSql(sprintf('
        UPDATE tubes
        SET
          qualityCheckStatus="%s",
          `status`="%s"
        WHERE
          `status`="%s"', Tube::QUALITY_REJECTED, Tube::STATUS_RETURNED, Tube::STATUS_REJECTED));

        // Make qualityCheckStatus NOT NULL that every row has a value
        $this->addSql('ALTER TABLE tubes CHANGE qualityCheckStatus qualityCheckStatus VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tubes DROP qualityCheckStatus');
    }
}
