<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251116000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for seller dashboard queries';
    }

    public function up(Schema $schema): void
    {
        // Index pour product.seller_id (déjà existant normalement)
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PRODUCT_SELLER ON product(seller_id)');
        
        // Index pour order_item.seller_id (déjà existant normalement)
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ORDER_ITEM_SELLER ON order_item(seller_id)');
        
        // Index composite pour les requêtes de dashboard
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PRODUCT_SELLER_CREATED ON product(seller_id, created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ORDER_ITEM_SELLER_ORDER ON order_item(seller_id, order_id)');
        
        // Index pour product_image.product_id et position
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PRODUCT_IMAGE_PRODUCT_POS ON product_image(product_id, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_PRODUCT_SELLER');
        $this->addSql('DROP INDEX IF EXISTS IDX_ORDER_ITEM_SELLER');
        $this->addSql('DROP INDEX IF EXISTS IDX_PRODUCT_SELLER_CREATED');
        $this->addSql('DROP INDEX IF EXISTS IDX_ORDER_ITEM_SELLER_ORDER');
        $this->addSql('DROP INDEX IF EXISTS IDX_PRODUCT_IMAGE_PRODUCT_POS');
    }
}
