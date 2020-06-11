<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version110 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'CVDLS-95 Refactor relationship between Result <--> Well <--> Specimen <--> Result';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Add Results.well
        $this->addSql('ALTER TABLE specimen_results ADD specimen_well_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A413036263188691 FOREIGN KEY (specimen_well_id) REFERENCES specimen_wells (id)');
        $this->addSql('CREATE INDEX IDX_A413036263188691 ON specimen_results (specimen_well_id)');

        // Add index for Results.specimen
        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A4130362BF112A8');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A4130362BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id)');

        // Update newly created Results.well to be that of Results.specimen.well
        $updateQuery = "
            UPDATE specimen_results r
            INNER JOIN specimens s ON r.specimen_id=s.id
            INNER JOIN specimen_wells w ON s.id=w.specimen_id
            SET r.specimen_well_id = w.id
        ";
        $this->addSql($updateQuery);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Remove index for Results.specimen
        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A4130362BF112A8');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A4130362BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id) ON DELETE CASCADE');

        // Remove Results.well
        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A413036263188691');
        $this->addSql('DROP INDEX IDX_A413036263188691 ON specimen_results');
        $this->addSql('ALTER TABLE specimen_results DROP specimen_well_id');
    }
}
