<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add birthday gift claim year to profile table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD birthday_gift_year INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP birthday_gift_year');
    }
}
