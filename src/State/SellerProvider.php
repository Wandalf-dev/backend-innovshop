<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SellerProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private EntityManagerInterface $em
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Utiliser le provider par défaut pour récupérer les données
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
            
            // API Platform peut retourner différentes structures
            if ($result instanceof \ApiPlatform\Doctrine\Orm\Paginator) {
                // Paginator - enrichir chaque élément
                foreach ($result as $seller) {
                    if ($seller instanceof Seller) {
                        $this->enrichSellerWithProductCount($seller);
                    }
                }
            } elseif (is_array($result)) {
                // Tableau simple ou structure Hydra
                if (isset($result['hydra:member'])) {
                    // Structure Hydra
                    foreach ($result['hydra:member'] as $seller) {
                        if ($seller instanceof Seller) {
                            $this->enrichSellerWithProductCount($seller);
                        }
                    }
                } else {
                    // Tableau simple
                    foreach ($result as $seller) {
                        if ($seller instanceof Seller) {
                            $this->enrichSellerWithProductCount($seller);
                        }
                    }
                }
            } elseif ($result instanceof \Traversable) {
                // Iterator
                foreach ($result as $seller) {
                    if ($seller instanceof Seller) {
                        $this->enrichSellerWithProductCount($seller);
                    }
                }
            }
        } else {
            $result = $this->itemProvider->provide($operation, $uriVariables, $context);
            if ($result instanceof Seller) {
                $this->enrichSellerWithProductCount($result);
            }
        }

        return $result;
    }

    private function enrichSellerWithProductCount(Seller $seller): void
    {
        // Charger les produits avec un JOIN pour éviter le lazy loading
        $qb = $this->em->createQueryBuilder();
        $qb->select('p')
            ->from('App\Entity\Product', 'p')
            ->where('p.seller = :seller')
            ->andWhere('p.isPublished = :published')
            ->setParameter('seller', $seller)
            ->setParameter('published', true);

        $products = $qb->getQuery()->getResult();
        
        // Initialiser la collection avec les produits chargés
        foreach ($products as $product) {
            if (!$seller->getProducts()->contains($product)) {
                $seller->getProducts()->add($product);
            }
        }
    }
}

