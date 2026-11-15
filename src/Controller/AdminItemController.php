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
use Throwable;

class AdminItemController
{
    public function __construct(
        private CatalogService $catalogService,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private ?UserRepository $userRepository = null
    ) {}

    public function index(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        $items = $this->catalogService->getAllItems();
        $categories = $this->catalogService->getAllCategories();

        return Response::html(View::render('admin/items', [
            'title'      => 'Gestion du matériel - Admin PretMat',
            'session'    => $this->sessionManager,
            'csrf'       => $this->csrfManager,
            'items'      => $items,
            'categories' => $categories,
        ]));
    }

    public function create(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/items');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/items');
        }

        $name = (string) $request->getPost('name', '');
        $description = (string) $request->getPost('description', '');
        $categoryId = (int) $request->getPost('category_id', 0);

        try {
            $this->catalogService->createItem($name, $description, $categoryId);
            $this->sessionManager->addFlash('success', 'Nouveau matériel ajouté avec succès.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/items');
    }

    public function update(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/items');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/items');
        }

        $id = (int) $request->getPost('id', 0);
        $name = (string) $request->getPost('name', '');
        $description = (string) $request->getPost('description', '');
        $categoryId = (int) $request->getPost('category_id', 0);

        try {
            $this->catalogService->updateItem($id, $name, $description, $categoryId);
            $this->sessionManager->addFlash('success', 'Matériel mis à jour avec succès.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/items');
    }

    public function delete(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/items');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/items');
        }

        $id = (int) $request->getPost('id', 0);

        try {
            $this->catalogService->deleteItem($id);
            $this->sessionManager->addFlash('success', 'Le matériel a été supprimé.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/items');
    }
}
