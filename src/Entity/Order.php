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
use App\Repository\OrderRepository;
use App\State\OrderProcessor;
use App\State\OrderProvider;
use App\State\OrderSecurityProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(provider: OrderProvider::class, normalizationContext: ['groups' => ['order:read', 'order:read:full']]),
        new GetCollection(provider: OrderProvider::class, normalizationContext: ['groups' => ['order:read', 'order:read:full']]),
        new Post(processor: OrderProcessor::class),
        new Put(processor: OrderSecurityProcessor::class, security: "is_granted('ROLE_USER')", denormalizationContext: ['groups' => ['order:write']]),
        new Patch(processor: OrderSecurityProcessor::class, security: "is_granted('ROLE_USER')", denormalizationContext: ['groups' => ['order:write']]),
        new Delete()
    ],
    normalizationContext: ['groups' => ['order:read']]
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact'])]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read:full'])]
    private ?User $user = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['order:read'])]
    private ?string $reference = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $status = 'confirmed';

    public const STATUS_ENUM = ['confirmed', 'shipped', 'delivered', 'cancelled'];

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUS_ENUM, true)) {
            throw new \InvalidArgumentException('Statut de commande invalide : ' . $status);
        }
        $this->status = $status;
        return $this;
    }


    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read'])]
    private ?string $totalAmount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['order:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['order:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 100)]
    private ?string $deliveryFirstName = null;

    #[ORM\Column(length: 100)]
    private ?string $deliveryLastName = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryStreet = null;

    #[ORM\Column(length: 20)]
    private ?string $deliveryZipCode = null;

    #[ORM\Column(length: 100)]
    private ?string $deliveryCity = null;

    #[ORM\Column(length: 100)]
    private ?string $deliveryCountry = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $deliveryPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shippingMethod = null;

    #[ORM\ManyToOne(targetEntity: Cart::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Cart $cart = null;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', orphanRemoval: true)]
    #[Groups(['order:read:full'])]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: \App\Entity\OrderSellerLot::class, mappedBy: 'order', orphanRemoval: true, cascade: ['persist'], fetch: 'LAZY')]
    #[Groups(['order:read:full'])]
    private Collection $sellerLots;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->sellerLots = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        // Ne définir la date que si elle n'est pas déjà définie
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }


    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
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

    public function getDeliveryFirstName(): ?string
    {
        return $this->deliveryFirstName;
    }

    public function setDeliveryFirstName(string $deliveryFirstName): static
    {
        $this->deliveryFirstName = $deliveryFirstName;
        return $this;
    }

    public function getDeliveryLastName(): ?string
    {
        return $this->deliveryLastName;
    }

    public function setDeliveryLastName(string $deliveryLastName): static
    {
        $this->deliveryLastName = $deliveryLastName;
        return $this;
    }

    public function getDeliveryStreet(): ?string
    {
        return $this->deliveryStreet;
    }

    public function setDeliveryStreet(string $deliveryStreet): static
    {
        $this->deliveryStreet = $deliveryStreet;
        return $this;
    }

    public function getDeliveryZipCode(): ?string
    {
        return $this->deliveryZipCode;
    }

    public function setDeliveryZipCode(string $deliveryZipCode): static
    {
        $this->deliveryZipCode = $deliveryZipCode;
        return $this;
    }

    public function getDeliveryCity(): ?string
    {
        return $this->deliveryCity;
    }

    public function setDeliveryCity(string $deliveryCity): static
    {
        $this->deliveryCity = $deliveryCity;
        return $this;
    }

    public function getDeliveryCountry(): ?string
    {
        return $this->deliveryCountry;
    }

    public function setDeliveryCountry(string $deliveryCountry): static
    {
        $this->deliveryCountry = $deliveryCountry;
        return $this;
    }

    public function getDeliveryPhone(): ?string
    {
        return $this->deliveryPhone;
    }

    public function setDeliveryPhone(?string $deliveryPhone): static
    {
        $this->deliveryPhone = $deliveryPhone;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }
        return $this;
    }

    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function setPaymentIntentId(?string $paymentIntentId): static
    {
        $this->paymentIntentId = $paymentIntentId;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getShippingMethod(): ?string
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(?string $shippingMethod): static
    {
        $this->shippingMethod = $shippingMethod;
        return $this;
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

    /**
     * @return Collection<int, \App\Entity\OrderSellerLot>
     */
    public function getSellerLots(): Collection
    {
        return $this->sellerLots;
    }

    public function addSellerLot(\App\Entity\OrderSellerLot $sellerLot): static
    {
        if (!$this->sellerLots->contains($sellerLot)) {
            $this->sellerLots->add($sellerLot);
            $sellerLot->setOrder($this);
        }
        return $this;
    }

    public function removeSellerLot(\App\Entity\OrderSellerLot $sellerLot): static
    {
        if ($this->sellerLots->removeElement($sellerLot)) {
            if ($sellerLot->getOrder() === $this) {
                $sellerLot->setOrder(null);
            }
        }
        return $this;
    }
}
