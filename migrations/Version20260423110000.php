<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_review table for community activity reviews.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_review (id INT AUTO_INCREMENT NOT NULL, activity_id INT NOT NULL, user_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_activity_review_user_activity (activity_id, user_id), INDEX IDX_5B0C43DAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_review ADD CONSTRAINT FK_5B0C43DAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_review DROP FOREIGN KEY FK_5B0C43DAA76ED395');
        $this->addSql('DROP TABLE activity_review');
    }
}
