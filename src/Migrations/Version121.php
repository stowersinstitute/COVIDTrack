<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version121 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results ADD ct1 VARCHAR(255) DEFAULT NULL, ADD ct1_amp_score VARCHAR(255) DEFAULT NULL, ADD ct2 VARCHAR(255) DEFAULT NULL, ADD ct2_amp_score VARCHAR(255) DEFAULT NULL, ADD ct3 VARCHAR(255) DEFAULT NULL, ADD ct3_amp_score VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP ct1, DROP ct1_amp_score, DROP ct2, DROP ct2_amp_score, DROP ct3, DROP ct3_amp_score');
    }
}
