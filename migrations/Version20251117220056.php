<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117220056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add selectedColor field to cart_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item ADD selected_color VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item DROP COLUMN selected_color');
    }
}
