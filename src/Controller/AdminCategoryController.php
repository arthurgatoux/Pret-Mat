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

class AdminCategoryController
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

        $categories = $this->catalogService->getCategoriesWithCount();

        return Response::html(View::render('admin/categories', [
            'title'      => 'Gestion des catégories - Admin PretMat',
            'session'    => $this->sessionManager,
            'csrf'       => $this->csrfManager,
            'categories' => $categories,
        ]));
    }

    public function create(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/categories');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/categories');
        }

        $name = (string) $request->getPost('name', '');

        try {
            $this->catalogService->createCategory($name);
            $this->sessionManager->addFlash('success', 'Catégorie créée avec succès.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/categories');
    }

    public function update(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/categories');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/categories');
        }

        $id = (int) $request->getPost('id', 0);
        $name = (string) $request->getPost('name', '');

        try {
            $this->catalogService->updateCategory($id, $name);
            $this->sessionManager->addFlash('success', 'Catégorie mise à jour.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/categories');
    }

    public function delete(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/categories');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/categories');
        }

        $id = (int) $request->getPost('id', 0);

        try {
            $this->catalogService->deleteCategory($id);
            $this->sessionManager->addFlash('success', 'La catégorie a été supprimée.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/categories');
    }
}
