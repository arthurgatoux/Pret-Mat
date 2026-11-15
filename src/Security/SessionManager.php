<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Env;
use App\Model\User;

class SessionManager
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || Env::get('APP_ENV') === 'production';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public function setUser(User $user): void
    {
        $_SESSION['user'] = [
            'id' => $user->getId(),
            'google_sub' => $user->getGoogleSub(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getRole(),
        ];
    }

    /**
     * @return array{id: int, google_sub: string, email: string, name: string, role: string}|null
     */
    public function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }

    public function getUserRole(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public function getUserName(): ?string
    {
        return $_SESSION['user']['name'] ?? null;
    }

    public function getUserEmail(): ?string
    {
        return $_SESSION['user']['email'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && ($this->getUserRole() === 'admin');
    }

    public function isMember(): bool
    {
        return $this->isLoggedIn() && ($this->getUserRole() === 'member');
    }

    // Gestion OAuth state & nonce
    public function setOauthState(string $state): void
    {
        $_SESSION['oauth2_state'] = $state;
    }

    public function getOauthState(): ?string
    {
        return $_SESSION['oauth2_state'] ?? null;
    }

    public function setOauthNonce(string $nonce): void
    {
        $_SESSION['oauth2_nonce'] = $nonce;
    }

    public function getOauthNonce(): ?string
    {
        return $_SESSION['oauth2_nonce'] ?? null;
    }

    public function clearOauthData(): void
    {
        unset($_SESSION['oauth2_state'], $_SESSION['oauth2_nonce']);
    }

    // Messages Flash
    public function addFlash(string $type, string $message): void
    {
        if (!isset($_SESSION['_flashes'])) {
            $_SESSION['_flashes'] = [];
        }
        $_SESSION['_flashes'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<array{type: string, message: string}>
     */
    public function getFlashes(): array
    {
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);

        return $flashes;
    }
}
