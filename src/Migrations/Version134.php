<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version134 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add DATETIME field and set default values for SpecimenResult.webHookFieldChangedAt';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // 1. Create field as nullable since no records will have this data
        $this->addSql('ALTER TABLE specimen_results ADD web_hook_field_changed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // 2. Set webHookFieldChangedAt to each record's updatedAt datetime.
        // This means the Web Hook system will consider any record currently in prod as
        // having been sent to the Web Hook system.
        // We'll probably need to do this kind of update when actually going live
        // with the Web Hook system. See ticket CVDLS-241
        // All records now have a web_hook_field_changed_at value.
        $this->addSql('UPDATE specimen_results SET web_hook_field_changed_at = updated_at');

        // 3. Remove nullable now that all records have this value
        $this->addSql('ALTER TABLE specimen_results MODIFY COLUMN web_hook_field_changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results DROP web_hook_field_changed_at');
    }
}
