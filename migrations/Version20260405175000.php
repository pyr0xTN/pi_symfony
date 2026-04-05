<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405175000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservations CHANGE id_reservation id_reservation INT NOT NULL');
        $this->addSql('ALTER TABLE services CHANGE date_depart date_depart DATE DEFAULT NULL, CHANGE date_arrive date_arrive DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservations CHANGE id_reservation id_reservation INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE services CHANGE date_depart date_depart DATE NOT NULL, CHANGE date_arrive date_arrive DATE NOT NULL');
    }
}
