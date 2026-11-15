<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Loan;
use RuntimeException;

class AccessControl
{
    /**
     * Exige que l'utilisateur soit authentifié.
     * Si un UserRepository est fourni, vérifie également que le compte existe toujours en base de données.
     *
     * @throws RuntimeException Si l'utilisateur n'est pas connecté
     */
    public static function requireAuth(SessionManager $session, ?\App\Repository\UserRepository $userRepository = null): void
    {
        if (!$session->isLoggedIn()) {
            $session->addFlash('error', 'Vous devez être connecté pour accéder à cette page.');
            header('Location: /');
            exit;
        }

        if ($userRepository !== null) {
            $userId = $session->getUserId();
            if ($userId === null || $userRepository->findById($userId) === null) {
                // L'utilisateur n'existe plus en base (ex: réinitialisation de base) : purge de la session orpheline
                $session->destroy();
                $session->start();
                $session->addFlash('error', 'Votre session a expiré ou votre compte est introuvable. Veuillez vous reconnecter via Google.');
                header('Location: /');
                exit;
            }
        }
    }

    /**
     * Exige que l'utilisateur dispose du rôle administrateur.
     *
     * @throws RuntimeException Si l'utilisateur n'a pas les droits requis (403 Forbidden)
     */
    public static function requireAdmin(SessionManager $session, ?\App\Repository\UserRepository $userRepository = null): void
    {
        self::requireAuth($session, $userRepository);

        if (!$session->isAdmin()) {
            http_response_code(403);
            $session->addFlash('error', 'Accès refusé : privilèges administrateur requis.');
            header('Location: /');
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur en session a le droit d'interagir avec un prêt donné (propriétaire ou administrateur).
     */
    public static function canManageLoan(SessionManager $session, Loan $loan): bool
    {
        if (!$session->isLoggedIn()) {
            return false;
        }

        if ($session->isAdmin()) {
            return true;
        }

        return $session->getUserId() === $loan->getUserId();
    }

    /**
     * Contrôle strict de propriété sur un prêt pour empêcher les failles IDOR.
     */
    public static function requireLoanOwnership(SessionManager $session, Loan $loan): void
    {
        self::requireAuth($session);

        if (!self::canManageLoan($session, $loan)) {
            http_response_code(403);
            $session->addFlash('error', 'Action non autorisée : vous ne pouvez pas agir sur un prêt qui ne vous appartient pas.');
            header('Location: /mes-prets');
            exit;
        }
    }
}
