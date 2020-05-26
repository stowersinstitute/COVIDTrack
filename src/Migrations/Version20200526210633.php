<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200526210633 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds Kiosk session tracking';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE dropoffs ADD kiosk_session_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD deleted_at DATETIME DEFAULT NULL, DROP status, DROP kiosk');
        $this->addSql('ALTER TABLE dropoffs ADD CONSTRAINT FK_9FB0510A5629F13B FOREIGN KEY (kiosk_session_id) REFERENCES kiosk_sessions (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9FB0510A5629F13B ON dropoffs (kiosk_session_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE dropoffs DROP FOREIGN KEY FK_9FB0510A5629F13B');
        $this->addSql('DROP INDEX IDX_9FB0510A5629F13B ON dropoffs');
        $this->addSql('ALTER TABLE dropoffs ADD status VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, ADD kiosk VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, DROP kiosk_session_id, DROP created_at, DROP updated_at, DROP deleted_at');
    }
}
