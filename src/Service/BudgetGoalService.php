<?php

namespace App\Service;

use App\Entity\BudgetGoal;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BudgetGoalService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule le montant total des commandes d'un utilisateur dans une période donnée
     * 
     * @param User $user L'utilisateur
     * @param \DateTimeInterface|null $startDate Date de début (inclusive)
     * @param \DateTimeInterface|null $endDate Date de fin (inclusive)
     * @return float Montant total des commandes
     */
    public function calculateSpentAmount(User $user, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): float
    {
        $qb = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.user = :user')
            ->andWhere('o.status != :cancelledStatus')
            ->setParameter('user', $user)
            ->setParameter('cancelledStatus', 'cancelled');

        // Filtrer par date de début si fournie
        if ($startDate !== null) {
            $startDateTime = clone $startDate;
            $startDateTime->setTime(0, 0, 0);
            $qb->andWhere('o.createdAt >= :startDate')
               ->setParameter('startDate', $startDateTime);
        }

        // Filtrer par date de fin si fournie
        if ($endDate !== null) {
            $endDateTime = clone $endDate;
            $endDateTime->setTime(23, 59, 59);
            $qb->andWhere('o.createdAt <= :endDate')
               ->setParameter('endDate', $endDateTime);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return (float) $result;
    }

    /**
     * Calcule et met à jour le currentAmount d'un objectif budgétaire
     * 
     * @param BudgetGoal $budgetGoal L'objectif budgétaire à mettre à jour
     * @return BudgetGoal L'objectif budgétaire mis à jour
     */
    public function updateCurrentAmount(BudgetGoal $budgetGoal): BudgetGoal
    {
        $user = $budgetGoal->getUser();
        if (!$user) {
            // Si pas d'utilisateur, initialiser à 0
            $budgetGoal->setCurrentAmount('0.00');
            return $budgetGoal;
        }

        try {
            $spentAmount = $this->calculateSpentAmount(
                $user,
                $budgetGoal->getStartDate(),
                $budgetGoal->getEndDate()
            );

            $budgetGoal->setCurrentAmount(number_format($spentAmount, 2, '.', ''));
        } catch (\Exception $e) {
            // En cas d'erreur, initialiser à 0
            $budgetGoal->setCurrentAmount('0.00');
        }
        
        return $budgetGoal;
    }

    /**
     * Met à jour tous les objectifs budgétaires d'un utilisateur qui chevauchent la date d'une commande
     * 
     * @param Order $order La commande créée
     */
    public function updateBudgetGoalsForOrder(Order $order): void
    {
        $user = $order->getUser();
        if (!$user) {
            return;
        }

        $orderDate = $order->getCreatedAt();
        if (!$orderDate) {
            return;
        }

        // Récupérer tous les objectifs budgétaires de l'utilisateur qui chevauchent la date de la commande
        $budgetGoals = $this->entityManager->getRepository(BudgetGoal::class)
            ->createQueryBuilder('bg')
            ->where('bg.user = :user')
            ->andWhere('(bg.startDate IS NULL OR bg.startDate <= :orderDate)')
            ->andWhere('(bg.endDate IS NULL OR bg.endDate >= :orderDate)')
            ->setParameter('user', $user)
            ->setParameter('orderDate', $orderDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        // Mettre à jour chaque objectif
        foreach ($budgetGoals as $budgetGoal) {
            $this->updateCurrentAmount($budgetGoal);
        }

        $this->entityManager->flush();
    }
}

