<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderSecurityProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Order && ($operation instanceof Put || $operation instanceof Patch)) {
            // Recharger l'entité depuis la base de données pour s'assurer qu'elle est à jour
            $orderId = $data->getId();
            if ($orderId) {
                $freshOrder = $this->entityManager->getRepository(Order::class)->find($orderId);
                if ($freshOrder) {
                    // Copier les changements depuis $data vers $freshOrder
                    if ($data->getStatus() !== $freshOrder->getStatus()) {
                        $freshOrder->setStatus($data->getStatus());
                    }
                    $data = $freshOrder;
                }
            }

            /** @var \App\Entity\User|null $user */
            $user = $this->security->getUser();
            
            if (!$user) {
                // Logger pour déboguer
                error_log('OrderSecurityProcessor: User is null. Token might not be properly authenticated.');
                throw new \RuntimeException('Vous devez être connecté pour modifier une commande.');
            }

            // Si l'utilisateur est admin, autoriser la modification
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            }

            // Si l'utilisateur est un vendeur
            $seller = $user->getSeller();
            if ($seller && $seller->getStatus() === 'approved') {
                // Compter les vendeurs uniques dans la commande
                $uniqueSellers = [];
                foreach ($data->getItems() as $item) {
                    $itemSeller = $item->getSeller();
                    $sellerId = $itemSeller ? $itemSeller->getId() : null;
                    if (!in_array($sellerId, $uniqueSellers, true)) {
                        $uniqueSellers[] = $sellerId;
                    }
                }
                
                // Si la commande a plusieurs vendeurs, le vendeur ne peut PAS modifier le statut de la commande
                // Il doit modifier le statut de son lot via OrderSellerLot
                if (count($uniqueSellers) > 1) {
                    // Vérifier si le vendeur essaie de modifier le statut de la commande
                    $changes = $this->entityManager->getUnitOfWork()->getEntityChangeSet($data);
                    if (isset($changes['status'])) {
                        throw new \RuntimeException('Vous ne pouvez pas modifier le statut de la commande. Veuillez modifier le statut de votre lot de produits via l\'API des lots de vendeurs.');
                    }
                    // Pour les autres champs, vérifier qu'ils appartiennent à ce vendeur
                    foreach ($data->getItems() as $item) {
                        $itemSeller = $item->getSeller();
                        if ($itemSeller && $itemSeller->getId() !== $seller->getId()) {
                            throw new \RuntimeException('Vous ne pouvez modifier que les commandes contenant vos produits.');
                        }
                    }
                } else {
                    // Si un seul vendeur, comportement classique : vérifier que tous les items appartiennent à ce vendeur
                    $allItemsBelongToSeller = true;
                    foreach ($data->getItems() as $item) {
                        $itemSeller = $item->getSeller();
                        // Si l'item n'a pas de vendeur (InnovShop) ou si ce n'est pas le vendeur connecté
                        if ($itemSeller === null || $itemSeller->getId() !== $seller->getId()) {
                            $allItemsBelongToSeller = false;
                            break;
                        }
                    }

                    if (!$allItemsBelongToSeller) {
                        throw new \RuntimeException('Vous ne pouvez modifier que les commandes contenant vos produits.');
                    }
                }
            } else {
                // Si l'utilisateur n'est ni admin ni vendeur approuvé, vérifier qu'il est le propriétaire
                if ($data->getUser() !== $user) {
                    throw new \RuntimeException('Vous ne pouvez modifier que vos propres commandes.');
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

