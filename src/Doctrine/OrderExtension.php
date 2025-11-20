<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Order;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class OrderExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Vérifier si un filtre user est présent dans le contexte
        $filters = $context['filters'] ?? [];
        $hasUserFilter = isset($filters['user']) || isset($filters['user.id']);

        // Si un filtre user est présent dans le contexte, vérifier qu'il correspond à l'utilisateur connecté
        // et forcer son application même pour les admins (pour /account)
        if ($hasUserFilter) {
            $userFilterValue = $filters['user'] ?? $filters['user.id'] ?? null;
            if ($userFilterValue) {
                // Si c'est un IRI, extraire l'ID
                if (is_string($userFilterValue) && preg_match('#/api/users/(\d+)#', $userFilterValue, $matches)) {
                    $filteredUserId = (int)$matches[1];
                    // Si l'admin demande ses propres commandes, forcer le filtre pour s'assurer qu'il est appliqué
                    if ($isAdmin && $filteredUserId === $user->getId()) {
                        $queryBuilder->andWhere("{$rootAlias}.user = :current_user")
                            ->setParameter('current_user', $user);
                        return;
                    }
                }
            }
            // Le filtre est déjà appliqué par API Platform via SearchFilter, on ne fait rien de plus
            return;
        }

        // Si pas de filtre user et que l'utilisateur n'est pas admin, forcer le filtre
        if (!$isAdmin) {
            $queryBuilder->andWhere("{$rootAlias}.user = :current_user")
                ->setParameter('current_user', $user);
        }
        // Si admin et pas de filtre user, il peut voir toutes les commandes (pour /admin/orders)
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Pour les items individuels, les admins peuvent toujours y accéder
        // Mais pour les non-admins, vérifier que c'est leur commande
        if (!$isAdmin) {
            $queryBuilder->andWhere("{$rootAlias}.user = :current_user")
                ->setParameter('current_user', $user);
        }
    }
}

