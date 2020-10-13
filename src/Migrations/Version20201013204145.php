<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20201013204145 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Saliva Specimens belonging to CONTROL group moved from Accepted status';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Update CONTROL Specimens currently in ACCEPTED status to RETURNED
        $this->addSql('
        UPDATE specimens
        SET
          `status`="RETURNED",
          `clia_testing_recommendation`=NULL
        WHERE
          `status`="ACCEPTED"
          AND id IN (
            SELECT s.id
            FROM specimens s
              JOIN participant_groups g ON s.`participant_group_id`=g.id
            WHERE g.title="CONTROL"
          )
');
    }

    public function down(Schema $schema) : void
    {
        throw new IrreversibleMigration('Restore from backup');
    }
}
