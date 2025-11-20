<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\SellerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SellerRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')", validationContext: ['groups' => ['Default', 'seller:create']]),
        new Put(security: "is_granted('ROLE_ADMIN') or object.getUser() == user"),
        new Patch(security: "is_granted('ROLE_ADMIN') or object.getUser() == user", validationContext: ['groups' => ['Default']]),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['seller:read']],
    denormalizationContext: ['groups' => ['seller:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'slug' => 'exact'])]
class Seller
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['seller:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'seller')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['seller:read', 'seller:write'])]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column(length: 150)]
    #[Groups(['seller:read', 'seller:write', 'product:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 150)]
    private ?string $shopName = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Groups(['seller:read', 'seller:write', 'product:read'])]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['seller:read', 'seller:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['seller:read', 'seller:write'])]
    private ?string $logoPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['seller:read', 'seller:write'])]
    private ?string $avatarPath = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['seller:read', 'seller:write'])]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['seller:read', 'seller:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 34, nullable: true)]
    #[Groups(['seller:write'])] // Pas dans seller:read pour la sécurité
    #[Assert\Iban(message: 'L\'IBAN n\'est pas valide', groups: ['seller:create'])]
    private ?string $iban = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pending'])]
    #[Groups(['seller:read', 'seller:write'])]
    #[Assert\Choice(choices: ['pending', 'approved', 'suspended'])]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    #[Groups(['seller:read'])]
    private string $ratingAverage = '0.00';

    #[ORM\Column]
    #[Groups(['seller:read'])]
    private int $ratingCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['seller:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['seller:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'seller')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getShopName(): ?string
    {
        return $this->shopName;
    }

    public function setShopName(string $shopName): static
    {
        $this->shopName = $shopName;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;
        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getRatingAverage(): string
    {
        return $this->ratingAverage;
    }

    public function setRatingAverage(string $ratingAverage): static
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
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setSeller($this);
        }
        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            if ($product->getSeller() === $this) {
                $product->setSeller(null);
            }
        }
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Retourne le nombre de produits publiés du vendeur
     * Méthode calculée simple; déclenche le lazy loading si collection non initialisée
     */
    #[Groups(['seller:read'])]
    public function getProductCount(): int
    {
        return $this->products->filter(fn(Product $product) => $product->isPublished())->count();
    }
}
