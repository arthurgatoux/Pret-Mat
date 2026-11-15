<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Category;
use App\Model\Item;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use InvalidArgumentException;
use RuntimeException;

class CatalogService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ItemRepository $itemRepository
    ) {}

    // =========================================================================
    // Gestion du Matériel (Items)
    // =========================================================================

    /**
     * @return array<Item>
     */
    public function getCatalog(?int $categoryId = null): array
    {
        return $this->itemRepository->findCatalog($categoryId);
    }

    /**
     * @return array<Item>
     */
    public function getAllItems(): array
    {
        return $this->itemRepository->findAll();
    }

    public function getItem(int $id): ?Item
    {
        return $this->itemRepository->findById($id);
    }

    public function createItem(string $name, ?string $description, int $categoryId): Item
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Le nom du matériel est obligatoire.");
        }

        $category = $this->categoryRepository->findById($categoryId);
        if ($category === null) {
            throw new InvalidArgumentException("La catégorie sélectionnée est introuvable.");
        }

        $item = new Item(
            id: null,
            name: $name,
            description: $description !== null && trim($description) !== '' ? trim($description) : null,
            categoryId: $categoryId
        );

        return $this->itemRepository->create($item);
    }

    public function updateItem(int $id, string $name, ?string $description, int $categoryId): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Le nom du matériel est obligatoire.");
        }

        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            throw new RuntimeException("Matériel introuvable.");
        }

        $category = $this->categoryRepository->findById($categoryId);
        if ($category === null) {
            throw new InvalidArgumentException("La catégorie sélectionnée est introuvable.");
        }

        $item->setName($name);
        $item->setDescription($description !== null && trim($description) !== '' ? trim($description) : null);
        $item->setCategoryId($categoryId);

        return $this->itemRepository->update($item);
    }

    /**
     * Supprime un matériel si et seulement s'il ne possède ni prêt actif, ni historique de prêts.
     * Ordre d'évaluation strict :
     * 1. Vérification d'un prêt actif en cours (hasActiveLoan)
     * 2. Vérification d'un historique d'emprunts passé (hasLoans)
     *
     * @throws RuntimeException En cas de prêt actif ou d'historique de prêts
     */
    public function deleteItem(int $id): bool
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            throw new RuntimeException("Matériel introuvable.");
        }

        // 1. Refus prioritaire si l'objet a un prêt actif en cours
        if ($this->itemRepository->hasActiveLoan($id)) {
            throw new RuntimeException("Suppression refusée : cet objet est actuellement emprunté par un membre.");
        }

        // 2. Refus si l'objet possède un historique de prêts passés (traçabilité et intégrité FK)
        if ($this->itemRepository->hasLoans($id)) {
            throw new RuntimeException(
                "Suppression refusée : cet objet possède un historique d'emprunts dans le club. Pour préserver la cohérence des archives, il ne peut pas être supprimé."
            );
        }

        return $this->itemRepository->delete($id);
    }

    // =========================================================================
    // Gestion des Catégories (Categories)
    // =========================================================================

    /**
     * @return array<Category>
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    /**
     * @return array<Category>
     */
    public function getCategoriesWithCount(): array
    {
        return $this->categoryRepository->findAllWithItemCount();
    }

    public function getCategory(int $id): ?Category
    {
        return $this->categoryRepository->findById($id);
    }

    public function createCategory(string $name): Category
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Le libellé de la catégorie est obligatoire.");
        }

        if ($this->categoryRepository->findByName($name) !== null) {
            throw new InvalidArgumentException("Une catégorie portant ce nom existe déjà.");
        }

        return $this->categoryRepository->create(new Category(null, $name));
    }

    public function updateCategory(int $id, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Le libellé de la catégorie est obligatoire.");
        }

        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            throw new RuntimeException("Catégorie introuvable.");
        }

        $existing = $this->categoryRepository->findByName($name);
        if ($existing !== null && $existing->getId() !== $id) {
            throw new InvalidArgumentException("Une autre catégorie porte déjà ce nom.");
        }

        $category->setName($name);

        return $this->categoryRepository->update($category);
    }

    /**
     * Supprime une catégorie si elle ne contient aucun équipement.
     *
     * @throws RuntimeException Si la catégorie contient au moins un équipement
     */
    public function deleteCategory(int $id): bool
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            throw new RuntimeException("Catégorie introuvable.");
        }

        $itemCount = $this->categoryRepository->countItems($id);
        if ($itemCount > 0) {
            throw new RuntimeException(
                sprintf("Suppression refusée : la catégorie '%s' contient %d matériel(s). Veuillez d'abord supprimer ou déplacer ces matériels.", $category->getName(), $itemCount)
            );
        }

        return $this->categoryRepository->delete($id);
    }
}
