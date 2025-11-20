-- ============================================================================
-- SYNCHRONISATION FINALE DOCTRINE - Corrections des types de colonnes
-- ============================================================================

-- Table address
ALTER TABLE address 
  MODIFY COLUMN phone VARCHAR(30) DEFAULT NULL,
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- Table budget_goal
ALTER TABLE budget_goal 
  MODIFY COLUMN start_date DATE DEFAULT NULL,
  MODIFY COLUMN end_date DATE DEFAULT NULL,
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- Table cart
ALTER TABLE cart 
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- Table category
ALTER TABLE category 
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- Table order
ALTER TABLE `order` 
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL,
  MODIFY COLUMN delivery_phone VARCHAR(30) DEFAULT NULL,
  MODIFY COLUMN payment_intent_id VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN payment_method VARCHAR(50) DEFAULT NULL,
  MODIFY COLUMN shipping_method VARCHAR(50) DEFAULT NULL;

-- Table product
ALTER TABLE product 
  MODIFY COLUMN color VARCHAR(50) DEFAULT NULL,
  MODIFY COLUMN size VARCHAR(50) DEFAULT NULL,
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL,
  MODIFY COLUMN featured_at DATETIME DEFAULT NULL;

-- Table product_image
ALTER TABLE product_image 
  MODIFY COLUMN alt_text VARCHAR(255) DEFAULT NULL;

-- Table seller
ALTER TABLE seller 
  MODIFY COLUMN logo_path VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN country VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN city VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN iban VARCHAR(34) DEFAULT NULL,
  MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending' NOT NULL,
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- Table user - IMPORTANT : Changer LONGTEXT en JSON
ALTER TABLE user 
  MODIFY COLUMN roles JSON NOT NULL,
  MODIFY COLUMN updated_at DATETIME DEFAULT NULL;

-- ============================================================================
-- FIN DE LA SYNCHRONISATION
-- ============================================================================
