<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

// TODO: Rename class
final class Version20200829084200 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove Group Scheduling features';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE drop_off_window_groups DROP FOREIGN KEY FK_E417CD84820A8D0B');
        $this->addSql('ALTER TABLE drop_off_window_groups DROP FOREIGN KEY FK_E417CD84F0155CBA');
        $this->addSql('ALTER TABLE drop_off_windows DROP FOREIGN KEY FK_43623626A40BC2D5');
        $this->addSql('DROP TABLE drop_off_windows');
        $this->addSql('DROP TABLE drop_off_window_groups');
        $this->addSql('DROP TABLE drop_off_schedules');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        throw new IrreversibleMigration('Restore from backup');
    }
}
