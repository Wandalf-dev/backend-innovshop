<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->security->getUser();
            
            // Vérifier si un filtre user est présent dans la requête
            $filters = $context['filters'] ?? [];
            $hasUserFilter = isset($filters['user']) || isset($filters['user.id']);
            
            // Si un filtre user est présent dans la requête, s'assurer qu'il est respecté même pour les admins
            // Cela permet aux admins de voir uniquement leurs propres commandes dans /account
            if ($hasUserFilter && $currentUser) {
                // Le filtre est déjà dans le contexte, on le laisse tel quel
                // API Platform l'appliquera automatiquement
            } elseif (!$hasUserFilter && $currentUser) {
                // Si pas de filtre user et que l'utilisateur n'est pas admin, forcer le filtre
                $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
                if (!$isAdmin) {
                    $context['filters'] = $context['filters'] ?? [];
                    $context['filters']['user'] = '/api/users/' . $currentUser->getId();
                }
                // Si admin et pas de filtre, il peut voir toutes les commandes (pour /admin/orders)
            }
            
            $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
            
            // Si c'est une collection, charger explicitement les sellerLots pour chaque commande
            if (is_iterable($result)) {
                foreach ($result as $order) {
                    if ($order instanceof Order) {
                        // Forcer le chargement des sellerLots
                        $order->getSellerLots()->count();
                    }
                }
            }
            
            return $result;
        }
        
        if ($operation instanceof Get) {
            $result = $this->itemProvider->provide($operation, $uriVariables, $context);
            
            if ($result instanceof Order) {
                // Charger explicitement les sellerLots avec leurs vendeurs
                $this->entityManager->getRepository(Order::class)
                    ->createQueryBuilder('o')
                    ->leftJoin('o.sellerLots', 'lot')
                    ->leftJoin('lot.seller', 'seller')
                    ->addSelect('lot')
                    ->addSelect('seller')
                    ->where('o.id = :id')
                    ->setParameter('id', $result->getId())
                    ->getQuery()
                    ->getResult();
                
                // Forcer le chargement
                $result->getSellerLots()->count();
            }
            
            return $result;
        }
        
        // Pour les autres opérations, utiliser le provider par défaut
        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }
}

