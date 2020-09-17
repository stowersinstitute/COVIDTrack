<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version141 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add fields for sending Tube records to Web Hook API';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tubes ADD web_hook_status VARCHAR(255) DEFAULT NULL, ADD web_hook_status_message LONGTEXT DEFAULT NULL, ADD web_hook_last_tried_publishing_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tubes DROP web_hook_status, DROP web_hook_status_message, DROP web_hook_last_tried_publishing_at');
    }
}
