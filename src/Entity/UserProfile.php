<?php

namespace App\Entity;

use App\Repository\UserProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    // Correction: 'User' avec Majuscule et inversedBy: 'userProfile'
    #[ORM\OneToOne(inversedBy: 'userProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }

    public function setFirstName(?string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }

    public function setLastName(?string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }

    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function getBio(): ?string { return $this->bio; }

    public function setBio(?string $bio): static { $this->bio = $bio; return $this; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(User $user): static { $this->user = $user; return $this; }
}