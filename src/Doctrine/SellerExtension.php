<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Seller;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

class SellerExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private Security $security
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Seller::class) {
            return;
        }

        // Les admins peuvent voir tous les vendeurs, y compris les suspendus
        // Les autres utilisateurs ne voient que les vendeurs approuvés
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        
        $rootAlias = $queryBuilder->getRootAliases()[0];
        
        if (!$isAdmin) {
            // Filtrer pour ne montrer que les vendeurs approuvés aux non-admins
            $queryBuilder->andWhere("{$rootAlias}.status = :approvedStatus")
                ->setParameter('approvedStatus', 'approved');
        }
        
        // Exclure "InnovShop" (slug='innovshop') du marketplace pour tous les utilisateurs
        $queryBuilder->andWhere("{$rootAlias}.slug != :innovshopSlug")
            ->setParameter('innovshopSlug', 'innovshop');
        
        // Note: On ne fait plus de JOIN avec les produits ici car cela peut causer des problèmes
        // Les produits seront chargés via la relation Doctrine si nécessaire
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Seller::class) {
            return;
        }

        // Empêcher l'accès à "InnovShop" (slug='innovshop') même pour les admins
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$rootAlias}.slug != :innovshopSlug")
            ->setParameter('innovshopSlug', 'innovshop');

        // Les admins peuvent accéder à tous les vendeurs, y compris les suspendus
        // Les autres utilisateurs ne peuvent accéder qu'aux vendeurs approuvés
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        
        if (!$isAdmin) {
            // Filtrer pour ne permettre l'accès qu'aux vendeurs approuvés aux non-admins
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("{$rootAlias}.status = :approvedStatus")
                ->setParameter('approvedStatus', 'approved');
        }
        
        // Note: On ne fait plus de JOIN avec les produits ici car cela peut causer des problèmes
        // Les produits seront chargés via la relation Doctrine si nécessaire
    }
}

