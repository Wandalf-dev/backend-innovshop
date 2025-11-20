<?php

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BudgetGoal;
use App\Entity\User;
use App\Repository\BudgetGoalRepository;
use App\Service\BudgetGoalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BudgetGoalProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private BudgetGoalService $budgetGoalService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Pour les opérations DELETE, gérer avant la vérification de type
        if ($operation instanceof Delete) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            if (!$user) {
                throw new \RuntimeException('Vous devez être connecté pour supprimer un objectif de budget.');
            }

            // Récupérer l'objet depuis $data ou depuis l'ID dans uriVariables
            $budgetGoal = $data instanceof BudgetGoal ? $data : null;
            
            // Si l'objet n'est pas fourni, le récupérer depuis la base de données
            if (!$budgetGoal) {
                $budgetGoalId = $uriVariables['id'] ?? null;
                if (!$budgetGoalId) {
                    throw new \RuntimeException('ID de l\'objectif budgétaire manquant.');
                }
                // Utiliser find() avec un refresh pour s'assurer d'avoir la dernière version
                $budgetGoal = $this->entityManager->getRepository(BudgetGoal::class)->find($budgetGoalId);
                if ($budgetGoal) {
                    // Rafraîchir l'objet depuis la base de données pour éviter les problèmes de cache
                    $this->entityManager->refresh($budgetGoal);
                }
            }
            
            if (!$budgetGoal) {
                throw new \RuntimeException('Objectif budgétaire introuvable.');
            }
            
            // S'assurer que la relation user est chargée
            $budgetGoal->getUser();
            
            // Vérifier que l'utilisateur est le propriétaire ou est admin
            $isOwner = $budgetGoal->getUser() === $user;
            // Vérifier si l'utilisateur est admin (vérifier directement dans les rôles)
            $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
            
            if (!$isOwner && !$isAdmin) {
                throw new \RuntimeException('Vous ne pouvez supprimer que vos propres objectifs de budget.');
            }
            
            // Si tout est OK, procéder à la suppression en utilisant le remove_processor
            // Le removeProcessor gère correctement la suppression et la réponse HTTP
            $result = $this->removeProcessor->process($budgetGoal, $operation, $uriVariables, $context);
            
            // S'assurer que l'objet est bien détaché de l'EntityManager après suppression
            $this->entityManager->detach($budgetGoal);
            
            return $result;
        }

        if ($data instanceof BudgetGoal) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            if (!$user) {
                throw new \RuntimeException('Vous devez être connecté pour créer ou modifier un objectif de budget.');
            }

            // Validation du montant cible
            $targetAmount = (float) $data->getTargetAmount();
            if ($targetAmount <= 0) {
                throw new \RuntimeException('Le montant cible doit être supérieur à 0.');
            }

            // Validation des dates
            if ($data->getStartDate() !== null && $data->getEndDate() !== null) {
                if ($data->getStartDate() > $data->getEndDate()) {
                    throw new \RuntimeException('La date de fin doit être postérieure à la date de début.');
                }
            }

            // Si c'est une création (pas d'ID), assigner l'utilisateur connecté et calculer currentAmount
            if (!$data->getId()) {
                $data->setUser($user);
                
                // Normaliser les dates pour la comparaison (s'assurer qu'elles sont à minuit)
                $startDate = $data->getStartDate();
                $endDate = $data->getEndDate();
                
                if ($startDate === null || $endDate === null) {
                    throw new \RuntimeException('Les dates de début et de fin sont requises.');
                }
                
                $startDate->setTime(0, 0, 0);
                $data->setStartDate($startDate);
                $endDate->setTime(23, 59, 59);
                $data->setEndDate($endDate);
                
                // Vérifier qu'il n'y a pas déjà un budget pour ce mois
                $repository = $this->entityManager->getRepository(BudgetGoal::class);
                /** @var BudgetGoalRepository $repository */
                $existingBudget = $repository->findBudgetForMonth($user, $startDate, null);
                
                if ($existingBudget !== null) {
                    $monthNames = [
                        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
                        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
                        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
                    ];
                    $month = (int) $startDate->format('n');
                    $year = $startDate->format('Y');
                    $monthName = $monthNames[$month] ?? 'mois';
                    throw new \RuntimeException("Vous avez déjà un budget pour le mois de {$monthName} {$year}. Vous ne pouvez créer qu'un seul budget par mois.");
                }
                
                // Calculer automatiquement le montant déjà dépensé dans la période
                if ($data->getUser()) {
                    $this->budgetGoalService->updateCurrentAmount($data);
                }
            } else {
                // En mode modification, vérifier que l'utilisateur modifie son propre objectif
                if ($data->getUser() !== $user && !$this->security->isGranted('ROLE_ADMIN')) {
                    throw new \RuntimeException('Vous ne pouvez modifier que vos propres objectifs de budget.');
                }
                
                // Normaliser les dates pour la comparaison
                $startDate = $data->getStartDate();
                $endDate = $data->getEndDate();
                
                if ($startDate !== null) {
                    $startDate->setTime(0, 0, 0);
                    $data->setStartDate($startDate);
                }
                if ($endDate !== null) {
                    $endDate->setTime(23, 59, 59);
                    $data->setEndDate($endDate);
                }
                
                // Vérifier les chevauchements de périodes (en excluant le budget en cours de modification)
                $repository = $this->entityManager->getRepository(BudgetGoal::class);
                /** @var BudgetGoalRepository $repository */
                $overlappingBudgets = $repository->findOverlappingBudgets(
                    $user,
                    $data->getStartDate(),
                    $data->getEndDate(),
                    $data->getId()
                );
                
                if (!empty($overlappingBudgets)) {
                    $existingGoal = $overlappingBudgets[0];
                    $existingStart = $existingGoal->getStartDate() ? $existingGoal->getStartDate()->format('d/m/Y') : 'non définie';
                    $existingEnd = $existingGoal->getEndDate() ? $existingGoal->getEndDate()->format('d/m/Y') : 'non définie';
                    throw new \RuntimeException("Un autre objectif budgétaire existe déjà pour cette période ({$existingStart} - {$existingEnd}). Vous ne pouvez pas avoir plusieurs budgets sur la même période.");
                }
                
                // Recalculer currentAmount si les dates ont changé
                if ($data->getUser()) {
                    $this->budgetGoalService->updateCurrentAmount($data);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

