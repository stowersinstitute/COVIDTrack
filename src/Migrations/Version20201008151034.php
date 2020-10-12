<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Tube;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20201008151034 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migrate Tube.status=ACCEPTED or REJECTED to use RETURNED with Tube.checkInDecision';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Assign a default value for records without check_in_decision
        $this->addSql(sprintf('
        UPDATE tubes
        SET
          check_in_decision="%s"
        WHERE
          check_in_decision IS NULL', Tube::CHECKED_IN_UNKNOWN));

        // Convert status="ACCEPTED" to (status="RETURNED" and checkInDecision="ACCEPTED")
        $this->addSql(sprintf('
        UPDATE tubes
        SET
          check_in_decision="%s",
          `status`="%s"
        WHERE
          `status`="ACCEPTED"', Tube::CHECKED_IN_ACCEPTED, Tube::STATUS_RETURNED));

        // Ensure Tubes with status="REJECTED" also mark checkInDecision="REJECTED"
        $this->addSql(sprintf('
        UPDATE tubes
        SET
          check_in_decision="%s"
        WHERE
          `status`="%s"', Tube::CHECKED_IN_REJECTED, Tube::STATUS_REJECTED));

        // Make checkInDecision NOT NULL now that every row has a value
        $this->addSql('ALTER TABLE tubes CHANGE check_in_decision check_in_decision VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup');
    }
}
