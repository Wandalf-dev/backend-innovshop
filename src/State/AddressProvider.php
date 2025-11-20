<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AddressProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private Security $security,
        private EntityManagerInterface $em
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Vous devez être connecté pour accéder aux adresses.');
        }

        // Pour les opérations sur une collection, filtrer par utilisateur connecté
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            // Si l'utilisateur n'est pas admin, ajouter un filtre pour ne montrer que ses adresses
            if (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
                // Modifier le contexte pour ajouter un filtre sur l'utilisateur
                $context['filters'] = $context['filters'] ?? [];
                $context['filters']['user'] = '/api/users/' . $currentUser->getId();
            }
            
            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }
        
        // Pour les opérations sur un item (Get, etc.)
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);
        
        // Si l'utilisateur n'est pas admin, vérifier qu'il est le propriétaire
        if ($result instanceof Address && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            if ($result->getUser() !== $currentUser) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Vous n\'avez pas accès à cette adresse.');
            }
        }
        
        return $result;
    }
}

