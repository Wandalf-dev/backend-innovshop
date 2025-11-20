<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251118000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add selectedColor and selectedSize fields to order_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item ADD selected_color VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD selected_size VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP COLUMN selected_color');
        $this->addSql('ALTER TABLE order_item DROP COLUMN selected_size');
    }
}

