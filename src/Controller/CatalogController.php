<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\UserRepository;
use App\Security\AccessControl;
use App\Security\CsrfManager;
use App\Security\SessionManager;
use App\Service\CatalogService;
use App\Service\LoanService;
use Throwable;

class CatalogController
{
    public function __construct(
        private CatalogService $catalogService,
        private LoanService $loanService,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private ?UserRepository $userRepository = null
    ) {}

    /**
     * Page Catalogue : liste des équipements, filtre par catégorie, statut de disponibilité.
     */
    public function index(Request $request): Response
    {
        $rawCategory = $request->getQuery('category');
        $selectedCategoryId = ($rawCategory !== null && is_numeric($rawCategory)) ? (int) $rawCategory : null;

        $categories = $this->catalogService->getAllCategories();
        $items = $this->catalogService->getCatalog($selectedCategoryId);

        return Response::html(View::render('catalog/index', [
            'title'              => 'Catalogue du matériel - PretMat',
            'session'            => $this->sessionManager,
            'csrf'               => $this->csrfManager,
            'categories'         => $categories,
            'items'              => $items,
            'selectedCategoryId' => $selectedCategoryId,
        ]));
    }

    /**
     * Traitement de l'emprunt d'un matériel (POST).
     */
    public function borrow(Request $request): Response
    {
        AccessControl::requireAuth($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/catalogue');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Session ou jeton de sécurité expiré. Veuillez réessayer.');

            return Response::redirect('/catalogue');
        }

        $userId = (int) $this->sessionManager->getUserId();

        // Contrôle de cohérence : l'utilisateur doit exister en base de données
        if ($this->userRepository !== null && $this->userRepository->findById($userId) === null) {
            $this->sessionManager->destroy();
            $this->sessionManager->start();
            $this->sessionManager->addFlash('error', 'Votre session a expiré ou votre compte est introuvable. Veuillez vous reconnecter via Google.');

            return Response::redirect('/');
        }

        $itemId = (int) $request->getPost('item_id', 0);
        $days = (int) $request->getPost('days', 0);

        try {
            $this->loanService->borrow($userId, $itemId, $days);
            $this->sessionManager->addFlash('success', sprintf("L'emprunt a été validé pour une durée de %d jour(s) !", $days));

            return Response::redirect('/mes-prets');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return Response::redirect('/catalogue');
        }
    }
}
