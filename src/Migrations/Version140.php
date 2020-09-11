<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version140 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Mark all current Specimen Results for research study to never be sent to Web Hooks (even if group enabled)';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // SpecimenResult::WEBHOOK_STATUS_NEVER_SEND
        $this->addSql('UPDATE specimen_results SET web_hook_status = "NEVER_SEND";');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE specimen_results SET web_hook_status = NULL;');
    }
}
