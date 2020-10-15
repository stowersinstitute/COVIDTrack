<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version145 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Enforce One-To-One relationship between Tube and Specimen';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tubes DROP INDEX IDX_C444196BF112A8, ADD UNIQUE INDEX UNIQ_C444196BF112A8 (specimen_id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tubes DROP INDEX UNIQ_C444196BF112A8, ADD INDEX IDX_C444196BF112A8 (specimen_id)');
    }
}
