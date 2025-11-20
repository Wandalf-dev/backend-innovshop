-- ============================================================================
-- MIGRATION INNOVSHOP VERS MARKETPLACE MULTI-VENDEURS
-- Date: 15 novembre 2025
-- Description: Ajout de la gestion des vendeurs tiers (seller)
-- ============================================================================

-- ============================================================================
-- 1. CRÉATION DE LA TABLE SELLER
-- ============================================================================

CREATE TABLE `seller` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` longtext DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `status` enum('pending','approved','suspended') NOT NULL DEFAULT 'pending',
  `rating_average` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_FB1AD3FC989D9B62` (`slug`),
  UNIQUE KEY `UNIQ_FB1AD3FCA76ED395` (`user_id`),
  KEY `IDX_SELLER_STATUS` (`status`),
  KEY `IDX_SELLER_RATING` (`rating_average`),
  CONSTRAINT `FK_SELLER_USER` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- JUSTIFICATION DES CHOIX POUR LA TABLE SELLER
-- ============================================================================
-- 
-- • UNIQUE KEY sur user_id : Un user ne peut avoir qu'une seule boutique vendeur
--   (évolutif vers plusieurs boutiques si besoin futur via suppression de cette contrainte)
--
-- • UNIQUE KEY sur slug : Pour des URLs uniques /marketplace/{slug}
--
-- • INDEX sur status : Pour filtrer rapidement les vendeurs approuvés/suspendus
--   Requête type: SELECT * FROM seller WHERE status = 'approved'
--
-- • INDEX sur rating_average : Pour trier/filtrer les vendeurs par note
--   Requête type: SELECT * FROM seller ORDER BY rating_average DESC
--
-- • ON DELETE RESTRICT sur FK user_id : Si on supprime un user, on REFUSE
--   la suppression tant que le seller existe. Cela force à :
--   1. Suspendre le seller d'abord
--   2. Gérer la transition des produits
--   3. Puis supprimer le seller
--   4. Enfin supprimer le user
--   → Évite la perte accidentelle de données vendeur
--
-- • rating_average decimal(3,2) : Format 0.00 à 9.99 (peut monter à 99.99)
--   Permet de stocker des notes type 4.73/5 ou 9.2/10
--
-- • iban varchar(34) : Longueur max d'un IBAN international (34 caractères)
--
-- ============================================================================


-- ============================================================================
-- 2. MODIFICATION DE LA TABLE PRODUCT - AJOUT DU VENDEUR
-- ============================================================================

ALTER TABLE `product` 
  ADD COLUMN `seller_id` int(11) DEFAULT NULL AFTER `category_id`;

ALTER TABLE `product`
  ADD KEY `IDX_PRODUCT_SELLER` (`seller_id`);

ALTER TABLE `product`
  ADD CONSTRAINT `FK_PRODUCT_SELLER` 
  FOREIGN KEY (`seller_id`) 
  REFERENCES `seller` (`id`) 
  ON DELETE SET NULL;

-- ============================================================================
-- JUSTIFICATION DES CHOIX POUR PRODUCT.SELLER_ID
-- ============================================================================
--
-- • seller_id NULL : Produit vendu directement par InnovShop (la plateforme)
--   seller_id = X : Produit vendu par le vendeur tiers X
--
-- • INDEX sur seller_id : Pour retrouver rapidement tous les produits d'un vendeur
--   Requête type: SELECT * FROM product WHERE seller_id = 5
--   Requête type: SELECT * FROM product WHERE seller_id IS NULL (produits InnovShop)
--
-- • ON DELETE SET NULL : Si un seller est supprimé, ses produits restent mais
--   deviennent des produits "orphelins" (seller_id = NULL).
--   → Permet de conserver l'historique des produits
--   → InnovShop peut récupérer ces produits ou les dépublier
--   → Évite la suppression en cascade qui effacerait l'historique des commandes
--   
--   Alternative considérée mais rejetée:
--   - ON DELETE CASCADE : Supprimerait les produits → perte d'historique commandes
--   - ON DELETE RESTRICT : Empêcherait la suppression du vendeur → trop rigide
--
-- ============================================================================


-- ============================================================================
-- 3. MODIFICATION DE LA TABLE ORDER_ITEM - AJOUT DU VENDEUR (DÉNORMALISATION)
-- ============================================================================

ALTER TABLE `order_item` 
  ADD COLUMN `seller_id` int(11) DEFAULT NULL AFTER `product_id`;

ALTER TABLE `order_item`
  ADD KEY `IDX_ORDER_ITEM_SELLER` (`seller_id`);

ALTER TABLE `order_item`
  ADD CONSTRAINT `FK_ORDER_ITEM_SELLER` 
  FOREIGN KEY (`seller_id`) 
  REFERENCES `seller` (`id`) 
  ON DELETE SET NULL;

