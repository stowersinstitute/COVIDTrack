<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\SpecimenResult;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// TODO: Rename class
final class Version20200905204529 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'SpecimenResult holds Web Hook details in status, message and timestamp field';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Add new fields for tracking Web Hook system
        $this->addSql('ALTER TABLE specimen_results ADD web_hook_status VARCHAR(255) DEFAULT NULL, ADD web_hook_status_message LONGTEXT DEFAULT NULL, ADD web_hook_last_tried_publishing_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // All results in prod are for research study and are currently not going to Web Hooks
        $this->addSql('
            UPDATE specimen_results SET
            web_hook_status = "' . SpecimenResult::WEBHOOK_STATUS_NEVER_SEND . '",
            web_hook_status_message = "Result from Research Study"
            ');

        // Remove old field tracking Web Hook success
        $this->addSql('ALTER TABLE specimen_results DROP last_web_hook_success_at');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE specimen_results ADD last_web_hook_success_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE specimen_results DROP web_hook_status, DROP web_hook_status_message, DROP web_hook_last_tried_publishing_at');
    }
}
