<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Tube;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20200915173823 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'All Tubes previously used at time of query should never be sent to Tube API Web Hooks';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('
        UPDATE tubes SET web_hook_status = "' . Tube::WEBHOOK_STATUS_NEVER_SEND . '"
        WHERE `status` = "' . Tube::STATUS_EXTERNAL . '"
            OR `status` = "' . Tube::STATUS_RETURNED . '"
            OR `status` = "' . Tube::STATUS_ACCEPTED . '"
            OR `status` = "' . Tube::STATUS_REJECTED . '"
         ');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup');
    }
}
