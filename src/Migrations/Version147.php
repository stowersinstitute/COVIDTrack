<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version147 extends AbstractMigration
{
    public function getDescription() : string
    {
        // Ensures Tube has same status as Specimen when Results exist
        return 'Tube.status is RESULTS when associated Specimen has results';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('
UPDATE tubes
SET `status` = "RESULTS"
WHERE
    id IN (
        SELECT t.id
        FROM tubes t
        JOIN specimens s ON t.specimen_id=s.id
        WHERE s.`status` = "RESULTS"
    )
');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup');
    }
}
