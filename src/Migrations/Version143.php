<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Tube;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version143 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Tube.webHookStatus default value for Tubes created or already printed labels';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('
        UPDATE tubes SET web_hook_status = "' . Tube::WEBHOOK_STATUS_PENDING . '"
        WHERE `status` = "' . Tube::STATUS_CREATED . '"
            OR `status` = "' . Tube::STATUS_PRINTED . '"
         ');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup');
    }
}
