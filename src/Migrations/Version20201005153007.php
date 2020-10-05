<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20201005153007 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Tube.status updated from EXTERNAL to RESULTS when Specimen has results';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('
UPDATE tubes
SET `status` = "RESULTS"
WHERE
    `status` = "EXTERNAL"
    AND id IN (
        SELECT t.id
        FROM tubes t
        JOIN specimens s ON t.specimen_id=s.id
        WHERE s.`status` = "RESULTS"
    )
');
    }

    public function down(Schema $schema) : void
    {
        throw new IrreversibleMigration('Restore from backup');
    }
}
