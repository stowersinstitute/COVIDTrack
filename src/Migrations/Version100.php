<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

class Version100 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Use application TimestampableEntity trait';
    }

    public function up(Schema $schema): void
    {
        $lines = [
            "ALTER TABLE tubes CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'",
            "ALTER TABLE users CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'",
        ];

        foreach ($lines as $line) {
            $this->addSql($line);
        }
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('down() not supported');
    }
}
