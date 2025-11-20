<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'product_review')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_user_product_review', columns: ['user_id', 'product_id'])]
#[ORM\Index(name: 'idx_product_review_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_product_review_user', columns: ['user_id'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['review:read', 'review:detail']]
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['review:read']],
            cacheHeaders: ['max_age' => 0, 'shared_max_age' => 0, 'vary' => ['Authorization']]
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: \App\State\ProductReviewProcessor::class,
            denormalizationContext: ['groups' => ['review:write']],
            normalizationContext: ['groups' => ['review:read', 'review:detail']]
        ),
    ],
    normalizationContext: ['groups' => ['review:read']],
    denormalizationContext: ['groups' => ['review:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['product' => 'exact', 'user' => 'exact'])]
class ProductReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read', 'review:detail', 'product:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['review:read', 'review:write', 'review:detail'])]
    #[Assert\NotNull(message: 'Le produit est requis')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['review:read', 'review:detail'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(['review:read', 'review:write', 'review:detail', 'product:read'])]
    #[Assert\NotNull(message: 'La note est requise')]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être entre 1 et 5'
    )]
    private ?int $rating = null;

    #[ORM\Column(length: 255)]
    #[Groups(['review:read', 'review:write', 'review:detail', 'product:read'])]
    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['review:read', 'review:write', 'review:detail', 'product:read'])]
    #[Assert\NotBlank(message: 'Le contenu est requis')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères'
    )]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['review:read', 'review:detail', 'product:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['review:read', 'review:detail'])]
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
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

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    // Méthode pour obtenir le nom de l'utilisateur (pour la sérialisation)
    #[Groups(['review:read', 'review:detail', 'product:read'])]
    public function getUserName(): string
    {
        if ($this->user) {
            return $this->user->getFirstName() . ' ' . $this->user->getLastName();
        }
        return 'Utilisateur anonyme';
    }

    // Méthode pour obtenir les initiales de l'utilisateur
    #[Groups(['review:read', 'review:detail', 'product:read'])]
    public function getUserInitials(): string
    {
        if ($this->user) {
            return strtoupper(substr($this->user->getFirstName(), 0, 1) . substr($this->user->getLastName(), 0, 1));
        }
        return 'UA';
    }
}

