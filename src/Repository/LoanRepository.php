<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Loan;
use DateTimeImmutable;
use PDO;

class LoanRepository
{
    public function __construct(
        private Database $database
    ) {}

    private function getPdo(): PDO
    {
        return $this->database->getConnection();
    }

    public function findById(int $id): ?Loan
    {
        $sql = 'SELECT l.id, l.user_id, l.item_id, l.borrowed_at, l.due_at, l.returned_at,
                       u.name AS user_name, u.email AS user_email,
                       i.name AS item_name, c.name AS category_name,
                       (CASE WHEN l.returned_at IS NULL AND l.due_at < NOW() THEN 1 ELSE 0 END) AS is_late
                FROM loans l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN items i ON l.item_id = i.id
                INNER JOIN categories c ON i.category_id = c.id
                WHERE l.id = :id
                LIMIT 1';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Récupère l'intégralité des prêts du club avec les détails associés et le calcul dynamique de retard pour l'administration.
     *
     * @return array<Loan>
     */
    public function findAllWithDetails(): array
    {
        $sql = 'SELECT l.id, l.user_id, l.item_id, l.borrowed_at, l.due_at, l.returned_at,
                       u.name AS user_name, u.email AS user_email,
                       i.name AS item_name, c.name AS category_name,
                       (CASE WHEN l.returned_at IS NULL AND l.due_at < NOW() THEN 1 ELSE 0 END) AS is_late
                FROM loans l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN items i ON l.item_id = i.id
                INNER JOIN categories c ON i.category_id = c.id
                ORDER BY l.borrowed_at DESC';

        $stmt = $this->getPdo()->query($sql);

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Récupère l'historique complet des prêts (en cours et rendus) d'un membre avec indicateur de retard calculé.
     *
     * @return array<Loan>
     */
    public function findByUserId(int $userId): array
    {
        $sql = 'SELECT l.id, l.user_id, l.item_id, l.borrowed_at, l.due_at, l.returned_at,
                       u.name AS user_name, u.email AS user_email,
                       i.name AS item_name, c.name AS category_name,
                       (CASE WHEN l.returned_at IS NULL AND l.due_at < NOW() THEN 1 ELSE 0 END) AS is_late
                FROM loans l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN items i ON l.item_id = i.id
                INNER JOIN categories c ON i.category_id = c.id
                WHERE l.user_id = :user_id
                ORDER BY l.borrowed_at DESC';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Récupère uniquement les prêts non encore restitués d'un utilisateur, triés par date d'échéance la plus proche.
     *
     * @return array<Loan>
     */
    public function findActiveByUserId(int $userId): array
    {
        $sql = 'SELECT l.id, l.user_id, l.item_id, l.borrowed_at, l.due_at, l.returned_at,
                       u.name AS user_name, u.email AS user_email,
                       i.name AS item_name, c.name AS category_name,
                       (CASE WHEN l.due_at < NOW() THEN 1 ELSE 0 END) AS is_late
                FROM loans l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN items i ON l.item_id = i.id
                INNER JOIN categories c ON i.category_id = c.id
                WHERE l.user_id = :user_id AND l.returned_at IS NULL
                ORDER BY l.due_at ASC';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Compte le nombre d'emprunts actifs d'un membre pour contrôler la règle métier du plafond de 3 prêts simultanés.
     */
    public function countActiveByUserId(int $userId): int
    {
        $stmt = $this->getPdo()->prepare('SELECT COUNT(*) FROM loans WHERE user_id = :user_id AND returned_at IS NULL');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(Loan $loan): Loan
    {
        $stmt = $this->getPdo()->prepare(
            'INSERT INTO loans (user_id, item_id, borrowed_at, due_at, returned_at) VALUES (:user_id, :item_id, :borrowed_at, :due_at, :returned_at)'
        );
        $stmt->execute([
            'user_id' => $loan->getUserId(),
            'item_id' => $loan->getItemId(),
            'borrowed_at' => $loan->getBorrowedAt()->format('Y-m-d H:i:s'),
            'due_at' => $loan->getDueAt()->format('Y-m-d H:i:s'),
            'returned_at' => $loan->getReturnedAt()?->format('Y-m-d H:i:s'),
        ]);

        $loan->setId((int) $this->getPdo()->lastInsertId());

        return $loan;
    }

    /**
     * Enregistre la restitution effective d'un prêt en horodatant returned_at, uniquement si le prêt est encore actif.
     */
    public function closeLoan(int $id, ?DateTimeImmutable $returnedAt = null): bool
    {
        $returnedAtString = $returnedAt !== null ? $returnedAt->format('Y-m-d H:i:s') : null;

        $stmt = $this->getPdo()->prepare(
            'UPDATE loans SET returned_at = COALESCE(:returned_at, NOW()) WHERE id = :id AND returned_at IS NULL'
        );

        $stmt->execute([
            'id' => $id,
            'returned_at' => $returnedAtString,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Loan
    {
        return new Loan(
            id: (int) $data['id'],
            userId: (int) $data['user_id'],
            itemId: (int) $data['item_id'],
            borrowedAt: new DateTimeImmutable((string) $data['borrowed_at']),
            dueAt: new DateTimeImmutable((string) $data['due_at']),
            returnedAt: isset($data['returned_at']) && $data['returned_at'] !== null
                ? new DateTimeImmutable((string) $data['returned_at'])
                : null,
            userName: isset($data['user_name']) ? (string) $data['user_name'] : null,
            userEmail: isset($data['user_email']) ? (string) $data['user_email'] : null,
            itemName: isset($data['item_name']) ? (string) $data['item_name'] : null,
            categoryName: isset($data['category_name']) ? (string) $data['category_name'] : null,
            isLate: isset($data['is_late']) ? (bool) $data['is_late'] : null
        );
    }
}
