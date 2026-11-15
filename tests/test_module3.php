<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Model\Category;
use App\Model\Item;
use App\Model\Loan;
use App\Model\User;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;

Env::load(dirname(__DIR__));

$database = Database::fromEnv();

echo "=== TEST MODULE 3 : MODEL & REPOSITORY ===" . PHP_EOL;

// 1. Tests UserRepository
echo PHP_EOL . "--- 1. Tests UserRepository ---" . PHP_EOL;
$userRepo = new UserRepository($database);

$admin = $userRepo->findByGoogleSub('fake_sub_admin_001');
assert($admin instanceof User, 'Admin doit être trouvé par son google_sub');
assert($admin->isAdmin(), 'Le rôle doit être admin');
echo "[OK] findByGoogleSub: trouvé {$admin->getName()} (rôle: {$admin->getRole()})" . PHP_EOL;

$member = $userRepo->findByEmail('membre@pretmat.local');
assert($member instanceof User, 'Membre doit être trouvé par email');
assert($member->isMember(), 'Le rôle doit être member');
echo "[OK] findByEmail: trouvé {$member->getName()} (id: {$member->getId()})" . PHP_EOL;

$newUser = new User(
    id: null,
    googleSub: 'test_sub_' . uniqid(),
    email: 'new_' . uniqid() . '@example.com',
    name: 'Test Nouvel Utilisateur',
    role: 'member'
);
$createdUser = $userRepo->create($newUser);
assert($createdUser->getId() !== null, 'Le nouvel utilisateur doit avoir un id');
echo "[OK] create: utilisateur créé avec l'id {$createdUser->getId()}" . PHP_EOL;

$userRepo->updateRole($createdUser->getId(), 'admin');
$updatedUser = $userRepo->findById($createdUser->getId());
assert($updatedUser->isAdmin(), 'Le rôle doit avoir été mis à jour à admin');
echo "[OK] updateRole: rôle mis à jour à {$updatedUser->getRole()}" . PHP_EOL;

// 2. Tests CategoryRepository
echo PHP_EOL . "--- 2. Tests CategoryRepository ---" . PHP_EOL;
$catRepo = new CategoryRepository($database);

$categories = $catRepo->findAll();
assert(count($categories) >= 3, 'Au moins 3 catégories existantes');
echo "[OK] findAll: " . count($categories) . " catégories récupérées" . PHP_EOL;

$categoriesWithCount = $catRepo->findAllWithItemCount();
foreach ($categoriesWithCount as $cat) {
    echo "  - {$cat->getName()} : {$cat->getItemCount()} objet(s)" . PHP_EOL;
}
assert($categoriesWithCount[0]->getItemCount() > 0, 'Le comptage doit être supérieur à 0');
echo "[OK] findAllWithItemCount validé" . PHP_EOL;

$cat1Count = $catRepo->countItems(1);
assert($cat1Count === 3, 'La catégorie 1 doit avoir exactement 3 objets');
echo "[OK] countItems(1) = {$cat1Count}" . PHP_EOL;

$newCat = $catRepo->create(new Category(null, 'Test Catégorie ' . uniqid()));
assert($newCat->getId() !== null, 'Nouvelle catégorie créée');
$newCat->setName($newCat->getName() . ' Renommée');
$catRepo->update($newCat);
$catRepo->delete($newCat->getId());
echo "[OK] CRUD Category (create, update, delete) validé" . PHP_EOL;

// 3. Tests ItemRepository
echo PHP_EOL . "--- 3. Tests ItemRepository ---" . PHP_EOL;
$itemRepo = new ItemRepository($database);

$catalogAll = $itemRepo->findCatalog(null);
assert(count($catalogAll) >= 8, 'Catalogue complet avec au moins 8 objets');
echo "[OK] findCatalog(null): " . count($catalogAll) . " objets retournés" . PHP_EOL;

$catalogCat1 = $itemRepo->findCatalog(1);
assert(count($catalogCat1) === 3, 'La catégorie 1 doit contenir 3 objets');
echo "[OK] findCatalog(1) [Filtre catégorie]: " . count($catalogCat1) . " objets retournés" . PHP_EOL;

