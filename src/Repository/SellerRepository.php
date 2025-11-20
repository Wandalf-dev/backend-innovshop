<?php

namespace App\Repository;

use App\Entity\Seller;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Seller>
 */
class SellerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seller::class);
    }

    /**
     * Récupère tous les vendeurs approuvés
     * Exclut "InnovShop" (slug='innovshop') du marketplace
     */
    public function findApproved(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.slug != :innovshopSlug')
            ->setParameter('status', 'approved')
            ->setParameter('innovshopSlug', 'innovshop')
            ->orderBy('s.ratingAverage', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère un vendeur par son slug
     * Exclut "InnovShop" (slug='innovshop') du marketplace
     */
    public function findOneBySlug(string $slug): ?Seller
    {
        // Empêcher l'accès à "InnovShop"
        if ($slug === 'innovshop') {
            return null;
        }
        
        return $this->createQueryBuilder('s')
            ->where('s.slug = :slug')
            ->andWhere('s.status = :status')
            ->andWhere('s.slug != :innovshopSlug')
            ->setParameter('slug', $slug)
            ->setParameter('status', 'approved')
            ->setParameter('innovshopSlug', 'innovshop')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le vendeur associé à un user
     */
    public function findByUser(int $userId): ?Seller
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche de vendeurs avec filtres
     */
    public function search(?string $query = null, ?string $country = null, ?float $minRating = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.slug != :innovshopSlug')
            ->setParameter('status', 'approved')
            ->setParameter('innovshopSlug', 'innovshop');

        if ($query) {
            $qb->andWhere('s.shopName LIKE :query OR s.description LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($country) {
            $qb->andWhere('s.country = :country')
                ->setParameter('country', $country);
        }

        if ($minRating !== null) {
            $qb->andWhere('s.ratingAverage >= :minRating')
                ->setParameter('minRating', $minRating);
        }

        return $qb->orderBy('s.ratingAverage', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la note moyenne d'un vendeur
     */
    public function updateRating(Seller $seller, float $newRating): void
    {
        $currentAverage = (float) $seller->getRatingAverage();
        $currentCount = $seller->getRatingCount();

        $newAverage = (($currentAverage * $currentCount) + $newRating) / ($currentCount + 1);

        $seller->setRatingAverage(number_format($newAverage, 2));
        $seller->setRatingCount($currentCount + 1);

        $this->getEntityManager()->flush();
    }
}
