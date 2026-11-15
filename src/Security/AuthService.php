<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Env;
use App\Model\User;
use App\Repository\UserRepository;
use League\OAuth2\Client\Provider\Google;
use RuntimeException;

class AuthService
{
    private ?Google $provider = null;

    public function __construct(
        private UserRepository $userRepository,
        private SessionManager $sessionManager,
        ?Google $provider = null
    ) {
        $this->provider = $provider;
    }

    private function getProvider(): Google
    {
        if ($this->provider === null) {
            $clientId = (string) Env::get('GOOGLE_CLIENT_ID', '');
            $clientSecret = (string) Env::get('GOOGLE_CLIENT_SECRET', '');
            $redirectUri = (string) Env::get('GOOGLE_REDIRECT_URI', '');

            if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
                throw new RuntimeException("Configuration Google OAuth incomplète dans les variables d'environnement.");
            }

            $this->provider = new Google([
                'clientId'     => $clientId,
                'clientSecret' => $clientSecret,
                'redirectUri'  => $redirectUri,
            ]);
        }

        return $this->provider;
    }

    /**
     * Génère l'URL d'autorisation Google avec un jeton state et un nonce anti-rejeu stockés en session.
     */
    public function getAuthorizationUrl(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $this->sessionManager->setOauthNonce($nonce);

        $authUrl = $this->getProvider()->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ]);

        $this->sessionManager->setOauthState($this->getProvider()->getState());

        return $authUrl;
    }

    /**
     * Traite le retour du flux d'autorisation Google OAuth 2.0.
     *
     * @throws RuntimeException En cas d'incohérence de state, token ou e-mail non vérifié
     */
    public function handleCallback(string $code, string $state): User
    {
        $storedState = $this->sessionManager->getOauthState();
        if ($storedState === null || !hash_equals($storedState, $state)) {
            $this->sessionManager->clearOauthData();
            throw new RuntimeException("Validation de sécurité échouée : jeton 'state' invalide ou expiré.");
        }

        // Échange du code d'autorisation contre un jeton d'accès
        $token = $this->getProvider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
        $googleUser = $this->getProvider()->getResourceOwner($token);

        // Validation obligatoire de l'e-mail vérifié par Google
        $isVerified = (bool) ($googleUser->getEmailVerified() ?? false);
        if (!$isVerified) {
            $this->sessionManager->clearOauthData();
            throw new RuntimeException("Connexion refusée : l'adresse e-mail de votre compte Google n'est pas vérifiée.");
        }

        $googleSub = (string) $googleUser->getId();
        $email = (string) $googleUser->getEmail();
        $name = (string) ($googleUser->getName() ?: explode('@', $email)[0]);

        $adminEmail = strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        $isMatchingAdmin = ($adminEmail !== '' && strtolower(trim($email)) === $adminEmail);

        // Recherche par Google sub (identifiant pérenne et stable)
        $user = $this->userRepository->findByGoogleSub($googleSub);

        if ($user === null) {
            // Recherche par e-mail en cas de premier rattachement Google
            $user = $this->userRepository->findByEmail($email);
        }

        if ($user === null) {
            // Première connexion : création automatique du compte
            $role = $isMatchingAdmin ? 'admin' : 'member';
            $user = new User(
                id: null,
                googleSub: $googleSub,
                email: $email,
                name: $name,
                role: $role
            );
            $user = $this->userRepository->create($user);
        } else {
            // Utilisateur existant : promotion si e-mail == ADMIN_EMAIL (jamais de rétrogradation automatique)
            if ($isMatchingAdmin && !$user->isAdmin()) {
                $this->userRepository->updateRole((int) $user->getId(), 'admin');
                $user->setRole('admin');
            }

            // Mise à jour des informations (google_sub, name, email) si modifiées
            $user->setGoogleSub($googleSub);
            $user->setName($name);
            $user->setEmail($email);
            $this->userRepository->update($user);
        }

        // Nettoyage des données temporaires OAuth
        $this->sessionManager->clearOauthData();

        // Régénération de l'identifiant de session contre la fixation de session
        $this->sessionManager->regenerate();

        // Stockage de l'utilisateur connecté en session
        $this->sessionManager->setUser($user);

        return $user;
    }

    public function logout(): void
    {
        $this->sessionManager->destroy();
    }
}
