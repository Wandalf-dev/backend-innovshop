<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrderSellerLot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderSellerLotProcessor implements ProcessorInterface
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
        if (!$data instanceof OrderSellerLot) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour modifier un lot de commande.');
        }

        // Admin peut tout faire
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Récupérer l'état original du lot si c'est une mise à jour
        $originalLot = null;
        if ($data->getId()) {
            $originalLot = $this->entityManager->getRepository(OrderSellerLot::class)->find($data->getId());
            if (!$originalLot) {
                throw new \RuntimeException('Lot de commande introuvable.');
            }
        }

        // Vendeur : peut modifier le statut de son propre lot uniquement
        if ($this->security->isGranted('ROLE_SELLER')) {
            $seller = $currentUser->getSeller();
            if (!$seller) {
                throw new AccessDeniedHttpException('Vous n\'êtes pas un vendeur approuvé.');
            }

            // Vérifier que le lot appartient à ce vendeur
            $lotSeller = $data->getSeller();
            if ($lotSeller === null || $lotSeller->getId() !== $seller->getId()) {
                throw new AccessDeniedHttpException('Vous ne pouvez modifier que les lots de vos propres produits.');
            }

            // Vérifier les champs modifiés
            if ($originalLot) {
                $changes = $this->entityManager->getUnitOfWork()->getEntityChangeSet($data);
                
                foreach ($changes as $field => $values) {
                    if ($field === 'status') {
                        $oldStatus = $values[0];
                        $newStatus = $values[1];
                        
                        // Un vendeur ne peut passer le statut qu'à 'shipped' ou 'delivered'
                        if (!in_array($newStatus, ['shipped', 'delivered'])) {
                            throw new BadRequestHttpException('Un vendeur ne peut changer le statut qu\'à "expédié" ou "livré".');
                        }
                        // Et ne peut pas revenir en arrière (ex: delivered -> shipped)
                        if ($oldStatus === 'delivered' && $newStatus === 'shipped') {
                            throw new BadRequestHttpException('Impossible de revenir en arrière sur le statut du lot.');
                        }
                        // Ne peut pas passer de confirmed à delivered directement
                        if ($oldStatus === 'confirmed' && $newStatus === 'delivered') {
                            throw new BadRequestHttpException('Le lot doit d\'abord être "expédié" avant d\'être "livré".');
                        }
                    } elseif (!in_array($field, ['updatedAt'])) { // updatedAt est autorisé
                        throw new AccessDeniedHttpException(sprintf('Les vendeurs ne peuvent pas modifier le champ "%s".', $field));
                    }
                }
            }
            
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Utilisateur normal : peut annuler son propre lot (si la commande lui appartient)
        if ($data->getOrder() && $data->getOrder()->getUser() === $currentUser) {
            if ($originalLot) {
                $changes = $this->entityManager->getUnitOfWork()->getEntityChangeSet($data);
                
                // L'utilisateur ne peut modifier que le statut pour annuler
                if (count($changes) === 1 && isset($changes['status'])) {
                    $oldStatus = $changes['status'][0];
                    $newStatus = $changes['status'][1];

                    if ($newStatus === 'cancelled' && $oldStatus === 'confirmed') {
                        // Autoriser l'annulation si le lot est confirmé
                        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
                    }
                }
                throw new AccessDeniedHttpException('Vous ne pouvez annuler que vos propres lots confirmés.');
            }
        }

        throw new AccessDeniedHttpException('Accès non autorisé à ce lot de commande.');
    }
}