// Vérification de la disponibilité : objet 1 a un prêt en cours (donc indisponible), objet 2 n'en a pas (disponible)
$item1 = $itemRepo->findById(1);
$item2 = $itemRepo->findById(2);
assert(!$item1->isAvailable(), "L'objet 1 doit être indisponible (prêt en cours)");
assert($item2->isAvailable(), "L'objet 2 doit être disponible");
echo "[OK] Disponibilité calculée: Objet 1 disponible = " . ($item1->isAvailable() ? 'Oui' : 'Non') . ", Objet 2 disponible = " . ($item2->isAvailable() ? 'Oui' : 'Non') . PHP_EOL;

// Vérification existence prêt (actif ou historique)
assert($itemRepo->hasLoans(1) === true, "L'objet 1 a un historique de prêt");
assert($itemRepo->hasLoans(8) === false, "L'objet 8 n'a aucun prêt");
assert($itemRepo->hasActiveLoan(1) === true, "L'objet 1 a un prêt actif");
assert($itemRepo->hasActiveLoan(4) === false, "L'objet 4 a un prêt rendu (pas actif)");
echo "[OK] hasLoans et hasActiveLoan validés avec succès" . PHP_EOL;

// 4. Tests LoanRepository
echo PHP_EOL . "--- 4. Tests LoanRepository ---" . PHP_EOL;
$loanRepo = new LoanRepository($database);

$allLoans = $loanRepo->findAllWithDetails();
assert(count($allLoans) >= 3, 'Au moins 3 prêts récupérés');
echo "[OK] findAllWithDetails: " . count($allLoans) . " prêts trouvés" . PHP_EOL;

$lateLoans = array_filter($allLoans, fn(Loan $l) => $l->isLate());
assert(count($lateLoans) >= 1, 'Au moins 1 prêt en retard détecté');
echo "[OK] Détection prêt en retard: " . count($lateLoans) . " prêt(s) en retard" . PHP_EOL;

$user2Loans = $loanRepo->findByUserId(2);
assert(count($user2Loans) === 2, "L'utilisateur 2 doit avoir 2 prêts dans son historique");
echo "[OK] findByUserId(2): " . count($user2Loans) . " prêts trouvés" . PHP_EOL;

$user2ActiveCount = $loanRepo->countActiveByUserId(2);
assert($user2ActiveCount === 1, "L'utilisateur 2 doit avoir 1 seul prêt actif");
echo "[OK] countActiveByUserId(2): {$user2ActiveCount} prêt actif" . PHP_EOL;

// Test création et clôture d'un prêt
$newLoan = new Loan(
    id: null,
    userId: $createdUser->getId(),
    itemId: 8,
    borrowedAt: new DateTimeImmutable(),
    dueAt: (new DateTimeImmutable())->modify('+7 days'),
    returnedAt: null
);
$createdLoan = $loanRepo->create($newLoan);
assert($createdLoan->getId() !== null, 'Le prêt doit être inséré avec un id');
assert($itemRepo->hasActiveLoan(8) === true, "L'objet 8 doit maintenant avoir un prêt actif");
echo "[OK] create Loan: prêt créé avec id {$createdLoan->getId()}" . PHP_EOL;

$closed = $loanRepo->closeLoan($createdLoan->getId());
assert($closed === true, 'Le prêt doit être clôturé avec succès');
assert($itemRepo->hasActiveLoan(8) === false, "L'objet 8 n'a plus de prêt actif après clôture");
echo "[OK] closeLoan: prêt clôturé, objet redevenu disponible" . PHP_EOL;

// Nettoyage de l'utilisateur de test
$database->getConnection()->exec("DELETE FROM loans WHERE user_id = {$createdUser->getId()}");
$database->getConnection()->exec("DELETE FROM users WHERE id = {$createdUser->getId()}");

echo PHP_EOL . "=== TOUS LES TESTS DU MODULE 3 ONT RÉUSSI ===" . PHP_EOL;
