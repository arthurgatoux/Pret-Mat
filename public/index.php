<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AdminCategoryController;
use App\Controller\AdminItemController;
use App\Controller\AdminLoanController;
use App\Controller\AuthController;
use App\Controller\CatalogController;
use App\Controller\MyLoansController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Security\AuthService;
use App\Security\CsrfManager;
use App\Security\SessionManager;
use App\Service\AuditService;
use App\Service\CatalogService;
use App\Service\LoanService;

// 1. En-têtes HTTP de sécurité obligatoires
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// 2. Chargement non bloquant des variables d'environnement
Env::load(dirname(__DIR__));

// 3. Initialisation et sécurisation de la session
$sessionManager = new SessionManager();
$sessionManager->start();

$csrfManager = new CsrfManager();

// 4. Initialisation des composants d'infrastructure (injection de dépendances sans singleton)
$database = Database::fromEnv();
$userRepository = new UserRepository($database);
$categoryRepository = new CategoryRepository($database);
$itemRepository = new ItemRepository($database);
$loanRepository = new LoanRepository($database);

$auditService = new AuditService();
$authService = new AuthService($userRepository, $sessionManager);
$loanService = new LoanService($database, $loanRepository, $itemRepository, $auditService);
$catalogService = new CatalogService($categoryRepository, $itemRepository);

// Contrôleurs
$authController = new AuthController($authService, $sessionManager, $csrfManager, $auditService);
$catalogController = new CatalogController($catalogService, $loanService, $sessionManager, $csrfManager, $userRepository);
$myLoansController = new MyLoansController($loanService, $sessionManager, $csrfManager, $userRepository);
$adminItemController = new AdminItemController($catalogService, $sessionManager, $csrfManager, $userRepository);
$adminCategoryController = new AdminCategoryController($catalogService, $sessionManager, $csrfManager, $userRepository);
$adminLoanController = new AdminLoanController($loanService, $sessionManager, $csrfManager, $userRepository);

$request = Request::createFromGlobals();
$router = new Router();

// 5. Déclaration des routes (6 pages + actions métier)
// Page 1 : Accueil
$router->get('/', function () use ($sessionManager, $csrfManager) {
    return Response::html(View::render('home/index', [
        'title'   => 'Accueil - PretMat',
        'session' => $sessionManager,
        'csrf'    => $csrfManager,
    ]));
});

// Authentification Google OAuth 2.0
$router->get('/auth/google', [$authController, 'login']);
$router->get('/auth/google/callback', [$authController, 'callback']);
$router->post('/logout', [$authController, 'logout']);

// Page 2 : Catalogue & Emprunt
$router->get('/catalogue', [$catalogController, 'index']);
$router->post('/emprunt', [$catalogController, 'borrow']);

// Page 3 : Mes prêts & Restitution
$router->get('/mes-prets', [$myLoansController, 'index']);
$router->post('/mes-prets/rendre', [$myLoansController, 'returnLoan']);

// Page 4 : Admin Matériel (CRUD)
$router->get('/admin/items', [$adminItemController, 'index']);
$router->post('/admin/items/create', [$adminItemController, 'create']);
$router->post('/admin/items/update', [$adminItemController, 'update']);
$router->post('/admin/items/delete', [$adminItemController, 'delete']);

// Page 5 : Admin Catégories (CRUD)
$router->get('/admin/categories', [$adminCategoryController, 'index']);
$router->post('/admin/categories/create', [$adminCategoryController, 'create']);
$router->post('/admin/categories/update', [$adminCategoryController, 'update']);
$router->post('/admin/categories/delete', [$adminCategoryController, 'delete']);

// Page 6 : Admin Prêts (vue globale & clôture)
$router->get('/admin/loans', [$adminLoanController, 'index']);
$router->post('/admin/loans/close', [$adminLoanController, 'close']);

// 6. Traitement de la requête avec gestion d'erreurs adaptée
try {
    $response = $router->dispatch($request);
    $response->send();
} catch (\Throwable $e) {
    if (Env::get('APP_ENV') === 'production') {
        error_log('Erreur serveur non gérée : ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        Response::html(View::render('home/500', [
            'title'   => 'Erreur serveur - PretMat',
            'session' => $sessionManager,
            'csrf'    => $csrfManager,
        ]), 500)->send();
    } else {
        Response::html(sprintf(
            '<div style="font-family: monospace; padding: 20px; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 8px;">' .
            '<h2>Erreur applicative (Mode Développement)</h2>' .
            '<p><strong>Message :</strong> %s</p>' .
            '<p><strong>Fichier :</strong> %s:%d</p>' .
            '<pre style="background: #fff; padding: 12px; border-radius: 4px; overflow: auto;">%s</pre>' .
            '</div>',
            htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
        ), 500)->send();
    }
}
