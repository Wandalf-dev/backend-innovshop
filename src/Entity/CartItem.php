<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CartItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['cart_item:read']],
    denormalizationContext: ['groups' => ['cart_item:write']],
    operations: [
        new Get(normalizationContext: ['groups' => ['cart_item:read', 'cart:read']]),
        new GetCollection(),
        new Post(normalizationContext: ['groups' => ['cart_item:read', 'cart:read']]),
        new Put(),
        new Patch(),
        new Delete()
    ]
)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart_item:read', 'cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cart_item:read', 'cart_item:write'])]
    private ?Cart $cart = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'cartItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cart_item:read', 'cart_item:write', 'cart:read'])]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['cart_item:read', 'cart_item:write', 'cart:read'])]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['cart_item:read', 'cart_item:write', 'cart:read'])]
    private ?int $quantity = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['cart_item:read', 'cart_item:write', 'cart:read'])]
    private ?string $selectedColor = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['cart_item:read', 'cart_item:write', 'cart:read'])]
    private ?string $selectedSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cart_item:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;
        return $this;
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

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getSelectedColor(): ?string
    {
        return $this->selectedColor;
    }

    public function setSelectedColor(?string $selectedColor): static
    {
        $this->selectedColor = $selectedColor;
        return $this;
    }

    public function getSelectedSize(): ?string
    {
        return $this->selectedSize;
    }

    public function setSelectedSize(?string $selectedSize): static
    {
        $this->selectedSize = $selectedSize;
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
}
