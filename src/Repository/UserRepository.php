<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\User;
use DateTimeImmutable;
use PDO;

class UserRepository
{
    public function __construct(
        private Database $database
    ) {}

    private function getPdo(): PDO
    {
        return $this->database->getConnection();
    }

    /**
     * Recherche un utilisateur par son identifiant stable Google OpenID (sub).
     */
    public function findByGoogleSub(string $googleSub): ?User
    {
        $stmt = $this->getPdo()->prepare('SELECT id, google_sub, email, name, role, created_at FROM users WHERE google_sub = :google_sub LIMIT 1');
        $stmt->execute(['google_sub' => $googleSub]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Recherche un utilisateur par son identifiant primaire interne.
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->getPdo()->prepare('SELECT id, google_sub, email, name, role, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Recherche un utilisateur par son adresse e-mail.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->getPdo()->prepare('SELECT id, google_sub, email, name, role, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Crée un nouvel utilisateur suite à sa première authentification Google réussie.
     */
    public function create(User $user): User
    {
        $stmt = $this->getPdo()->prepare(
            'INSERT INTO users (google_sub, email, name, role, created_at) VALUES (:google_sub, :email, :name, :role, NOW())'
        );
        $stmt->execute([
            'google_sub' => $user->getGoogleSub(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getRole(),
        ]);

        $user->setId((int) $this->getPdo()->lastInsertId());

        return $user;
    }

    /**
     * Met à jour le rôle d'un compte (par exemple promotion en admin lors de la correspondance avec ADMIN_EMAIL).
     */
    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->getPdo()->prepare('UPDATE users SET role = :role WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'role' => $role,
        ]);
    }

    /**
     * Met à jour le profil (nom, email) d'un utilisateur existant.
     */
    public function update(User $user): bool
    {
        $stmt = $this->getPdo()->prepare('UPDATE users SET google_sub = :google_sub, name = :name, email = :email, role = :role WHERE id = :id');

        return $stmt->execute([
            'id' => $user->getId(),
            'google_sub' => $user->getGoogleSub(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): User
    {
        return new User(
            id: (int) $data['id'],
            googleSub: (string) $data['google_sub'],
            email: (string) $data['email'],
            name: (string) $data['name'],
            role: (string) $data['role'],
            createdAt: isset($data['created_at']) ? new DateTimeImmutable((string) $data['created_at']) : null
        );
    }
}
