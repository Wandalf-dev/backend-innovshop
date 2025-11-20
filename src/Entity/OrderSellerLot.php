<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use App\Repository\OrderSellerLotRepository;
use App\State\OrderSellerLotProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderSellerLotRepository::class)]
#[ORM\Table(name: 'order_seller_lot')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['order_lot:read']]),
        new GetCollection(normalizationContext: ['groups' => ['order_lot:read']]),
        new Put(processor: OrderSellerLotProcessor::class, denormalizationContext: ['groups' => ['order_lot:write']]),
        new Patch(processor: OrderSellerLotProcessor::class, denormalizationContext: ['groups' => ['order_lot:write']])
    ],
    normalizationContext: ['groups' => ['order_lot:read']]
)]
class OrderSellerLot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order_lot:read', 'order:read:full'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'sellerLots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['order_lot:read'])]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Seller::class)]
    #[ORM\JoinColumn(name: 'seller_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['order_lot:read', 'order:read:full'])]
    private ?Seller $seller = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['order_lot:read', 'order_lot:write', 'order:read:full'])]
    private ?string $status = 'confirmed';

    public const STATUS_ENUM = ['confirmed', 'shipped', 'delivered', 'cancelled'];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['order_lot:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['order_lot:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->status = 'confirmed';
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUS_ENUM, true)) {
            throw new \InvalidArgumentException('Statut de lot invalide : ' . $status);
        }
        $this->status = $status;
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
}

