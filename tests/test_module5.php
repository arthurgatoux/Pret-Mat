<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Model\Loan;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Service\CatalogService;
use App\Service\LoanService;

Env::load(dirname(__DIR__));
$database = Database::fromEnv();

$catRepo = new CategoryRepository($database);
$itemRepo = new ItemRepository($database);
$loanRepo = new LoanRepository($database);
$userRepo = new UserRepository($database);

$loanService = new LoanService($database, $loanRepo, $itemRepo);
$catalogService = new CatalogService($catRepo, $itemRepo);

echo "=== TEST MODULE 5 : SERVICES MÉTIER & RÈGLES DE GESTION ===" . PHP_EOL;

// 1. Tests LoanService
echo PHP_EOL . "--- 1. Tests LoanService ---" . PHP_EOL;

// 1.1 Validation de la durée côté serveur (1 à 14 jours)
$caughtDuration0 = false;
try {
    $loanService->borrow(2, 2, 0);
} catch (InvalidArgumentException $e) {
    $caughtDuration0 = true;
    assert(str_contains($e->getMessage(), 'entre 1 et 14 jours'));
}
assert($caughtDuration0, 'Durée 0 jour doit lever une exception');

$caughtDuration15 = false;
try {
    $loanService->borrow(2, 2, 15);
} catch (InvalidArgumentException $e) {
    $caughtDuration15 = true;
    assert(str_contains($e->getMessage(), 'entre 1 et 14 jours'));
}
assert($caughtDuration15, 'Durée 15 jours doit lever une exception');
echo "[OK] Validation durée d'emprunt (1 à 14 jours) validée côté serveur" . PHP_EOL;

// 1.2 Emprunt d'un objet disponible
// Objet 2 (Objectif Sony FE 24-70) est disponible
$newLoan = $loanService->borrow(1, 2, 5);
assert($newLoan->getId() !== null, 'Le prêt doit être créé');
assert($itemRepo->hasActiveLoan(2) === true, "L'objet 2 a désormais un prêt actif");
$expectedDue = (new DateTimeImmutable())->modify('+5 days')->format('Y-m-d');
assert($newLoan->getDueAt()->format('Y-m-d') === $expectedDue, 'due_at doit être égal à borrowed_at + 5 jours');
echo "[OK] Emprunt réussi avec transaction SELECT ... FOR UPDATE et due_at = borrowed_at + 5 jours" . PHP_EOL;

// 1.3 Tentative d'emprunt sur un objet déjà emprunté (verrouillage)
$caughtDoubleBorrow = false;
try {
    $loanService->borrow(2, 2, 3);
} catch (RuntimeException $e) {
    $caughtDoubleBorrow = true;
    assert(str_contains($e->getMessage(), 'indisponible'));
}
assert($caughtDoubleBorrow, 'Un objet déjà emprunté ne peut pas faire l objet d un second prêt');
echo "[OK] Règle : un seul prêt actif par objet strictement garantie" . PHP_EOL;

// 1.4 Plafond de 3 prêts actifs par membre
// User 1 a déjà 2 prêts actifs (item 7 depuis seed, item 2 qu'on vient d'emprunter).
// Empruntons un 3e prêt (item 3 Trépied)
$loan3 = $loanService->borrow(1, 3, 7);
assert($loanRepo->countActiveByUserId(1) === 3, 'User 1 a 3 prêts actifs');

// Tentative d'un 4e prêt pour User 1 (item 5 Shure SM7B)
$caughtLimit = false;
try {
    $loanService->borrow(1, 5, 2);
} catch (RuntimeException $e) {
    $caughtLimit = true;
    assert(str_contains($e->getMessage(), 'plafond maximal de 3 prêts actifs'));
}
assert($caughtLimit, 'Le 4e prêt doit être refusé pour respecter le quota de 3 prêts actifs');
echo "[OK] Règle : plafond strict de 3 prêts actifs simultanés par membre validé" . PHP_EOL;

