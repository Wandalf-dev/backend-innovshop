<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor: \App\State\UserPasswordHasher::class),
        new Put(processor: \App\State\UserPasswordHasher::class),
        new Patch(processor: \App\State\UserPasswordHasher::class),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'seller:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write', 'seller:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user:write'])]
    private ?string $password = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['user:read', 'user:write'])]
    private array $roles = [];

    #[ORM\Column(length: 100)]
    #[Groups(['user:read', 'user:write', 'order:read:full', 'seller:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read', 'user:write', 'order:read:full', 'seller:read'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read', 'user:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[ApiProperty(writable: false)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Address::class, mappedBy: 'user', orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $addresses;

    #[ORM\OneToMany(targetEntity: Cart::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $carts;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    #[ORM\OneToMany(targetEntity: BudgetGoal::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $budgetGoals;

    #[ORM\OneToOne(targetEntity: Seller::class, mappedBy: 'user')]
    #[Groups(['user:read'])]
    private ?Seller $seller = null;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->carts = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->budgetGoals = new ArrayCollection();
    }

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Méthode utilisée par Symfony Security pour obtenir les rôles
     * Cette méthode modifie les rôles selon la logique métier
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        
        // Logique des rôles :
        // - ROLE_ADMIN peut avoir tous les droits (ROLE_ADMIN + ROLE_USER + ROLE_SELLER)
        // - ROLE_SELLER seul ne doit PAS avoir ROLE_USER (uniquement ROLE_SELLER)
        // - Un client normal doit avoir ROLE_USER
        $hasSeller = in_array('ROLE_SELLER', $roles, true);
        $hasAdmin = in_array('ROLE_ADMIN', $roles, true);
        
        if ($hasAdmin) {
            // Si l'utilisateur est admin, il peut avoir tous les rôles
            // S'assurer qu'il a ROLE_USER
            if (!in_array('ROLE_USER', $roles, true)) {
                $roles[] = 'ROLE_USER';
            }
            // L'admin peut aussi avoir ROLE_SELLER s'il a un compte vendeur
            // On ne retire pas ROLE_SELLER ni ROLE_USER pour un admin
        } elseif ($hasSeller) {
            // Si l'utilisateur est vendeur (mais pas admin), retirer ROLE_USER
            // Un vendeur non-admin ne doit avoir QUE ROLE_SELLER
            $roles = array_filter($roles, fn($role) => $role !== 'ROLE_USER');
            $roles = array_values($roles);
        } elseif (empty($roles)) {
            // Si l'utilisateur n'a aucun rôle et pas de rôle spécial, ajouter ROLE_USER par défaut
            $roles[] = 'ROLE_USER';
        }
        
        return array_unique($roles);
    }

    /**
     * Propriété virtuelle pour API Platform
     * Retourne les rôles tels qu'ils sont stockés dans la base de données
     * sans modification pour éviter les problèmes de sérialisation
     */
    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['user:read'])]
    public function getRolesForApi(): array
    {
        // Pour la sérialisation, retourner les rôles tels qu'ils sont stockés
        // La logique de modification des rôles est dans getRoles() pour Symfony Security
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): static
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setUser($this);
        }
        return $this;
    }

    public function removeAddress(Address $address): static
    {
        if ($this->addresses->removeElement($address)) {
            if ($address->getUser() === $this) {
                $address->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Cart>
     */
    public function getCarts(): Collection
    {
        return $this->carts;
    }

    public function addCart(Cart $cart): static
    {
        if (!$this->carts->contains($cart)) {
            $this->carts->add($cart);
            $cart->setUser($this);
        }
        return $this;
    }

    public function removeCart(Cart $cart): static
    {
        if ($this->carts->removeElement($cart)) {
            if ($cart->getUser() === $this) {
                $cart->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, BudgetGoal>
     */
    public function getBudgetGoals(): Collection
    {
        return $this->budgetGoals;
    }

    public function addBudgetGoal(BudgetGoal $budgetGoal): static
    {
        if (!$this->budgetGoals->contains($budgetGoal)) {
            $this->budgetGoals->add($budgetGoal);
            $budgetGoal->setUser($this);
        }
        return $this;
    }

    public function removeBudgetGoal(BudgetGoal $budgetGoal): static
    {
        if ($this->budgetGoals->removeElement($budgetGoal)) {
            if ($budgetGoal->getUser() === $this) {
                $budgetGoal->setUser(null);
            }
        }
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getSeller(): ?Seller
    {
        return $this->seller;
    }

    public function setSeller(?Seller $seller): static
    {
        // unset the owning side of the relation if necessary
        if ($seller === null && $this->seller !== null) {
            $this->seller->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($seller !== null && $seller->getUser() !== $this) {
            $seller->setUser($this);
        }

        $this->seller = $seller;
        return $this;
    }

    public function isSeller(): bool
    {
        return $this->seller !== null && in_array('ROLE_SELLER', $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }
}
