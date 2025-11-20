<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ApiResource]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read:full'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Seller::class)]
    #[ORM\JoinColumn(name: 'seller_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['order:read:full'])]
    private ?Seller $seller = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:read:full'])]
    private ?string $productName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read:full'])]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['order:read:full'])]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read:full'])]
    private ?string $totalLine = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['order:read:full'])]
    private ?string $selectedColor = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['order:read:full'])]
    private ?string $selectedSize = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
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

    public function getSeller(): ?Seller
    {
        return $this->seller;
    }

    public function setSeller(?Seller $seller): static
    {
        $this->seller = $seller;
        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getTotalLine(): ?string
    {
        return $this->totalLine;
    }

    public function setTotalLine(string $totalLine): static
    {
        $this->totalLine = $totalLine;
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
}
