<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version132 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add Participant Group flags for Accepting Saliva and Blood Specimens, and Sending Web Hook data for Viral and Antibody Results';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE participant_groups ADD accepts_saliva_specimens TINYINT(1) DEFAULT \'1\' NOT NULL, ADD accepts_blood_specimens TINYINT(1) DEFAULT \'1\' NOT NULL, ADD viral_results_web_hooks_enabled TINYINT(1) DEFAULT \'1\' NOT NULL, ADD antibody_results_web_hooks_enabled TINYINT(1) DEFAULT \'1\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE participant_groups DROP accepts_saliva_specimens, DROP accepts_blood_specimens, DROP viral_results_web_hooks_enabled, DROP antibody_results_web_hooks_enabled');
    }
}
