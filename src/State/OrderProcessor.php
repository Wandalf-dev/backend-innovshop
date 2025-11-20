<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderSellerLot;
use App\Entity\Cart;
use App\Service\BudgetGoalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private BudgetGoalService $budgetGoalService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Order) {
            // Récupérer le panier depuis la base de données
            $cart = $data->getCart();
            
            if ($cart && $data->getItems()->isEmpty()) {
                // Forcer le chargement du panier complet
                $cartId = $cart->getId();
                $fullCart = $this->entityManager->getRepository(Cart::class)->createQueryBuilder('c')
                    ->leftJoin('c.items', 'ci')
                    ->leftJoin('ci.product', 'p')
                    ->addSelect('ci', 'p')
                    ->where('c.id = :cartId')
                    ->setParameter('cartId', $cartId)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($fullCart && $fullCart->getItems()->count() > 0) {
                    // Copier chaque item du panier vers OrderItem
                    foreach ($fullCart->getItems() as $cartItem) {
                        $orderItem = new OrderItem();
                        $orderItem->setOrder($data);
                        $orderItem->setProduct($cartItem->getProduct());
                        $orderItem->setProductName($cartItem->getProduct()->getName());
                        $orderItem->setUnitPrice($cartItem->getUnitPrice());
                        $orderItem->setQuantity($cartItem->getQuantity());
                        $orderItem->setTotalLine(
                            (string)((float)$cartItem->getUnitPrice() * $cartItem->getQuantity())
                        );
                        
                        // 🆕 Copier le seller_id du produit vers l'order_item
                        if ($cartItem->getProduct()->getSeller()) {
                            $orderItem->setSeller($cartItem->getProduct()->getSeller());
                        }
                        
                        // Copier selectedColor et selectedSize depuis CartItem
                        $orderItem->setSelectedColor($cartItem->getSelectedColor());
                        $orderItem->setSelectedSize($cartItem->getSelectedSize());
                        
                        $data->addItem($orderItem);
                        $this->entityManager->persist($orderItem);
                    }
                }
            }

            // Persister la commande
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            // Créer les lots de vendeurs (un lot par vendeur unique dans la commande)
            // La méthode createSellerLots vérifie déjà si des lots existent
            $this->createSellerLots($data);

            // Mettre à jour les objectifs budgétaires
            if ($data->getCreatedAt()) {
                $this->budgetGoalService->updateBudgetGoalsForOrder($data);
            }
            
            return $result;
        }

        // Appeler le processor par défaut pour persister
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Crée les lots de vendeurs pour une commande
     * Un lot est créé pour chaque vendeur unique (ou null pour InnovShop)
     */
    private function createSellerLots(Order $order): void
    {
        // Si des lots existent déjà, ne pas en créer de nouveaux
        if ($order->getSellerLots()->count() > 0) {
            return;
        }

        // Grouper les items par vendeur
        $itemsBySeller = [];
        foreach ($order->getItems() as $item) {
            $sellerId = $item->getSeller() ? $item->getSeller()->getId() : null;
            if (!isset($itemsBySeller[$sellerId])) {
                $itemsBySeller[$sellerId] = [];
            }
            $itemsBySeller[$sellerId][] = $item;
        }

        // Créer un lot pour chaque vendeur unique
        foreach ($itemsBySeller as $sellerId => $items) {
            $lot = new OrderSellerLot();
            $lot->setOrder($order);
            $lot->setStatus('confirmed');
            
            // Si sellerId est null, le lot représente InnovShop
            if ($sellerId !== null) {
                $seller = $items[0]->getSeller();
                if ($seller) {
                    $lot->setSeller($seller);
                }
            }
            // Si sellerId est null, le seller reste null (InnovShop)
            
            $order->addSellerLot($lot);
            $this->entityManager->persist($lot);
        }

        $this->entityManager->flush();
    }
}
