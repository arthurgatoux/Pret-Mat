<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(
        string $host,
        int|string $port,
        string $dbname,
        string $user,
        string $password
    ) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $user, $password, $options);
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) Env::get('DB_HOST', 'db'),
            (int) Env::get('DB_PORT', 3306),
            (string) Env::get('DB_NAME', 'pretmat'),
            (string) Env::get('DB_USER', 'pretmat_user'),
            (string) Env::get('DB_PASSWORD', '')
        );
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
