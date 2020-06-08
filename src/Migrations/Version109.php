<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version109 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Fix cascade delete when trying to remove a Tube or Kiosk Session record';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE kiosk_session_tubes DROP FOREIGN KEY FK_726A58465629F13B');
        $this->addSql('ALTER TABLE kiosk_session_tubes DROP FOREIGN KEY FK_726A5846A8AE880A');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A58465629F13B FOREIGN KEY (kiosk_session_id) REFERENCES kiosk_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A5846A8AE880A FOREIGN KEY (tube_id) REFERENCES tubes (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE kiosk_session_tubes DROP FOREIGN KEY FK_726A58465629F13B');
        $this->addSql('ALTER TABLE kiosk_session_tubes DROP FOREIGN KEY FK_726A5846A8AE880A');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A58465629F13B FOREIGN KEY (kiosk_session_id) REFERENCES kiosk_sessions (id)');
        $this->addSql('ALTER TABLE kiosk_session_tubes ADD CONSTRAINT FK_726A5846A8AE880A FOREIGN KEY (tube_id) REFERENCES tubes (id)');
    }
}
