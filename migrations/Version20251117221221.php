<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117221221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add selectedSize field to cart_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item ADD selected_size VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item DROP COLUMN selected_size');
    }
}
