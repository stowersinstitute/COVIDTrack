<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version105 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add Well Plate storage';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE specimen_wells (id INT AUTO_INCREMENT NOT NULL, well_plate_id INT NOT NULL, specimen_id INT NOT NULL, position SMALLINT UNSIGNED DEFAULT NULL, INDEX IDX_BC811F77FEB87F02 (well_plate_id), INDEX IDX_BC811F77BF112A8 (specimen_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE well_plates (id INT AUTO_INCREMENT NOT NULL, barcode VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_35DA1FB197AE0266 (barcode), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE specimen_wells ADD CONSTRAINT FK_BC811F77FEB87F02 FOREIGN KEY (well_plate_id) REFERENCES well_plates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE specimen_wells ADD CONSTRAINT FK_BC811F77BF112A8 FOREIGN KEY (specimen_id) REFERENCES specimens (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE excel_import_workbooks ADD file_mime_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE specimens DROP rna_well_plate_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_wells DROP FOREIGN KEY FK_BC811F77FEB87F02');
        $this->addSql('DROP TABLE specimen_wells');
        $this->addSql('DROP TABLE well_plates');
        $this->addSql('ALTER TABLE excel_import_workbooks DROP file_mime_type');
        $this->addSql('ALTER TABLE specimens ADD rna_well_plate_id VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
    }
}
