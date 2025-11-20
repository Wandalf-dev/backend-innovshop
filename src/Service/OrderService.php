<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Divise une commande en plusieurs commandes (une par vendeur)
     * Retourne un tableau des nouvelles commandes créées
     * 
     * @param Order $originalOrder La commande originale à diviser
     * @return Order[] Les nouvelles commandes créées (une par vendeur)
     */
    public function splitOrderBySeller(Order $originalOrder): array
    {
        // Recharger la commande avec ses items depuis la base de données pour s'assurer qu'ils sont bien chargés
        $orderId = $originalOrder->getId();
        if (!$orderId) {
            error_log("[ERROR] Cannot split order without ID");
            return [$originalOrder];
        }
        
        $originalOrder = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')
            ->leftJoin('i.product', 'p')
            ->leftJoin('i.seller', 's')
            ->addSelect('i')
            ->addSelect('p')
            ->addSelect('s')
            ->where('o.id = :id')
            ->setParameter('id', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$originalOrder) {
            error_log("[ERROR] Order with ID {$orderId} not found");
            return [];
        }
        
        // Grouper les items par vendeur
        $itemsBySeller = [];
        $items = $originalOrder->getItems();
        
        if ($items->isEmpty()) {
            error_log("[WARNING] Order {$originalOrder->getReference()} has no items to split");
            return [$originalOrder];
        }
        
        error_log("[DEBUG] Order {$originalOrder->getReference()} has {$items->count()} items");
        
        foreach ($items as $item) {
            // Utiliser null comme clé pour les produits sans vendeur (InnovShop)
            $sellerId = $item->getSeller() ? $item->getSeller()->getId() : null;
            
            if (!isset($itemsBySeller[$sellerId])) {
                $itemsBySeller[$sellerId] = [];
            }
            $itemsBySeller[$sellerId][] = $item;
        }

        error_log("[DEBUG] Splitting order {$originalOrder->getReference()} into " . count($itemsBySeller) . " seller groups");

        // Si un seul vendeur (ou tous InnovShop), ne pas diviser
        if (count($itemsBySeller) <= 1) {
            error_log("[DEBUG] Only one seller group, no split needed");
            return [$originalOrder];
        }

        $newOrders = [];
        $originalReference = $originalOrder->getReference();
        $orderCounter = 1;

        // Créer une nouvelle commande pour chaque vendeur
        foreach ($itemsBySeller as $sellerId => $items) {
            $newOrder = new Order();
            
            // Copier les informations de base depuis la commande originale
            $newOrder->setUser($originalOrder->getUser());
            
            // Générer une référence unique pour chaque commande divisée
            // Format: REF-ORIGINALE-SUFFIXE (ex: INV-2025-123-1, INV-2025-123-2)
            $newReference = $originalReference . '-' . $orderCounter;
            
            // Vérifier que la référence n'existe pas déjà (au cas où)
            $existingOrder = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $newReference]);
            if ($existingOrder) {
                // Si la référence existe, ajouter un timestamp pour garantir l'unicité
                $newReference = $originalReference . '-' . $orderCounter . '-' . time();
            }
            
            $newOrder->setReference($newReference);
            $newOrder->setStatus($originalOrder->getStatus());
            $newOrder->setPaymentMethod($originalOrder->getPaymentMethod());
            $newOrder->setShippingMethod($originalOrder->getShippingMethod());
            $newOrder->setPaymentIntentId($originalOrder->getPaymentIntentId());
            $newOrder->setDeliveryFirstName($originalOrder->getDeliveryFirstName());
            $newOrder->setDeliveryLastName($originalOrder->getDeliveryLastName());
            $newOrder->setDeliveryStreet($originalOrder->getDeliveryStreet());
            $newOrder->setDeliveryZipCode($originalOrder->getDeliveryZipCode());
            $newOrder->setDeliveryCity($originalOrder->getDeliveryCity());
            $newOrder->setDeliveryCountry($originalOrder->getDeliveryCountry());
            $newOrder->setDeliveryPhone($originalOrder->getDeliveryPhone());
            $newOrder->setCart($originalOrder->getCart());
            
            // Copier la date de création de la commande originale
            // (PrePersist ne l'écrasera pas car il vérifie si createdAt est null)
            if ($originalOrder->getCreatedAt()) {
                $newOrder->setCreatedAt($originalOrder->getCreatedAt());
            }
            
            // Calculer le totalAmount pour cette commande
            $totalAmount = 0;
            $itemCount = 0;
            foreach ($items as $item) {
                // Retirer l'item de l'ancienne commande avant de l'ajouter à la nouvelle
                $originalOrder->removeItem($item);
                // Changer la relation vers la nouvelle commande
                $item->setOrder($newOrder);
                // Ajouter l'item à la nouvelle commande
                $newOrder->addItem($item);
                // Persister l'item pour s'assurer qu'il est bien sauvegardé
                $this->entityManager->persist($item);
                $totalAmount += (float)$item->getTotalLine();
                $itemCount++;
            }
            
            error_log("[DEBUG] Created order {$newReference} with {$itemCount} items, total: {$totalAmount}");
            
            $newOrder->setTotalAmount((string)$totalAmount);
            
            // Persister la nouvelle commande (et ses items)
            $this->entityManager->persist($newOrder);
            
            $newOrders[] = $newOrder;
            
            $orderCounter++;
        }

        // Flush les nouvelles commandes AVANT de supprimer l'ancienne
        // Cela garantit que les items sont bien sauvegardés avec leur nouvelle relation
        $this->entityManager->flush();
        
        error_log("[DEBUG] Flushed new orders, now removing original order {$originalOrder->getReference()}");
        
        // Maintenant supprimer la commande originale (qui ne devrait plus avoir d'items)
        $this->entityManager->remove($originalOrder);
        $this->entityManager->flush();
        
        error_log("[DEBUG] Order split completed. Created " . count($newOrders) . " new orders");

        return $newOrders;
    }
}