-- ============================================================================
-- JUSTIFICATION DE LA DÉNORMALISATION DANS ORDER_ITEM
-- ============================================================================
--
-- ✅ RECOMMANDÉ : J'ai ajouté seller_id dans order_item
--
-- AVANTAGES :
-- • Performance : Requête directe pour le dashboard vendeur
--   Sans dénorm: SELECT * FROM order_item oi JOIN product p ON oi.product_id = p.id WHERE p.seller_id = 5
--   Avec dénorm: SELECT * FROM order_item WHERE seller_id = 5
--   → Évite une JOIN systématique, crucial pour les dashboards avec beaucoup de ventes
--
-- • Historique immuable : Si un produit change de vendeur (cas exceptionnel mais possible),
--   l'order_item garde le vendeur au moment de la vente
--   → Cohérence comptable et historique
--
-- • Simplicité des rapports : Pour calculer le CA d'un vendeur:
--   SELECT SUM(total_line) FROM order_item WHERE seller_id = 5
--   → Pas de JOIN, pas de complexité
--
-- • Gestion des produits supprimés : Si product_id pointe vers un produit supprimé,
--   on garde quand même l'info du vendeur dans l'order_item
--
-- INCONVÉNIENTS (mineurs) :
-- • Redondance : L'info existe dans product.seller_id
--   → Acceptable car order_item est déjà dénormalisé (product_name, unit_price)
--   → Cohérent avec la philosophie de l'historique de commande
--
-- • Mise à jour : Il faudra remplir seller_id lors de la création de l'order_item
--   → Simple: $orderItem->setSellerId($product->getSellerId())
--
-- • ON DELETE SET NULL : Si un seller est supprimé, ses order_item gardent
--   seller_id = NULL mais on peut retrouver le nom du vendeur via d'autres moyens
--   (logs, product_name contient souvent l'info, etc.)
--
-- CONCLUSION : La dénormalisation est fortement recommandée pour une marketplace
-- ============================================================================


-- ============================================================================
-- 4. INDEXES COMPOSITES RECOMMANDÉS (OPTIMISATIONS SUPPLÉMENTAIRES)
-- ============================================================================

-- Index composite pour retrouver les commandes d'un vendeur triées par date
ALTER TABLE `order_item`
  ADD KEY `IDX_SELLER_ORDER_DATE` (`seller_id`, `order_id`);

-- Index pour filtrer les produits publiés d'un vendeur
ALTER TABLE `product`
  ADD KEY `IDX_SELLER_PUBLISHED` (`seller_id`, `is_published`);

-- ============================================================================
-- JUSTIFICATION DES INDEX COMPOSITES
-- ============================================================================
--
-- • IDX_SELLER_ORDER_DATE : Permet les requêtes type:
--   SELECT oi.* FROM order_item oi 
--   JOIN `order` o ON oi.order_id = o.id 
--   WHERE oi.seller_id = 5 
--   ORDER BY o.created_at DESC
--   → Optimise le tri par date dans le dashboard vendeur
--
-- • IDX_SELLER_PUBLISHED : Permet les requêtes type:
--   SELECT * FROM product WHERE seller_id = 5 AND is_published = 1
--   → Optimise l'affichage de la boutique vendeur (produits publiés uniquement)
--
-- ============================================================================


-- ============================================================================
-- 5. MIGRATION DES DONNÉES EXISTANTES
-- ============================================================================

-- Tous les produits actuels appartiennent à InnovShop (seller_id = NULL)
-- Pas de migration nécessaire, la colonne est déjà NULL par défaut

-- Si vous voulez créer un "vendeur" InnovShop pour cohérence:
-- INSERT INTO seller (user_id, shop_name, slug, status, created_at) 
-- VALUES (1, 'InnovShop Official', 'innovshop-official', 'approved', NOW());

-- Puis mettre à jour les produits:
-- UPDATE product SET seller_id = 1 WHERE seller_id IS NULL;

-- ⚠️ NE PAS EXÉCUTER CES REQUÊTES SI VOUS VOULEZ GARDER LA LOGIQUE:
-- seller_id = NULL → Produits vendus directement par la plateforme


-- ============================================================================
-- 6. VÉRIFICATIONS POST-MIGRATION
-- ============================================================================

-- Vérifier que la table seller est créée
-- SHOW CREATE TABLE seller;

-- Vérifier que les colonnes seller_id sont ajoutées
-- DESCRIBE product;
-- DESCRIBE order_item;

-- Vérifier que les contraintes FK sont actives
-- SELECT * FROM information_schema.TABLE_CONSTRAINTS 
-- WHERE TABLE_SCHEMA = 'innovshop' AND TABLE_NAME IN ('product', 'order_item', 'seller');

-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================
