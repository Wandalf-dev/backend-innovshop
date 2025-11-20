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
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\ProductRepository;
use App\State\ProductProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: "idx_created_at", columns: ["created_at"])]
#[ORM\Index(name: "idx_is_published", columns: ["is_published"])]
#[ORM\Index(name: "idx_is_featured", columns: ["is_featured"])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['product:read', 'product:detail']]
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['product:read', 'product:list']]
        ),
        new Post(
            processor: ProductProcessor::class
        ),
        new Put(
            processor: ProductProcessor::class
        ),
        new Patch(
            processor: ProductProcessor::class
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['category' => 'exact', 'seller' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['isPublished', 'isFeatured'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC', 'price' => 'ASC'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'product:list', 'product:detail', 'cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Seller::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'seller_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail', 'order:read:full'])]
    private ?Seller $seller = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail', 'cart:read', 'order:read:full'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['product:read', 'product:list', 'product:detail'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['product:read', 'product:write', 'product:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail', 'cart:read'])]
    private ?string $price = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:detail'])]
    private ?array $colors = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:detail'])]
    private ?array $sizes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:detail'])]
    private ?array $features = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    private bool $isFeatured = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['product:read', 'product:detail'])]
    private ?\DateTimeInterface $featuredAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    private bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['product:read', 'product:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['product:read', 'product:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', orphanRemoval: true)]
    #[Groups(['product:read', 'product:list', 'product:detail', 'cart:read', 'order:read:full'])]
    private Collection $images;

    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'product')]
    private Collection $cartItems;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private Collection $orderItems;

    #[ORM\OneToMany(targetEntity: ProductReview::class, mappedBy: 'product', orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Groups(['product:read', 'product:list', 'product:detail'])]
    private ?string $ratingAverage = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['product:read', 'product:list', 'product:detail'])]
    private int $ratingCount = 0;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        // Générer le slug automatiquement si non défini
        if ($this->slug === null && $this->name !== null) {
            $this->slug = $this->generateSlug($this->name);
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
        // Mettre à jour le slug si le nom a changé
        if ($this->name !== null) {
            $this->slug = $this->generateSlug($this->name);
        }
    }

    private function generateSlug(string $string): string
    {
        // Remplacer les caractères accentués
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        // Convertir en minuscules
        $string = strtolower($string);
        // Remplacer tout ce qui n'est pas alphanumérique par un tiret
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);
        // Supprimer les tirets en début et fin
        $string = trim($string, '-');
        // Ajouter un timestamp pour garantir l'unicité
        $string .= '-' . time();
        
        return $string;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getSeller(): ?Seller
    {
        return $this->seller;
    }

    public function setSeller(?Seller $seller): static
    {
        $this->seller = $seller;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getColors(): ?array
    {
        return $this->colors;
    }

    public function setColors(?array $colors): static
    {
        $this->colors = $colors;
        return $this;
    }

    /**
     * Méthode de compatibilité pour l'ancien champ color (string)
     * Retourne la première couleur si disponible
     */
    #[Groups(['product:read', 'product:detail'])]
    public function getColor(): ?string
    {
        if ($this->colors && count($this->colors) > 0) {
            return $this->colors[0];
        }
        return null;
    }

    public function getSizes(): ?array
    {
        return $this->sizes;
    }

    public function setSizes(?array $sizes): static
    {
        $this->sizes = $sizes;
        return $this;
    }

    /**
     * Méthode de compatibilité pour l'ancien champ size (string)
     * Retourne la première taille si disponible
     */
    #[Groups(['product:read', 'product:detail'])]
    public function getSize(): ?string
    {
        if ($this->sizes && count($this->sizes) > 0) {
            return $this->sizes[0];
        }
        return null;
    }

    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        // Si on active "mis à la une", enregistrer la date
        if ($isFeatured && !$this->isFeatured) {
            $this->featuredAt = new \DateTime();
        }
        // Si on désactive, réinitialiser la date
        if (!$isFeatured) {
            $this->featuredAt = null;
        }
        
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getFeaturedAt(): ?\DateTimeInterface
    {
        return $this->featuredAt;
    }

    public function setFeaturedAt(?\DateTimeInterface $featuredAt): static
    {
        $this->featuredAt = $featuredAt;
        return $this;
    }

    #[Groups(['product:read', 'product:write', 'product:list', 'product:detail'])]
    #[SerializedName('published')]
    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
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
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $cartItem): static
    {
        if (!$this->cartItems->contains($cartItem)) {
            $this->cartItems->add($cartItem);
            $cartItem->setProduct($this);
        }
        return $this;
    }

    public function removeCartItem(CartItem $cartItem): static
    {
        if ($this->cartItems->removeElement($cartItem)) {
            if ($cartItem->getProduct() === $this) {
                $cartItem->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setProduct($this);
        }
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getProduct() === $this) {
                $orderItem->setProduct(null);
            }
        }
        return $this;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    /**
     * @return Collection<int, ProductReview>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(ProductReview $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }
        return $this;
    }

    public function removeReview(ProductReview $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getProduct() === $this) {
                $review->setProduct(null);
            }
        }
        return $this;
    }

    public function getRatingAverage(): ?string
    {
        return $this->ratingAverage;
    }

    public function setRatingAverage(?string $ratingAverage): static
    {
        $this->ratingAverage = $ratingAverage;
        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): static
    {
        $this->ratingCount = $ratingCount;
        return $this;
    }
}
