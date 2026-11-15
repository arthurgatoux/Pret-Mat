<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Security\AuthService;
use App\Security\CsrfManager;
use App\Security\SessionManager;
use App\Service\AuditService;
use Throwable;

class AuthController
{
    public function __construct(
        private AuthService $authService,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private ?AuditService $auditService = null
    ) {}

    /**
     * Démarre le flux d'authentification Google OAuth 2.0.
     */
    public function login(Request $request): Response
    {
        if ($this->sessionManager->isLoggedIn()) {
            return Response::redirect('/');
        }

        try {
            $authUrl = $this->authService->getAuthorizationUrl();

            return Response::redirect($authUrl);
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', "Impossible d'initialiser la connexion Google : " . $e->getMessage());

            return Response::redirect('/');
        }
    }

    /**
     * Traite le retour d'autorisation de Google (Callback).
     */
    public function callback(Request $request): Response
    {
        $code = (string) $request->getQuery('code', '');
        $state = (string) $request->getQuery('state', '');
        $error = $request->getQuery('error');

        if ($error !== null) {
            $this->sessionManager->addFlash('error', 'Connexion Google annulée ou refusée.');

            return Response::redirect('/');
        }

        if ($code === '' || $state === '') {
            $this->sessionManager->addFlash('error', 'Paramètres de retour Google OAuth incomplets.');

            return Response::redirect('/');
        }

        try {
            $user = $this->authService->handleCallback($code, $state);

            // Journalisation de la connexion dans MongoDB (F8 - non bloquant)
            if ($user->getId() !== null) {
                $this->auditService?->logLogin((int) $user->getId());
            }

            $this->sessionManager->addFlash('success', sprintf('Bienvenue, %s ! Vous êtes connecté.', $user->getName()));

            return Response::redirect('/catalogue');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Erreur lors de la connexion : ' . $e->getMessage());

            return Response::redirect('/');
        }
    }

    /**
     * Déconnexion sécurisée de l'utilisateur (requête POST avec jeton CSRF obligatoire).
     */
    public function logout(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide lors de la déconnexion.');

            return Response::redirect('/');
        }

        $this->authService->logout();
        $this->sessionManager->start();
        $this->sessionManager->addFlash('info', 'Vous avez été déconnecté avec succès.');

        return Response::redirect('/');
    }
}
