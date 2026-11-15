<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Model\Category;
use App\Model\Item;
use App\Model\Loan;
use App\Model\User;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Service\LoanService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LoanServiceTest extends TestCase
{
    private Database $database;
    private LoanRepository $loanRepository;
    private ItemRepository $itemRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private LoanService $loanService;

    private User $userA;
    private User $userB;
    private User $adminUser;
    private Category $testCategory;
    /** @var array<int, Item> */
    private array $testItems = [];

    protected function setUp(): void
    {
        $this->database = Database::fromEnv();
        $this->loanRepository = new LoanRepository($this->database);
        $this->itemRepository = new ItemRepository($this->database);
        $this->categoryRepository = new CategoryRepository($this->database);
        $this->userRepository = new UserRepository($this->database);
        $this->loanService = new LoanService($this->database, $this->loanRepository, $this->itemRepository);

        $suffix = uniqid();

        // 1. Création de comptes utilisateurs dédiés au test (isolés du reste de la base)
        $this->userA = $this->userRepository->create(new User(
            id: null,
            googleSub: "sub_user_a_{$suffix}",
            email: "user_a_{$suffix}@test.local",
            name: "Membre A {$suffix}",
            role: "member"
        ));

        $this->userB = $this->userRepository->create(new User(
            id: null,
            googleSub: "sub_user_b_{$suffix}",
            email: "user_b_{$suffix}@test.local",
            name: "Membre B {$suffix}",
            role: "member"
        ));

        $this->adminUser = $this->userRepository->create(new User(
            id: null,
            googleSub: "sub_admin_{$suffix}",
            email: "admin_{$suffix}@test.local",
            name: "Admin {$suffix}",
            role: "admin"
        ));

        // 2. Création d'une catégorie dédiée
        $this->testCategory = $this->categoryRepository->create(new Category(
            id: null,
            name: "Catégorie Test {$suffix}"
        ));

        // 3. Création de 5 matériels isolés pour les tests de quota et de disponibilité
        for ($i = 1; $i <= 5; $i++) {
            $this->testItems[$i] = $this->itemRepository->create(new Item(
                id: null,
                name: "Objet Test {$i} {$suffix}",
                description: "Description test {$i}",
                categoryId: (int) $this->testCategory->getId()
            ));
        }
    }

    protected function tearDown(): void
    {
        $pdo = $this->database->getConnection();

        // Nettoyage strict des prêts, objets, catégories et utilisateurs créés pour ce test
        $userIds = [(int) $this->userA->getId(), (int) $this->userB->getId(), (int) $this->adminUser->getId()];
        $userIdsList = implode(',', $userIds);
        $pdo->exec("DELETE FROM loans WHERE user_id IN ($userIdsList)");

        $itemIds = array_map(fn(Item $item) => (int) $item->getId(), $this->testItems);
        if (!empty($itemIds)) {
            $itemIdsList = implode(',', $itemIds);
            $pdo->exec("DELETE FROM loans WHERE item_id IN ($itemIdsList)");
            $pdo->exec("DELETE FROM items WHERE id IN ($itemIdsList)");
        }

        if ($this->testCategory->getId() !== null) {
            $pdo->exec("DELETE FROM categories WHERE id = {$this->testCategory->getId()}");
        }

        $pdo->exec("DELETE FROM users WHERE id IN ($userIdsList)");
    }

    /**
     * Règle métier : la durée d'emprunt doit être comprise entre 1 et 14 jours (rejet si 0 jour).
     */
    public function testBorrowFailsWhenDurationIsOutOfBounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La durée d'emprunt doit être comprise entre 1 et 14 jours.");

        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 0);
    }

    /**
     * Règle métier : rejet si la durée dépasse 14 jours.
     */
    public function testBorrowFailsWhenDurationExceedsFourteenDays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La durée d'emprunt doit être comprise entre 1 et 14 jours.");

        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 15);
    }

    /**
     * Règle métier : un objet ne peut avoir qu'un seul prêt actif à la fois (SELECT ... FOR UPDATE).
     */
    public function testBorrowFailsWhenItemAlreadyHasActiveLoan(): void
    {
        // 1. Le membre A emprunte l'objet 1
        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 7);

        // 2. Le membre B tente d'emprunter le même objet -> refus immédiat
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Ce matériel est actuellement indisponible (déjà emprunté).");

        $this->loanService->borrow((int) $this->userB->getId(), (int) $this->testItems[1]->getId(), 3);
    }

    /**
     * Règle métier : un membre ne peut avoir plus de 3 prêts actifs simultanés.
     */
    public function testBorrowFailsWhenUserReachesLimitOfThreeLoans(): void
    {
        // Le membre A emprunte 3 objets distincts
        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 5);
        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[2]->getId(), 5);
        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[3]->getId(), 5);

        $this->assertSame(3, $this->loanRepository->countActiveByUserId((int) $this->userA->getId()));

        // Tentative d'un 4ème prêt -> refus strict
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Vous avez atteint le plafond maximal de 3 prêts actifs simultanés.");

        $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[4]->getId(), 2);
    }

    /**
     * Règle métier & sécurité : un utilisateur tiers ne peut pas clôturer le prêt d'un autre membre (anti-IDOR).
     */
    public function testReturnLoanFailsForNonOwnerAndNonAdmin(): void
    {
        // Le membre A emprunte l'objet 1
        $loan = $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Action refusée : vous ne pouvez pas rendre un prêt qui ne vous appartient pas.");

        // Le membre B (non admin) tente de restituer le prêt du membre A
        $this->loanService->returnLoan((int) $loan->getId(), (int) $this->userB->getId(), false);
    }

    /**
     * Règle métier : le propriétaire de l'emprunt peut clôturer son propre prêt.
     */
    public function testReturnLoanSucceedsForOwner(): void
    {
        $loan = $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 5);

        $success = $this->loanService->returnLoan((int) $loan->getId(), (int) $this->userA->getId(), false);
        $this->assertTrue($success);

        $refreshedLoan = $this->loanRepository->findById((int) $loan->getId());
        $this->assertNotNull($refreshedLoan);
        $this->assertTrue($refreshedLoan->isReturned());
    }

    /**
     * Règle métier : un administrateur peut clôturer n'importe quel prêt actif.
     */
    public function testReturnLoanSucceedsForAdmin(): void
    {
        // Prêt appartenant au membre A
        $loan = $this->loanService->borrow((int) $this->userA->getId(), (int) $this->testItems[1]->getId(), 5);

        // L'administrateur clôture le prêt
        $success = $this->loanService->returnLoan((int) $loan->getId(), (int) $this->adminUser->getId(), true);
        $this->assertTrue($success);

        $refreshedLoan = $this->loanRepository->findById((int) $loan->getId());
        $this->assertNotNull($refreshedLoan);
        $this->assertTrue($refreshedLoan->isReturned());
    }
}
