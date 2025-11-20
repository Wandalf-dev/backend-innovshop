<?php

namespace App\EventListener;

use App\Entity\Seller;
use App\Entity\Product;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Seller::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Seller::class)]
class SellerStatusListener
{
    private EmailService $emailService;
    private ?string $pendingStatus = null;
    private ?string $oldStatus = null;

    public function __construct(
        EmailService $emailService,
        private EntityManagerInterface $entityManager
    ) {
        $this->emailService = $emailService;
    }

    public function preUpdate(Seller $seller, PreUpdateEventArgs $event): void
    {
        // Vérifier si le status a changé
        if ($event->hasChangedField('status')) {
            $oldStatus = $event->getOldValue('status');
            $newStatus = $event->getNewValue('status');
            
            // Stocker l'ancien statut pour l'utiliser dans postUpdate
            $this->oldStatus = $oldStatus;
            
            $user = $seller->getUser();
            if (!$user) {
                return;
            }

            // Obtenir les rôles bruts depuis la propriété directement
            // On utilise la réflexion pour accéder à la propriété privée
            $reflection = new \ReflectionClass($user);
            $rolesProperty = $reflection->getProperty('roles');
            $rolesProperty->setAccessible(true);
            $rawRoles = $rolesProperty->getValue($user);
            
            $rolesChanged = false;

            // Si le vendeur est approuvé, ajouter ROLE_SELLER
            if ($newStatus === 'approved' && $oldStatus !== 'approved') {
                if (!in_array('ROLE_SELLER', $rawRoles, true)) {
                    $rawRoles[] = 'ROLE_SELLER';
                    // Si l'utilisateur n'est PAS admin, retirer ROLE_USER
                    // Un admin peut avoir ROLE_USER + ROLE_SELLER + ROLE_ADMIN
                    if (!in_array('ROLE_ADMIN', $rawRoles, true)) {
                        $rawRoles = array_filter($rawRoles, fn($role) => $role !== 'ROLE_USER');
                        $rawRoles = array_values($rawRoles);
                    }
                    $user->setRoles($rawRoles);
                    $rolesChanged = true;
                    error_log(sprintf('[SellerStatusListener] Adding ROLE_SELLER to user %d. New roles (raw): %s', $user->getId(), json_encode($rawRoles)));
                }
                // Stocker le statut pour l'envoi d'email après la mise à jour
                $this->pendingStatus = 'approved';
            }

            // Si le vendeur est suspendu ou rejeté, retirer ROLE_SELLER
            // Après retrait, l'utilisateur n'aura plus de rôle spécial, donc getRoles() ajoutera ROLE_USER
            if (in_array($newStatus, ['suspended', 'rejected'], true) && $oldStatus === 'approved') {
                $newRoles = array_filter($rawRoles, fn($role) => $role !== 'ROLE_SELLER');
                $newRoles = array_values($newRoles);
                // Si l'utilisateur n'a plus de rôle spécial, on laisse getRoles() ajouter ROLE_USER automatiquement
                // Mais on stocke un tableau vide pour que getRoles() puisse ajouter ROLE_USER
                $user->setRoles($newRoles);
                $rolesChanged = true;
                error_log(sprintf('[SellerStatusListener] Removing ROLE_SELLER from user %d. New roles (raw): %s', $user->getId(), json_encode($newRoles)));
            }
            
            // Si les rôles ont changé, forcer Doctrine à détecter le changement
            if ($rolesChanged) {
                $em = $event->getObjectManager();
                $uow = $em->getUnitOfWork();
                $classMetadata = $em->getClassMetadata(\App\Entity\User::class);
                
                // Recalculer le change set pour le User
                $uow->recomputeSingleEntityChangeSet($classMetadata, $user);
                
                // Si le User n'est pas dans l'unité de travail, l'ajouter
                if (!$uow->isScheduledForUpdate($user)) {
                    $uow->scheduleForUpdate($user);
                }
                
                error_log(sprintf('[SellerStatusListener] PreUpdate - User %d scheduled for update. Roles: %s', $user->getId(), json_encode($user->getRoles())));
            }
        }
    }

