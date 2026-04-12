<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe payment intent reference to achat for idempotent activity payments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE achat ADD stripePaymentIntentId VARCHAR(255) DEFAULT NULL, ADD UNIQUE INDEX uniq_achat_stripe_payment_intent (stripePaymentIntentId)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE achat DROP INDEX uniq_achat_stripe_payment_intent, DROP COLUMN stripePaymentIntentId');
    }
}
