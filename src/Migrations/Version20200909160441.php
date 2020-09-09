<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// TODO: Rename class
final class Version20200909160441 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove field SpecimenResult.webHookFieldChangedAt';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP web_hook_field_changed_at');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results ADD web_hook_field_changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
