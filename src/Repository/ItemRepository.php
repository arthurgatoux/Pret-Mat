<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Item;
use DateTimeImmutable;
use PDO;

class ItemRepository
{
    public function __construct(
        private Database $database
    ) {}

    private function getPdo(): PDO
    {
        return $this->database->getConnection();
    }

    /**
     * Sélectionne les matériels avec leur catégorie et disponibilité calculée (absence de prêt actif), avec filtre catégorie optionnel.
     *
     * @return array<Item>
     */
    public function findCatalog(?int $categoryId = null): array
    {
        if ($categoryId !== null) {
            $sql = 'SELECT i.id, i.name, i.description, i.category_id, i.created_at,
                           c.name AS category_name,
                           (CASE WHEN active_loans.id IS NULL THEN 1 ELSE 0 END) AS is_available
                    FROM items i
                    INNER JOIN categories c ON i.category_id = c.id
                    LEFT JOIN loans active_loans ON active_loans.item_id = i.id AND active_loans.returned_at IS NULL
                    WHERE i.category_id = :category_id
                    ORDER BY i.name ASC';

            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute(['category_id' => $categoryId]);
        } else {
            $sql = 'SELECT i.id, i.name, i.description, i.category_id, i.created_at,
                           c.name AS category_name,
                           (CASE WHEN active_loans.id IS NULL THEN 1 ELSE 0 END) AS is_available
                    FROM items i
                    INNER JOIN categories c ON i.category_id = c.id
                    LEFT JOIN loans active_loans ON active_loans.item_id = i.id AND active_loans.returned_at IS NULL
                    ORDER BY i.name ASC';

            $stmt = $this->getPdo()->query($sql);
        }

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Récupère tous les matériels avec leur catégorie pour la vue d'administration.
     *
     * @return array<Item>
     */
    public function findAll(): array
    {
        return $this->findCatalog(null);
    }

    public function findById(int $id): ?Item
    {
        $sql = 'SELECT i.id, i.name, i.description, i.category_id, i.created_at,
                       c.name AS category_name,
                       (CASE WHEN active_loans.id IS NULL THEN 1 ELSE 0 END) AS is_available
                FROM items i
                INNER JOIN categories c ON i.category_id = c.id
                LEFT JOIN loans active_loans ON active_loans.item_id = i.id AND active_loans.returned_at IS NULL
                WHERE i.id = :id
                LIMIT 1';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Vérifie si un matériel possède au moins un prêt (actif ou archivé) pour sécuriser l'interdiction de suppression.
     */
    public function hasLoans(int $itemId): bool
    {
        $stmt = $this->getPdo()->prepare('SELECT 1 FROM loans WHERE item_id = :item_id LIMIT 1');
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Vérifie si un matériel fait l'objet d'un prêt actif en cours (non restitué).
     */
    public function hasActiveLoan(int $itemId): bool
    {
        $stmt = $this->getPdo()->prepare('SELECT 1 FROM loans WHERE item_id = :item_id AND returned_at IS NULL LIMIT 1');
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(Item $item): Item
    {
        $stmt = $this->getPdo()->prepare(
            'INSERT INTO items (name, description, category_id, created_at) VALUES (:name, :description, :category_id, NOW())'
        );
        $stmt->execute([
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'category_id' => $item->getCategoryId(),
        ]);

        $item->setId((int) $this->getPdo()->lastInsertId());

        return $item;
    }

    public function update(Item $item): bool
    {
        $stmt = $this->getPdo()->prepare(
            'UPDATE items SET name = :name, description = :description, category_id = :category_id WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'category_id' => $item->getCategoryId(),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->getPdo()->prepare('DELETE FROM items WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Item
    {
        return new Item(
            id: (int) $data['id'],
            name: (string) $data['name'],
            description: $data['description'] !== null ? (string) $data['description'] : null,
            categoryId: (int) $data['category_id'],
            categoryName: isset($data['category_name']) ? (string) $data['category_name'] : null,
            isAvailable: isset($data['is_available']) ? (bool) $data['is_available'] : true,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable((string) $data['created_at']) : null
        );
    }
}
