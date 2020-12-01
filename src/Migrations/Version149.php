<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version149 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Excel Workbook import now stored as one row in excel_import_workbooks';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE excel_import_cells DROP FOREIGN KEY FK_CC2D7B332A915FFA');
        $this->addSql('DROP TABLE excel_import_cells');
        $this->addSql('DROP TABLE excel_import_worksheets');
        $this->addSql('ALTER TABLE excel_import_workbooks ADD worksheets LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE excel_import_workbooks CHANGE excel_data worksheets LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE excel_import_cells (id INT AUTO_INCREMENT NOT NULL, worksheet_id INT NOT NULL, row_index INT NOT NULL, col_index VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, value LONGTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, value_type VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX IDX_CC2D7B332A915FFA (worksheet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE excel_import_worksheets (id INT AUTO_INCREMENT NOT NULL, workbook_id INT NOT NULL, title VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, INDEX IDX_B54347BE44689220 (workbook_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE excel_import_cells ADD CONSTRAINT FK_CC2D7B332A915FFA FOREIGN KEY (worksheet_id) REFERENCES excel_import_worksheets (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE excel_import_worksheets ADD CONSTRAINT FK_B54347BE44689220 FOREIGN KEY (workbook_id) REFERENCES excel_import_workbooks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE excel_import_workbooks DROP worksheets');
    }
}
