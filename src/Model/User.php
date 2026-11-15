<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

class User
{
    public function __construct(
        private ?int $id,
        private string $googleSub,
        private string $email,
        private string $name,
        private string $role = 'member',
        private ?DateTimeImmutable $createdAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getGoogleSub(): string
    {
        return $this->googleSub;
    }

    public function setGoogleSub(string $googleSub): void
    {
        $this->googleSub = $googleSub;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
