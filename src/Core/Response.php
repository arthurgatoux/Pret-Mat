<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = []
    ) {}

    public static function html(string $content, int $statusCode = 200): self
    {
        return new self($content, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
        echo $this->content;
    }
}
