<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200527191205 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds scheduling support including drop-off windows';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE drop_off_windows (id INT AUTO_INCREMENT NOT NULL, schedule_id INT NOT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_43623626A40BC2D5 (schedule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE drop_off_window_groups (drop_off_window_id INT NOT NULL, participant_group_id INT NOT NULL, INDEX IDX_E417CD84820A8D0B (drop_off_window_id), INDEX IDX_E417CD84F0155CBA (participant_group_id), PRIMARY KEY(drop_off_window_id, participant_group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE drop_off_schedules (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, location LONGTEXT DEFAULT NULL, days_of_the_week LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', daily_start_time TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', daily_end_time TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', window_interval_minutes INT DEFAULT NULL, num_expected_drop_offs_per_group INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE drop_off_windows ADD CONSTRAINT FK_43623626A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES drop_off_schedules (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE drop_off_window_groups ADD CONSTRAINT FK_E417CD84820A8D0B FOREIGN KEY (drop_off_window_id) REFERENCES drop_off_windows (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE drop_off_window_groups ADD CONSTRAINT FK_E417CD84F0155CBA FOREIGN KEY (participant_group_id) REFERENCES participant_groups (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE drop_off_window_groups DROP FOREIGN KEY FK_E417CD84820A8D0B');
        $this->addSql('ALTER TABLE drop_off_windows DROP FOREIGN KEY FK_43623626A40BC2D5');
        $this->addSql('DROP TABLE drop_off_windows');
        $this->addSql('DROP TABLE drop_off_window_groups');
        $this->addSql('DROP TABLE drop_off_schedules');
    }
}
