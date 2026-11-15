<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postParams
     * @param array<string, mixed> $serverParams
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $queryParams,
        private array $postParams,
        private array $serverParams
    ) {}

    public static function createFromGlobals(): self
    {
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($rawUri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return new self($method, $path, $_GET, $_POST, $_SERVER);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllPost(): array
    {
        return $this->postParams;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
