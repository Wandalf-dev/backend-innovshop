<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Address;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AddressProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Address) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            throw new \RuntimeException('Vous devez être connecté pour créer ou modifier une adresse.');
        }

        // Pour les opérations POST (création), assigner automatiquement l'utilisateur connecté
        if ($operation instanceof Post) {
            // Toujours assigner l'utilisateur connecté, même si un autre utilisateur est fourni
            $data->setUser($currentUser);
        }

        // Pour les opérations PUT/PATCH (modification), vérifier que l'utilisateur est le propriétaire
        if ($operation instanceof Put || $operation instanceof Patch) {
            $existingAddress = $this->entityManager->getRepository(Address::class)->find($data->getId());
            
            if ($existingAddress && $existingAddress->getUser() !== $currentUser) {
                // Vérifier si l'utilisateur est admin
                $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
                
                if (!$isAdmin) {
                    throw new \RuntimeException('Vous ne pouvez modifier que vos propres adresses.');
                }
            }
            
            // S'assurer que l'utilisateur de l'adresse ne peut pas être changé (sauf par admin)
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                // Si l'adresse existe déjà, conserver son utilisateur d'origine
                if ($existingAddress) {
                    $data->setUser($existingAddress->getUser());
                } else {
                    // Si c'est une nouvelle adresse, assigner l'utilisateur connecté
                    $data->setUser($currentUser);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

