-- ============================================================================
-- CORRECTION DES INDEX POUR SYNCHRONISATION DOCTRINE
-- ============================================================================

-- Corriger les index order_item
DROP INDEX IF EXISTS IDX_SELLER_ORDER_DATE ON order_item;
DROP INDEX IF EXISTS idx_order_item_seller ON order_item;
ALTER TABLE order_item ADD INDEX IDX_52EA1F098DE820D9 (seller_id);

-- Corriger les index product
DROP INDEX IF EXISTS IDX_SELLER_PUBLISHED ON product;
DROP INDEX IF EXISTS idx_product_seller ON product;
ALTER TABLE product ADD INDEX IDX_D34A04AD8DE820D9 (seller_id);

-- Supprimer les index seller qui posent problème
DROP INDEX IF EXISTS IDX_SELLER_STATUS ON seller;
DROP INDEX IF EXISTS IDX_SELLER_RATING ON seller;

-- ============================================================================
-- FIN DE LA CORRECTION
-- ============================================================================
