<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\LoanService;

Env::load(dirname(__DIR__));
$database = Database::fromEnv();

$catRepo = new CategoryRepository($database);
$itemRepo = new ItemRepository($database);
$loanRepo = new LoanRepository($database);
$userRepo = new UserRepository($database);

echo "=== TEST MODULE 6 : AUDIT SERVICE MONGODB (F8) ===" . PHP_EOL;

// 1. Test AuditService avec la connexion MongoDB Atlas configurée
echo PHP_EOL . "--- 1. Test AuditService (MongoDB Atlas actif) ---" . PHP_EOL;
$auditService = new AuditService();

$testUserId = 2; // Bob Martin
$testItemId = 8; // Softbox

// 1.1 Journalisation de l'action 'connexion'
$auditService->logLogin($testUserId);
echo "[OK] Journalisation 'connexion' envoyée" . PHP_EOL;

// 1.2 Journalisation de l'action 'emprunt'
$auditService->logBorrow($testUserId, $testItemId);
echo "[OK] Journalisation 'emprunt' envoyée" . PHP_EOL;

// 1.3 Journalisation de l'action 'retour'
$auditService->logReturn($testUserId, $testItemId);
echo "[OK] Journalisation 'retour' envoyée" . PHP_EOL;

// 1.4 Vérification du document stocké dans MongoDB Atlas
$latestLogs = $auditService->getLatestLogs(3);
assert(!empty($latestLogs), 'Les logs doivent être présents dans MongoDB Atlas');
echo "[OK] Lecture des logs depuis MongoDB Atlas réussie (" . count($latestLogs) . " récents récupérés)" . PHP_EOL;

// Vérification de la conformité du schéma et de l'absence totale de données sensibles
$forbiddenKeys = ['email', 'password', 'token', 'google_sub', 'sub', 'secret'];
foreach ($latestLogs as $log) {
    assert(isset($log['date']), "Le champ 'date' doit être présent");
    assert(isset($log['action']), "Le champ 'action' doit être présent");
    assert(isset($log['user_id']), "Le champ 'user_id' doit être présent");
    assert(in_array($log['action'], ['connexion', 'emprunt', 'retour'], true), "Action inconnue : {$log['action']}");

    foreach ($forbiddenKeys as $forbidden) {
        assert(!array_key_exists($forbidden, (array) $log), "Alerte sécurité : donnée sensible détectée ({$forbidden})");
    }
}
echo "[OK] Conformité des données et absence totale de données sensibles validée" . PHP_EOL;

// 2. Test d'indisponibilité de MongoDB (Résilience & continuité de service)
echo PHP_EOL . "--- 2. Test de résilience : MongoDB injoignable (dégradation gracieuse) ---" . PHP_EOL;

// AuditService pointant sur une adresse inaccessible avec timeout court
$offlineAuditService = new AuditService(
    uri: 'mongodb://10.255.255.1:27017', // Adresse IP non routable / inaccessible
    dbName: 'pretmat_test'
);

// L'appel direct ne doit lever AUCUNE exception
$offlineAuditService->logLogin(999);
$offlineAuditService->logBorrow(999, 999);
$offlineAuditService->logReturn(999, 999);
echo "[OK] Appels log() sur MongoDB injoignable : aucune exception levée, non bloquant" . PHP_EOL;

// Test d'un emprunt métier avec ce service d'audit hors-ligne
$loanServiceWithOfflineAudit = new LoanService($database, $loanRepo, $itemRepo, $offlineAuditService);

// Objet 3 (Trépied) est disponible
$resilientLoan = $loanServiceWithOfflineAudit->borrow(1, 3, 2);
assert($resilientLoan->getId() !== null, "L'emprunt métier doit réussir même si MongoDB est hors-ligne");
echo "[OK] Emprunt métier exécuté avec succès malgré MongoDB hors-ligne" . PHP_EOL;

// Restitution métier avec service d'audit hors-ligne
$resilientReturn = $loanServiceWithOfflineAudit->returnLoan($resilientLoan->getId(), 1, false);
assert($resilientReturn === true, "La restitution métier doit réussir même si MongoDB est hors-ligne");
echo "[OK] Restitution métier exécutée avec succès malgré MongoDB hors-ligne" . PHP_EOL;

// Nettoyage du prêt de test
$database->getConnection()->exec("DELETE FROM loans WHERE id = {$resilientLoan->getId()}");

echo PHP_EOL . "=== TOUS LES TESTS DU MODULE 6 ONT RÉUSSI ===" . PHP_EOL;
