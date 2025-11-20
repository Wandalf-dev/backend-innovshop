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
use App\Repository\AddressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            provider: \App\State\AddressProvider::class,
            security: "is_granted('ROLE_USER') or is_granted('ROLE_SELLER')"
        ),
        new GetCollection(
            provider: \App\State\AddressProvider::class,
            security: "is_granted('ROLE_USER') or is_granted('ROLE_SELLER')"
        ),
        new Post(processor: \App\State\AddressProcessor::class),
        new Put(processor: \App\State\AddressProcessor::class),
        new Patch(processor: \App\State\AddressProcessor::class),
        new Delete()
    ],
    normalizationContext: ['groups' => ['address:read']],
    denormalizationContext: ['groups' => ['address:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact'])]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['address:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['address:write', 'address:read'])] // Exposer user dans address:read pour le filtrage côté client
    #[ApiProperty(writable: false)] // Le user sera assigné automatiquement par le processor
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $label = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $street = null;

    #[ORM\Column(length: 20)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $zipCode = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $city = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $country = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private ?string $phone = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['address:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['address:read'])]
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;
        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): static
    {
        $this->zipCode = $zipCode;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
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
     * Get formatted address as a single string
     */
    #[Groups(['address:read', 'user:read'])]
    public function getFullAddress(): string
    {
        return sprintf(
            '%s, %s %s, %s',
            $this->street ?? '',
            $this->zipCode ?? '',
            $this->city ?? '',
            $this->country ?? ''
        );
    }
}
