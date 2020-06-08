<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200608210507 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'CVDLS-95 Refactor relationship between Result <--> Well <--> Specimen';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A4130362BF112A8');
        $this->addSql('DROP INDEX IDX_A4130362BF112A8 ON specimen_results');
        $this->addSql('ALTER TABLE specimen_results CHANGE specimen_id specimen_well_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A413036263188691 FOREIGN KEY (specimen_well_id) REFERENCES specimen_wells (id)');
        $this->addSql('CREATE INDEX IDX_A413036263188691 ON specimen_results (specimen_well_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP FOREIGN KEY FK_A413036263188691');
        $this->addSql('DROP INDEX IDX_A413036263188691 ON specimen_results');
        $this->addSql('ALTER TABLE specimen_results CHANGE specimen_well_id specimen_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE specimen_results ADD CONSTRAINT FK_A4130362BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_A4130362BF112A8 ON specimen_results (specimen_id)');
    }
}
