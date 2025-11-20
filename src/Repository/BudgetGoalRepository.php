<?php

namespace App\Repository;

use App\Entity\BudgetGoal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetGoal>
 */
class BudgetGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetGoal::class);
    }

    /**
     * Vérifie si une période chevauche avec d'autres budgets de l'utilisateur
     * 
     * @param User $user L'utilisateur
     * @param \DateTimeInterface|null $startDate Date de début
     * @param \DateTimeInterface|null $endDate Date de fin
     * @param int|null $excludeId ID du budget à exclure (pour les modifications)
     * @return BudgetGoal[] Liste des budgets qui chevauchent
     */
    public function findOverlappingBudgets(User $user, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('bg')
            ->where('bg.user = :user')
            ->setParameter('user', $user);

        // Exclure le budget en cours de modification
        if ($excludeId !== null) {
            $qb->andWhere('bg.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        // Si les deux dates sont définies, vérifier le chevauchement
        if ($startDate !== null && $endDate !== null) {
            // Normaliser les dates pour la comparaison (sans heures/minutes/secondes)
            $startDateNormalized = clone $startDate;
            $startDateNormalized->setTime(0, 0, 0);
            $endDateNormalized = clone $endDate;
            $endDateNormalized->setTime(23, 59, 59);
            
            // Vérifier les chevauchements : deux périodes se chevauchent si
            // startDate1 <= endDate2 AND endDate1 >= startDate2
            // On vérifie seulement les budgets qui ont des dates définies
            // Utiliser CAST pour convertir en DATE si nécessaire, sinon comparer directement
            $qb->andWhere('bg.startDate IS NOT NULL AND bg.endDate IS NOT NULL')
               ->andWhere('bg.startDate <= :endDate AND bg.endDate >= :startDate')
            ->setParameter('startDate', $startDateNormalized, \Doctrine\DBAL\Types\Types::DATE_MUTABLE)
            ->setParameter('endDate', $endDateNormalized, \Doctrine\DBAL\Types\Types::DATE_MUTABLE);
        } 
        // Si seule la date de début est définie
        elseif ($startDate !== null) {
            $qb->andWhere('(
                bg.endDate IS NULL OR bg.endDate >= :startDate
            )')
            ->setParameter('startDate', $startDate);
        }
        // Si seule la date de fin est définie
        elseif ($endDate !== null) {
            $qb->andWhere('(
                bg.startDate IS NULL OR bg.startDate <= :endDate
            )')
            ->setParameter('endDate', $endDate);
        }
        // Si aucune date n'est définie, vérifier s'il existe des budgets sans dates
        else {
            $qb->andWhere('bg.startDate IS NULL AND bg.endDate IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si l'utilisateur a déjà un budget pour le mois spécifié
     * 
     * @param User $user L'utilisateur
     * @param \DateTimeInterface $date Une date du mois à vérifier
     * @param int|null $excludeId ID du budget à exclure (pour les modifications)
     * @return BudgetGoal|null Le budget existant pour ce mois, ou null
     */
    public function findBudgetForMonth(User $user, \DateTimeInterface $date, ?int $excludeId = null): ?BudgetGoal
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        
        // Calculer le premier et dernier jour du mois
        $firstDay = new \DateTime("{$year}-{$month}-01");
        $firstDay->setTime(0, 0, 0);
        $lastDay = clone $firstDay;
        $lastDay->modify('last day of this month');
        $lastDay->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('bg')
            ->where('bg.user = :user')
            ->andWhere('bg.startDate IS NOT NULL AND bg.endDate IS NOT NULL')
            ->andWhere('bg.startDate <= :lastDay AND bg.endDate >= :firstDay')
            ->setParameter('user', $user)
            ->setParameter('firstDay', $firstDay, \Doctrine\DBAL\Types\Types::DATE_MUTABLE)
            ->setParameter('lastDay', $lastDay, \Doctrine\DBAL\Types\Types::DATE_MUTABLE)
            ->setMaxResults(1);

        // Exclure le budget en cours de modification
        if ($excludeId !== null) {
            $qb->andWhere('bg.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $result = $qb->getQuery()->getResult();
        return !empty($result) ? $result[0] : null;
    }
}
