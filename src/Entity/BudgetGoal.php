<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\BudgetGoalRepository;
use App\State\BudgetGoalProcessor;
use App\State\BudgetGoalProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetGoalRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    uriTemplate: '/budget-goals',
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER') or is_granted('ROLE_SELLER')",
            normalizationContext: ['groups' => ['budget_goal:read']],
            cacheHeaders: ['max_age' => 0, 'shared_max_age' => 0, 'vary' => ['Authorization']]
        ),
        new Get(
            uriTemplate: '/budget-goals/{id}',
            security: "(is_granted('ROLE_USER') or is_granted('ROLE_SELLER')) and object.getUser() == user",
            normalizationContext: ['groups' => ['budget_goal:read']]
        ),
        new Post(
            security: "is_granted('ROLE_USER') or is_granted('ROLE_SELLER')",
            processor: BudgetGoalProcessor::class,
            denormalizationContext: ['groups' => ['budget_goal:write']]
        ),
        new Put(
            uriTemplate: '/budget-goals/{id}',
            security: "(is_granted('ROLE_USER') or is_granted('ROLE_SELLER')) and object.getUser() == user",
            processor: BudgetGoalProcessor::class,
            denormalizationContext: ['groups' => ['budget_goal:write']]
        ),
        new Patch(
            uriTemplate: '/budget-goals/{id}',
            security: "(is_granted('ROLE_USER') or is_granted('ROLE_SELLER')) and object.getUser() == user",
            processor: BudgetGoalProcessor::class,
            denormalizationContext: ['groups' => ['budget_goal:write']]
        ),
        new Delete(
            uriTemplate: '/budget-goals/{id}',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_SELLER')",
            provider: BudgetGoalProvider::class,
            processor: BudgetGoalProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['budget_goal:read']],
    denormalizationContext: ['groups' => ['budget_goal:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['goalType' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['endDate' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC'])]
class BudgetGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['budget_goal:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'budgetGoals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 150)]
    #[Groups(['budget_goal:read', 'budget_goal:write'])]
    #[Assert\NotBlank(message: 'Le libellé est requis')]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: 'Le libellé doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $label = null;

    #[ORM\Column(length: 50)]
    #[Groups(['budget_goal:read', 'budget_goal:write'])]
    #[Assert\NotBlank(message: 'Le type d\'objectif est requis')]
    #[Assert\Choice(
        choices: ['économie', 'plafond'],
        message: 'Le type d\'objectif doit être l\'un des suivants : économie, plafond'
    )]
    private ?string $goalType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['budget_goal:read', 'budget_goal:write'])]
    #[Assert\NotBlank(message: 'Le montant cible est requis')]
    #[Assert\Type(type: 'numeric', message: 'Le montant cible doit être un nombre')]
    #[Assert\Positive(message: 'Le montant cible doit être supérieur à 0')]
    private ?string $targetAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['budget_goal:read'])]
    private string $currentAmount = '0.00';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['budget_goal:read', 'budget_goal:write'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['budget_goal:read', 'budget_goal:write'])]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups(['budget_goal:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(writable: false)]
    #[Groups(['budget_goal:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getGoalType(): ?string
    {
        return $this->goalType;
    }

    public function setGoalType(string $goalType): static
    {
        $this->goalType = $goalType;
        return $this;
    }

    public function getTargetAmount(): ?string
    {
        return $this->targetAmount;
    }

    public function setTargetAmount(string $targetAmount): static
    {
        $this->targetAmount = $targetAmount;
        return $this;
    }

    public function getCurrentAmount(): string
    {
        return $this->currentAmount;
    }

    public function setCurrentAmount(string $currentAmount): static
    {
        $this->currentAmount = $currentAmount;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Calcul du pourcentage de progression (0-100)
     */
    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['budget_goal:read'])]
    public function getProgressPercentage(): float
    {
        if (!$this->targetAmount || (float) $this->targetAmount <= 0) {
            return 0.0;
        }

        $current = (float) $this->currentAmount;
        $target = (float) $this->targetAmount;
        $percentage = ($current / $target) * 100;

        // Borner entre 0 et 100
        return max(0.0, min(100.0, round($percentage, 2)));
    }

    public function __toString(): string
    {
        return $this->label ?? '';
    }
}
