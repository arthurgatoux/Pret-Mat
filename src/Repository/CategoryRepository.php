<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Category;
use PDO;

class CategoryRepository
{
    public function __construct(
        private Database $database
    ) {}

    private function getPdo(): PDO
    {
        return $this->database->getConnection();
    }

    /**
     * @return array<Category>
     */
    public function findAll(): array
    {
        $stmt = $this->getPdo()->query('SELECT id, name FROM categories ORDER BY name ASC');
        $rows = $stmt->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Récupère toutes les catégories avec le nombre d'objets rattachés via une jointure externe groupée.
     *
     * @return array<Category>
     */
    public function findAllWithItemCount(): array
    {
        $sql = 'SELECT c.id, c.name, COUNT(i.id) AS item_count
                FROM categories c
                LEFT JOIN items i ON i.category_id = c.id
                GROUP BY c.id, c.name
                ORDER BY c.name ASC';

        $stmt = $this->getPdo()->query($sql);
        $rows = $stmt->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?Category
    {
        $stmt = $this->getPdo()->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByName(string $name): ?Category
    {
        $stmt = $this->getPdo()->prepare('SELECT id, name FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Compte le nombre d'équipements rattachés à une catégorie pour valider l'interdiction de suppression si non vide.
     */
    public function countItems(int $categoryId): int
    {
        $stmt = $this->getPdo()->prepare('SELECT COUNT(*) FROM items WHERE category_id = :category_id');
        $stmt->execute(['category_id' => $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(Category $category): Category
    {
        $stmt = $this->getPdo()->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute(['name' => $category->getName()]);

        $category->setId((int) $this->getPdo()->lastInsertId());

        return $category;
    }

    public function update(Category $category): bool
    {
        $stmt = $this->getPdo()->prepare('UPDATE categories SET name = :name WHERE id = :id');

        return $stmt->execute([
            'id' => $category->getId(),
            'name' => $category->getName(),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->getPdo()->prepare('DELETE FROM categories WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Category
    {
        return new Category(
            id: (int) $data['id'],
            name: (string) $data['name'],
            itemCount: isset($data['item_count']) ? (int) $data['item_count'] : 0
        );
    }
}
