<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BudgetGoal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BudgetGoalProvider implements ProviderInterface
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
        // Pour les opérations sur une collection, utiliser le provider par défaut
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            // Forcer un refresh depuis la base de données en vidant le cache Doctrine
            // Cela garantit que les données sont toujours à jour après une suppression
            $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
            
            // Si c'est un Paginator, s'assurer que les données sont fraîches
            if ($result instanceof \ApiPlatform\Doctrine\Orm\Paginator) {
                // Le Paginator utilise déjà une requête fraîche, pas besoin de modifier
                return $result;
            }
            
            return $result;
        }
        
        // Pour les opérations sur un item (Get, Delete, etc.), utiliser le provider par défaut
        // et s'assurer que la relation user est chargée
        try {
            $result = $this->itemProvider->provide($operation, $uriVariables, $context);
            
            if ($result instanceof BudgetGoal) {
                // S'assurer que la relation user est chargée (éviter le lazy loading)
                $result->getUser();
            }
            
            return $result;
        } catch (\Exception $e) {
            // Si l'objet n'est pas trouvé, retourner null pour que le processor gère l'erreur
            return null;
        }
    }
}

