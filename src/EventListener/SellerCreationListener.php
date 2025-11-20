<?php

namespace App\EventListener;

use App\Entity\Seller;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Seller::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Seller::class)]
class SellerCreationListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function prePersist(Seller $seller, PrePersistEventArgs $event): void
    {
        // Définir le statut initial à 'pending' si non défini
        if ($seller->getStatus() === null) {
            $seller->setStatus('pending');
        }
    }

    public function postPersist(Seller $seller, PostPersistEventArgs $event): void
    {
        // Ajouter ROLE_SELLER dès la création du compte vendeur (même en attente)
        // Cela permet au frontend d'afficher correctement "Dashboard" et de masquer le panier
        $user = $seller->getUser();
        if ($user) {
            // Obtenir les rôles bruts depuis la propriété directement
            $reflection = new \ReflectionClass($user);
            $rolesProperty = $reflection->getProperty('roles');
            $rolesProperty->setAccessible(true);
            $rawRoles = $rolesProperty->getValue($user);
            
            // Ajouter ROLE_SELLER s'il n'est pas déjà présent
            if (!in_array('ROLE_SELLER', $rawRoles, true)) {
                $rawRoles[] = 'ROLE_SELLER';
                
                // Si l'utilisateur n'est PAS admin, retirer ROLE_USER
                // Un admin peut avoir ROLE_USER + ROLE_SELLER + ROLE_ADMIN
                if (!in_array('ROLE_ADMIN', $rawRoles, true)) {
                    $rawRoles = array_filter($rawRoles, fn($role) => $role !== 'ROLE_USER');
                    $rawRoles = array_values($rawRoles);
                }
                
                // Utiliser une requête SQL directe pour garantir la mise à jour
                $connection = $this->entityManager->getConnection();
                $rolesJson = json_encode($rawRoles);
                
                error_log(sprintf('[SellerCreationListener] Adding ROLE_SELLER to user %d on seller creation. New roles (raw): %s', $user->getId(), $rolesJson));
                
                $connection->executeStatement(
                    'UPDATE `user` SET roles = :roles WHERE id = :id',
                    ['roles' => $rolesJson, 'id' => $user->getId()],
                    ['roles' => \PDO::PARAM_STR, 'id' => \PDO::PARAM_INT]
                );
                
                // Recharger le User depuis la DB pour vérifier
                $this->entityManager->refresh($user);
                
                $rolesAfterFlush = $user->getRoles();
                error_log(sprintf('[SellerCreationListener] User %d roles after SQL update: %s', $user->getId(), json_encode($rolesAfterFlush)));
            }
        }
    }
}
