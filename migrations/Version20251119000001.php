<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251119000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order_seller_lot table for tracking status per seller within an order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_seller_lot (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            seller_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'confirmed\',
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX IDX_ORDER_SELLER_LOT_ORDER (order_id),
            INDEX IDX_ORDER_SELLER_LOT_SELLER (seller_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE order_seller_lot 
            ADD CONSTRAINT FK_ORDER_SELLER_LOT_ORDER 
            FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE order_seller_lot 
            ADD CONSTRAINT FK_ORDER_SELLER_LOT_SELLER 
            FOREIGN KEY (seller_id) REFERENCES seller (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_seller_lot DROP FOREIGN KEY FK_ORDER_SELLER_LOT_ORDER');
        $this->addSql('ALTER TABLE order_seller_lot DROP FOREIGN KEY FK_ORDER_SELLER_LOT_SELLER');
        $this->addSql('DROP TABLE order_seller_lot');
    }
}

