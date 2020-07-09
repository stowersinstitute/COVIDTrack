<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Specimen;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20200709162806 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove CLIA Testing Recommendation for Blood Specimens';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $sql = sprintf('UPDATE specimens SET clia_testing_recommendation = NULL WHERE type = "%s"', Specimen::TYPE_BLOOD);
        $this->addSql($sql);
    }

    public function down(Schema $schema) : void
    {
        throw new IrreversibleMigration('Cannot undo setting clia_testing_recommendation to NULL for Blood Specimens. Restore from backup.');
    }
}
