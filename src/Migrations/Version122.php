<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version122 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Cascade delete Specimen Results when deleting a Specimen record';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A4130362BF112A8');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A4130362BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A4130362BF112A8');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A4130362BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id)');
    }
}
