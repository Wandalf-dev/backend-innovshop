<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Product;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ProductExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Product::class) {
            return;
        }

        // Les admins peuvent voir tous les produits, même non publiés
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        
        if ($isAdmin) {
            // Les admins voient tous les produits, pas de filtre
            return;
        }

        // Filtrer les produits pour ne montrer que ceux qui sont publiés ET dont le vendeur est approuvé (ou pas de vendeur)
        $rootAlias = $queryBuilder->getRootAliases()[0];
        
        // Joindre avec le vendeur si ce n'est pas déjà fait
        $sellerAlias = $queryNameGenerator->generateJoinAlias('seller');
        $queryBuilder->leftJoin("{$rootAlias}.seller", $sellerAlias);
        
        // Filtrer : produit publié ET (pas de vendeur OU vendeur approuvé)
        $queryBuilder->andWhere(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq("{$rootAlias}.isPublished", ':isPublished'),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull("{$sellerAlias}.id"),
                    $queryBuilder->expr()->eq("{$sellerAlias}.status", ':sellerStatus')
                )
            )
        );
        
        $queryBuilder->setParameter('isPublished', true);
        $queryBuilder->setParameter('sellerStatus', 'approved');
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Product::class) {
            return;
        }

        // Pour les items individuels, on peut permettre l'accès même si le vendeur est suspendu
        // (pour que les admins puissent voir les produits suspendus)
        // Mais on peut aussi appliquer le même filtre si nécessaire
        // Pour l'instant, on laisse passer pour que les admins puissent voir
    }
}

