<?php

namespace App\EventListener;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Product::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Product::class)]
class ProductFeaturedListener
{
    private const MAX_FEATURED_PRODUCTS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function postUpdate(Product $product, LifecycleEventArgs $event): void
    {
        $this->manageFeaturedProducts($product);
    }

    public function postPersist(Product $product, LifecycleEventArgs $event): void
    {
        $this->manageFeaturedProducts($product);
    }

    private function manageFeaturedProducts(Product $product): void
    {
        // Si le produit est mis à la une
        if ($product->isFeatured()) {
            // Récupérer tous les produits à la une, triés par date de mise à la une (plus ancien en premier)
            $featuredProducts = $this->entityManager
                ->getRepository(Product::class)
                ->createQueryBuilder('p')
                ->where('p.isFeatured = :featured')
                ->andWhere('p.id != :currentId')
                ->setParameter('featured', true)
                ->setParameter('currentId', $product->getId())
                ->orderBy('p.featuredAt', 'ASC')
                ->getQuery()
                ->getResult();

            // Si on a déjà 3 produits à la une ou plus, désactiver le(s) plus ancien(s)
            $count = count($featuredProducts);
            if ($count >= self::MAX_FEATURED_PRODUCTS) {
                $toDisable = $count - self::MAX_FEATURED_PRODUCTS + 1;
                for ($i = 0; $i < $toDisable; $i++) {
                    $featuredProducts[$i]->setIsFeatured(false);
                }
                $this->entityManager->flush();
            }
        }
    }
}
