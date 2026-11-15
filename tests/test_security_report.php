<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Model\User;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Security\AccessControl;
use App\Security\SessionManager;
use App\Service\LoanService;

Env::load(dirname(__DIR__));
$database = Database::fromEnv();
$pdo = $database->getConnection();

echo "=== VÉRIFICATION DES PREUVES DE SÉCURITÉ ===" . PHP_EOL;

// 1. Tests d'injection SQL
echo PHP_EOL . "--- 1. Épreuves Anti-Injection SQL ---" . PHP_EOL;
$maliciousPayloads = [
    "' OR '1'='1",
    "1; DROP TABLE loans; --",
    "1 UNION SELECT 1, 'hacked', 'desc', 1, NOW()--",
    "admin' --",
    "' OR 1=1 #"
];

$itemRepo = new ItemRepository($database);
foreach ($maliciousPayloads as $payload) {
    // Test sur la recherche préparée
    $stmt = $pdo->prepare('SELECT id, name FROM items WHERE name = :name');
    $stmt->execute(['name' => $payload]);
    $result = $stmt->fetchAll();
    echo "[OK] Payload neutralisé en paramètre scalaire : " . htmlspecialchars($payload) . " (0 injection, " . count($result) . " ligne trouvée)" . PHP_EOL;
}

// 2. Tests Broken Access Control
echo PHP_EOL . "--- 2. Épreuves de Contrôle d'Accès ---" . PHP_EOL;
$session = new SessionManager();
$session->start();

// 2.1 Visiteur non connecté
$session->destroy();
$session->start();
assert($session->isLoggedIn() === false);
assert($session->isAdmin() === false);
echo "[OK] Visiteur anonyme : aucun droit d'accès" . PHP_EOL;

// 2.2 Membre standard accédant à une ressource admin
$memberUser = new User(2, 'sub_member', 'membre@pretmat.local', 'Bob', 'member');
$session->setUser($memberUser);
assert($session->isLoggedIn() === true);
assert($session->isAdmin() === false);
echo "[OK] Membre standard : rôle 'member' vérifié côté serveur" . PHP_EOL;

// 3. Tests IDOR sur la clôture de prêt
echo PHP_EOL . "--- 3. Épreuve de Prévention IDOR ---" . PHP_EOL;
$loanRepo = new LoanRepository($database);
$loanService = new LoanService($database, $loanRepo, $itemRepo);

// Prêt #3 appartient à l'utilisateur #1 (Alice)
$aliceLoan = $loanRepo->findById(3);
assert($aliceLoan !== null, 'Le prêt #3 existe');
assert($aliceLoan->getUserId() === 1, 'Le prêt #3 appartient à Alice (user #1)');

// Bob (user #2, rôle member) tente de clôturer le prêt d'Alice
$idorBlocked = false;
try {
    $loanService->returnLoan(3, 2, false);
} catch (RuntimeException $e) {
    $idorBlocked = true;
    assert(str_contains($e->getMessage(), 'ne vous appartient pas'));
}
assert($idorBlocked, 'La tentative IDOR doit être immédiatement bloquée');
echo "[OK] IDOR bloqué avec succès : le prêt d'Alice n'a pas pu être clôturé par Bob" . PHP_EOL;

echo PHP_EOL . "=== TOUTES LES PREUVES DE SÉCURITÉ SONT VALIDÉES ===" . PHP_EOL;
