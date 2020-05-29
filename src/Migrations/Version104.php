<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version104 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Fix which fields are nullable in system_configuration_entries';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE system_configuration_entries CHANGE reference_id reference_id VARCHAR(255) NOT NULL, CHANGE label label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE system_configuration_entries CHANGE reference_id reference_id VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, CHANGE label label VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
