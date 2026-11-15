<?php

declare(strict_types=1);

namespace App\Security;

class CsrfManager
{
    public function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf_token'];
    }

    public function renderInput(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');

        return sprintf('<input type="hidden" name="csrf_token" value="%s">', $token);
    }

    public function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionToken = $_SESSION['_csrf_token'] ?? null;

        if (!is_string($sessionToken) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
