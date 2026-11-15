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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    private Database $database;
    private CategoryRepository $categoryRepository;
    private ItemRepository $itemRepository;
    private LoanRepository $loanRepository;
    private UserRepository $userRepository;

    private User $testUser;
    private Category $testCategory;
    private Item $availableItem;
    private Item $borrowedItem;
    /** @var array<Loan> */
    private array $createdLoans = [];
    /** @var array<Item> */
    private array $createdItems = [];
    /** @var array<Category> */
    private array $createdCategories = [];
    /** @var array<User> */
    private array $createdUsers = [];

    protected function setUp(): void
    {
        $this->database = Database::fromEnv();
        $this->categoryRepository = new CategoryRepository($this->database);
        $this->itemRepository = new ItemRepository($this->database);
        $this->loanRepository = new LoanRepository($this->database);
        $this->userRepository = new UserRepository($this->database);

        $suffix = uniqid();

        // 1. Création d'un utilisateur de test dédié
        $this->testUser = $this->userRepository->create(new User(
            id: null,
            googleSub: "sub_repo_{$suffix}",
            email: "repo_{$suffix}@test.local",
            name: "User Repo {$suffix}",
            role: "member"
        ));
        $this->createdUsers[] = $this->testUser;

        // 2. Création d'une catégorie dédiée
        $this->testCategory = $this->categoryRepository->create(new Category(
            id: null,
            name: "Catégorie Repo {$suffix}"
        ));
        $this->createdCategories[] = $this->testCategory;

        // 3. Création de deux objets dédiés
        $this->availableItem = $this->itemRepository->create(new Item(
            id: null,
            name: "Objet Dispo {$suffix}",
            description: "Objet disponible pour test repository",
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $this->availableItem;

        $this->borrowedItem = $this->itemRepository->create(new Item(
            id: null,
            name: "Objet Emprunté {$suffix}",
            description: "Objet emprunté pour test repository",
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $this->borrowedItem;

        // 4. Prêt actif sur $this->borrowedItem
        $loan = $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $this->borrowedItem->getId(),
            borrowedAt: new DateTimeImmutable('-2 days'),
            dueAt: new DateTimeImmutable('+5 days'),
            returnedAt: null
        ));
        $this->createdLoans[] = $loan;
    }

    protected function tearDown(): void
    {
        $pdo = $this->database->getConnection();

        // Nettoyage strict et ordonné des données créées
        foreach ($this->createdLoans as $loan) {
            if ($loan->getId() !== null) {
                $pdo->exec("DELETE FROM loans WHERE id = {$loan->getId()}");
            }
        }

        foreach ($this->createdItems as $item) {
            if ($item->getId() !== null) {
                $pdo->exec("DELETE FROM loans WHERE item_id = {$item->getId()}");
                $pdo->exec("DELETE FROM items WHERE id = {$item->getId()}");
            }
        }

        foreach ($this->createdCategories as $category) {
            if ($category->getId() !== null) {
                $pdo->exec("DELETE FROM categories WHERE id = {$category->getId()}");
            }
        }

        foreach ($this->createdUsers as $user) {
            if ($user->getId() !== null) {
                $pdo->exec("DELETE FROM users WHERE id = {$user->getId()}");
            }
        }
    }

    /**
     * Test Repository : la liste catalogue retourne les matériels avec la jointure catégorie et la disponibilité calculée.
     */
    public function testFindCatalogReturnsItemsWithCategoryJoinAndCalculatedAvailability(): void
    {
        // 1. Catalogue global sans filtre
        $catalog = $this->itemRepository->findCatalog(null);
        $this->assertNotEmpty($catalog);

        // Retrouver nos deux objets de test dans le catalogue global
        $foundAvailable = null;
        $foundBorrowed = null;

        foreach ($catalog as $item) {
            $this->assertInstanceOf(Item::class, $item);
            if ($item->getId() === $this->availableItem->getId()) {
                $foundAvailable = $item;
            } elseif ($item->getId() === $this->borrowedItem->getId()) {
                $foundBorrowed = $item;
            }
        }

        $this->assertNotNull($foundAvailable, "L'objet disponible doit être présent dans le catalogue");
        $this->assertSame($this->testCategory->getName(), $foundAvailable->getCategoryName());
        $this->assertTrue($foundAvailable->isAvailable(), "L'objet sans prêt actif doit être disponible (is_available = true)");

        $this->assertNotNull($foundBorrowed, "L'objet emprunté doit être présent dans le catalogue");
        $this->assertSame($this->testCategory->getName(), $foundBorrowed->getCategoryName());
        $this->assertFalse($foundBorrowed->isAvailable(), "L'objet ayant un prêt actif ne doit pas être disponible (is_available = false)");

        // 2. Filtre par catégorie dédiée
        $catItems = $this->itemRepository->findCatalog((int) $this->testCategory->getId());
        $this->assertCount(2, $catItems, "La catégorie de test doit contenir exactement nos 2 matériels");

        foreach ($catItems as $item) {
            $this->assertSame((int) $this->testCategory->getId(), $item->getCategoryId());
            $this->assertSame($this->testCategory->getName(), $item->getCategoryName());
        }
    }

    /**
     * Test Repository : calcul dynamique du statut "en retard" dans la liste globale des prêts.
     */
    public function testFindAllWithDetailsCalculatesLateStatusDynamically(): void
    {
        $suffix = uniqid();

        // Création d'objets spécifiques pour tester les 3 états de prêt :
        // - un prêt en retard (due_at dans le passé, returned_at = null)
        // - un prêt rendu (returned_at != null, due_at dans le passé)
        // - un prêt en cours dans les temps (due_at dans le futur, returned_at = null)
        $itemLate = $this->itemRepository->create(new Item(
            id: null,
            name: "Objet Retard {$suffix}",
            description: null,
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $itemLate;

        $itemReturned = $this->itemRepository->create(new Item(
            id: null,
            name: "Objet Rendu {$suffix}",
            description: null,
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $itemReturned;

        $itemOnTime = $this->itemRepository->create(new Item(
            id: null,
            name: "Objet Dans Les Temps {$suffix}",
            description: null,
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $itemOnTime;

        // 1. Prêt en retard : emprunté il y a 10 jours, dû il y a 3 jours, non rendu
        $loanLate = $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $itemLate->getId(),
            borrowedAt: new DateTimeImmutable('-10 days'),
            dueAt: new DateTimeImmutable('-3 days'),
            returnedAt: null
        ));
        $this->createdLoans[] = $loanLate;

        // 2. Prêt rendu : emprunté il y a 10 jours, dû il y a 3 jours, rendu il y a 4 jours
        $loanReturned = $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $itemReturned->getId(),
            borrowedAt: new DateTimeImmutable('-10 days'),
            dueAt: new DateTimeImmutable('-3 days'),
            returnedAt: new DateTimeImmutable('-4 days')
        ));
        $this->createdLoans[] = $loanReturned;

        // 3. Prêt en cours dans les temps : emprunté il y a 2 jours, dû dans 5 jours
        $loanOnTime = $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $itemOnTime->getId(),
            borrowedAt: new DateTimeImmutable('-2 days'),
            dueAt: new DateTimeImmutable('+5 days'),
            returnedAt: null
        ));
        $this->createdLoans[] = $loanOnTime;

        // Récupération de tous les prêts avec calcul dynamique du retard
        $allLoans = $this->loanRepository->findAllWithDetails();

        $foundLate = null;
        $foundReturned = null;
        $foundOnTime = null;

        foreach ($allLoans as $loan) {
            if ($loan->getId() === $loanLate->getId()) {
                $foundLate = $loan;
            } elseif ($loan->getId() === $loanReturned->getId()) {
                $foundReturned = $loan;
            } elseif ($loan->getId() === $loanOnTime->getId()) {
                $foundOnTime = $loan;
            }
        }

        $this->assertNotNull($foundLate, "Le prêt en retard doit être retrouvé dans la liste");
        $this->assertNotNull($foundReturned, "Le prêt rendu doit être retrouvé dans la liste");
        $this->assertNotNull($foundOnTime, "Le prêt en cours doit être retrouvé dans la liste");

        // Prêt non rendu avec due_at dépassé => isActive() true ET isLate() true
        $this->assertTrue($foundLate->isActive());
        $this->assertTrue($foundLate->isLate(), "Un prêt actif dont l'échéance est passée doit être en retard");

        // Prêt restitué => isReturned() true ET isLate() false (même si due_at était dans le passé)
        $this->assertTrue($foundReturned->isReturned());
        $this->assertFalse($foundReturned->isLate(), "Un prêt restitué ne doit jamais être considéré en retard");

        // Prêt actif dans les temps => isActive() true ET isLate() false
        $this->assertTrue($foundOnTime->isActive());
        $this->assertFalse($foundOnTime->isLate(), "Un prêt actif dont l'échéance est future ne doit pas être en retard");
    }
}