// 1.5 Clôture / Restitution du prêt
// Non-propriétaire et non-admin (ex: User 2 essaie de rendre le prêt de User 1)
$caughtNonOwner = false;
try {
    $loanService->returnLoan($newLoan->getId(), 2, false);
} catch (RuntimeException $e) {
    $caughtNonOwner = true;
    assert(str_contains($e->getMessage(), 'ne vous appartient pas'));
}
assert($caughtNonOwner, 'Un non-propriétaire ne peut pas rendre le prêt');
echo "[OK] Contrôle de propriété : refus pour un utilisateur tiers (anti-IDOR)" . PHP_EOL;

// Propriétaire rend son prêt
$successReturn = $loanService->returnLoan($newLoan->getId(), 1, false);
assert($successReturn === true, 'Le propriétaire peut rendre son prêt');
assert($itemRepo->hasActiveLoan(2) === false, "L'objet 2 redevient disponible");
echo "[OK] Restitution par le propriétaire validée" . PHP_EOL;

// Clôture par un administrateur sur le prêt 3
$adminReturn = $loanService->returnLoan($loan3->getId(), 999, true);
assert($adminReturn === true, "L'administrateur peut clôturer n'importe quel prêt");
echo "[OK] Clôture par un administrateur validée" . PHP_EOL;

// 2. Tests CatalogService
echo PHP_EOL . "--- 2. Tests CatalogService ---" . PHP_EOL;

// 2.1 Refus de suppression d'un objet avec prêt actif
$caughtDeleteActive = false;
try {
    // Objet 1 a le prêt 1 actif (en retard)
    $catalogService->deleteItem(1);
} catch (RuntimeException $e) {
    $caughtDeleteActive = true;
    assert(str_contains($e->getMessage(), 'actuellement emprunté'));
}
assert($caughtDeleteActive, 'Suppression refusée si prêt actif');
echo "[OK] Refus de suppression d'un objet avec prêt actif avec message clair" . PHP_EOL;

// 2.2 Refus de suppression d'un objet avec historique de prêts
$caughtDeleteHistory = false;
try {
    // Objet 4 a le prêt 2 clôturé/rendu
    $catalogService->deleteItem(4);
} catch (RuntimeException $e) {
    $caughtDeleteHistory = true;
    assert(str_contains($e->getMessage(), 'historique d\'emprunts'));
}
assert($caughtDeleteHistory, 'Suppression refusée si historique de prêts existant');
echo "[OK] Refus de suppression d'un objet avec historique de prêts avec message clair" . PHP_EOL;

// 2.3 Suppression autorisée d'un objet sans aucun prêt
$tempItem = $catalogService->createItem('Objet Jetable Test', 'Sans prêt', 1);
$deletedItem = $catalogService->deleteItem($tempItem->getId());
assert($deletedItem === true, 'Un objet vierge de tout prêt peut être supprimé');
echo "[OK] Suppression autorisée pour un matériel sans prêt" . PHP_EOL;

// 2.4 Refus de suppression d'une catégorie non vide
$caughtDeleteCat = false;
try {
    $catalogService->deleteCategory(1);
} catch (RuntimeException $e) {
    $caughtDeleteCat = true;
    assert(str_contains($e->getMessage(), 'contient'));
}
assert($caughtDeleteCat, 'Suppression d une catégorie non vide refusée');
echo "[OK] Refus de suppression d'une catégorie contenant des objets avec message clair" . PHP_EOL;

// 2.5 Suppression autorisée d'une catégorie vide
$emptyCat = $catalogService->createCategory('Catégorie Temporaire ' . uniqid());
$deletedCat = $catalogService->deleteCategory($emptyCat->getId());
assert($deletedCat === true, 'Une catégorie vide peut être supprimée');
echo "[OK] Suppression autorisée pour une catégorie vide" . PHP_EOL;

// Nettoyage des prêts de test
$database->getConnection()->exec("DELETE FROM loans WHERE id IN ({$newLoan->getId()}, {$loan3->getId()})");

echo PHP_EOL . "=== TOUS LES TESTS DU MODULE 5 ONT RÉUSSI ===" . PHP_EOL;
