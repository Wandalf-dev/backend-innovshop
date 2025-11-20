<?php

namespace App\EventListener;

use App\Entity\ProductReview;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductReview::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: ProductReview::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: ProductReview::class)]
class ProductReviewListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function postPersist(ProductReview $review, PostPersistEventArgs $event): void
    {
        $this->updateProductRatings($review->getProduct());
    }

    public function postUpdate(ProductReview $review, PostUpdateEventArgs $event): void
    {
        $this->updateProductRatings($review->getProduct());
    }

    public function postRemove(ProductReview $review, PostRemoveEventArgs $event): void
    {
        $this->updateProductRatings($review->getProduct());
    }

    private function updateProductRatings(?Product $product): void
    {
        if (!$product) {
            return;
        }

        // Calculer la moyenne et le nombre d'avis
        $reviews = $this->entityManager
            ->getRepository(ProductReview::class)
            ->createQueryBuilder('r')
            ->where('r.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();

        $count = count($reviews);
        
        if ($count === 0) {
            $product->setRatingAverage(null);
            $product->setRatingCount(0);
        } else {
            $sum = array_sum(array_map(fn($r) => $r->getRating(), $reviews));
            $average = round($sum / $count, 2);
            $product->setRatingAverage((string)$average);
            $product->setRatingCount($count);
        }

        $this->entityManager->flush();
    }
}

