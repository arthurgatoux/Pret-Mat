<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

class Loan
{
    public function __construct(
        private ?int $id,
        private int $userId,
        private int $itemId,
        private DateTimeImmutable $borrowedAt,
        private DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
        private ?string $userName = null,
        private ?string $userEmail = null,
        private ?string $itemName = null,
        private ?string $categoryName = null,
        private ?bool $isLate = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getBorrowedAt(): DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function getDueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getReturnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?DateTimeImmutable $returnedAt): void
    {
        $this->returnedAt = $returnedAt;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getItemName(): ?string
    {
        return $this->itemName;
    }

    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }

    public function isActive(): bool
    {
        return $this->returnedAt === null;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isLate(): bool
    {
        if ($this->isLate !== null) {
            return $this->isLate;
        }

        if ($this->returnedAt !== null) {
            return false;
        }

        return $this->dueAt < new DateTimeImmutable();
    }
}
