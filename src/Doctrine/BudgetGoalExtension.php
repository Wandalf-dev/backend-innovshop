<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\BudgetGoal;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class BudgetGoalExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== BudgetGoal::class) {
            return;
        }

        $user = $this->security->getUser();
        
        if (!$user) {
            // Si pas d'utilisateur connecté, ne rien retourner
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("1 = 0");
            return;
        }

        // Les admins peuvent voir tous les objectifs
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Filtrer pour ne montrer que les objectifs de l'utilisateur connecté
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$rootAlias}.user = :current_user")
            ->setParameter('current_user', $user);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== BudgetGoal::class) {
            return;
        }

        $user = $this->security->getUser();
        
        if (!$user) {
            // Si pas d'utilisateur connecté, ne rien retourner
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("1 = 0");
            return;
        }

        // Les admins peuvent accéder à tous les objectifs
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Filtrer pour ne montrer que les objectifs de l'utilisateur connecté
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$rootAlias}.user = :current_user")
            ->setParameter('current_user', $user);
    }
}