    public function postUpdate(Seller $seller, PostUpdateEventArgs $event): void
    {
        // S'assurer que les modifications du User (rôles) sont bien flushées
        $user = $seller->getUser();
        if ($user) {
            $currentRoles = $user->getRoles();
            error_log(sprintf('[SellerStatusListener] PostUpdate - User %d current roles before flush: %s', $user->getId(), json_encode($currentRoles)));
            
            // Utiliser une requête SQL directe pour garantir la mise à jour
            // car le User pourrait ne pas être dans l'unité de travail
            $connection = $this->entityManager->getConnection();
            
            // Obtenir les rôles bruts depuis la propriété directement
            // On utilise la réflexion pour accéder à la propriété privée
            $reflection = new \ReflectionClass($user);
            $rolesProperty = $reflection->getProperty('roles');
            $rolesProperty->setAccessible(true);
            $rawRoles = $rolesProperty->getValue($user);
            
            // Si l'utilisateur n'est PAS admin, retirer ROLE_USER
            // Un admin peut avoir ROLE_USER + ROLE_SELLER + ROLE_ADMIN
            if (!in_array('ROLE_ADMIN', $rawRoles, true)) {
                $rawRoles = array_filter($rawRoles, fn($role) => $role !== 'ROLE_USER');
                $rawRoles = array_values($rawRoles);
            }
            
            $rolesJson = json_encode($rawRoles);
            error_log(sprintf('[SellerStatusListener] PostUpdate - Updating user %d with roles JSON: %s', $user->getId(), $rolesJson));
            
            $connection->executeStatement(
                'UPDATE `user` SET roles = :roles WHERE id = :id',
                ['roles' => $rolesJson, 'id' => $user->getId()],
                ['roles' => \PDO::PARAM_STR, 'id' => \PDO::PARAM_INT]
            );
            
            // Recharger le User depuis la DB pour vérifier
            $this->entityManager->refresh($user);
            $rolesAfterFlush = $user->getRoles();
            error_log(sprintf('[SellerStatusListener] PostUpdate - User %d roles after SQL update: %s', $user->getId(), json_encode($rolesAfterFlush)));
        }

        // Si le vendeur vient d'être suspendu, dépublicier tous ses produits
        if ($seller->getStatus() === 'suspended' && $this->oldStatus === 'approved') {
            $products = $this->entityManager
                ->getRepository(Product::class)
                ->createQueryBuilder('p')
                ->where('p.seller = :seller')
                ->andWhere('p.isPublished = :published')
                ->setParameter('seller', $seller)
                ->setParameter('published', true)
                ->getQuery()
                ->getResult();

            foreach ($products as $product) {
                $product->setIsPublished(false);
            }
            
            $this->entityManager->flush();
        }

        // Si le vendeur vient d'être réactivé (suspended → approved), republier tous ses produits
        if ($seller->getStatus() === 'approved' && $this->oldStatus === 'suspended') {
            $products = $this->entityManager
                ->getRepository(Product::class)
                ->createQueryBuilder('p')
                ->where('p.seller = :seller')
                ->setParameter('seller', $seller)
                ->getQuery()
                ->getResult();

            foreach ($products as $product) {
                // Republier tous les produits du vendeur lors de la réactivation
                $product->setIsPublished(true);
            }
            
            $this->entityManager->flush();
        }

        // Envoyer l'email après la mise à jour si le vendeur a été approuvé
        if ($this->pendingStatus === 'approved') {
            $this->emailService->sendSellerApprovalNotification($seller);
            $this->pendingStatus = null; // Réinitialiser
        }
        
        // Réinitialiser l'ancien statut
        $this->oldStatus = null;
    }
}
