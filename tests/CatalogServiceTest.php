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
use App\Service\CatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CatalogServiceTest extends TestCase
{
    private Database $database;
    private CategoryRepository $categoryRepository;
    private ItemRepository $itemRepository;
    private LoanRepository $loanRepository;
    private UserRepository $userRepository;
    private CatalogService $catalogService;

    private User $testUser;
    private Category $testCategory;
    /** @var array<Item> */
    private array $createdItems = [];
    /** @var array<Category> */
    private array $createdCategories = [];

    protected function setUp(): void
    {
        $this->database = Database::fromEnv();
        $this->categoryRepository = new CategoryRepository($this->database);
        $this->itemRepository = new ItemRepository($this->database);
        $this->loanRepository = new LoanRepository($this->database);
        $this->userRepository = new UserRepository($this->database);
        $this->catalogService = new CatalogService($this->categoryRepository, $this->itemRepository);

        $suffix = uniqid();

        // Création d'un utilisateur et d'une catégorie de test isolés
        $this->testUser = $this->userRepository->create(new User(
            id: null,
            googleSub: "sub_cat_test_{$suffix}",
            email: "cat_test_{$suffix}@test.local",
            name: "Test User Cat {$suffix}",
            role: "member"
        ));

        $this->testCategory = $this->categoryRepository->create(new Category(
            id: null,
            name: "Catégorie CatalogTest {$suffix}"
        ));
        $this->createdCategories[] = $this->testCategory;
    }

    protected function tearDown(): void
    {
        $pdo = $this->database->getConnection();

        // 1. Suppression des prêts associés aux objets de test
        foreach ($this->createdItems as $item) {
            if ($item->getId() !== null) {
                $pdo->exec("DELETE FROM loans WHERE item_id = {$item->getId()}");
            }
        }
        if ($this->testUser->getId() !== null) {
            $pdo->exec("DELETE FROM loans WHERE user_id = {$this->testUser->getId()}");
        }

        // 2. Suppression des objets créés
        foreach ($this->createdItems as $item) {
            if ($item->getId() !== null) {
                $pdo->exec("DELETE FROM items WHERE id = {$item->getId()}");
            }
        }

        // 3. Suppression des catégories créées
        foreach ($this->createdCategories as $cat) {
            if ($cat->getId() !== null) {
                $pdo->exec("DELETE FROM categories WHERE id = {$cat->getId()}");
            }
        }

        // 4. Suppression de l'utilisateur de test
        if ($this->testUser->getId() !== null) {
            $pdo->exec("DELETE FROM users WHERE id = {$this->testUser->getId()}");
        }
    }

    /**
     * Règle métier : refus prioritaire de suppression d'un objet ayant un prêt actif en cours.
     */
    public function testDeleteItemFailsIfItemHasActiveLoan(): void
    {
        $item = $this->itemRepository->create(new Item(
            id: null,
            name: 'Objet Prêt Actif ' . uniqid(),
            description: 'Test',
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $item;

        // Création d'un prêt actif non restitué
        $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $item->getId(),
            borrowedAt: new DateTimeImmutable(),
            dueAt: (new DateTimeImmutable())->modify('+7 days'),
            returnedAt: null
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Suppression refusée : cet objet est actuellement emprunté par un membre.");

        $this->catalogService->deleteItem((int) $item->getId());
    }

    /**
     * Règle métier : refus de suppression d'un objet ayant un historique de prêts passés (traçabilité / FK RESTRICT).
     */
    public function testDeleteItemFailsIfItemHasLoanHistory(): void
    {
        $item = $this->itemRepository->create(new Item(
            id: null,
            name: 'Objet Historique ' . uniqid(),
            description: 'Test',
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $item;

        // Création d'un prêt archivé / restitué
        $this->loanRepository->create(new Loan(
            id: null,
            userId: (int) $this->testUser->getId(),
            itemId: (int) $item->getId(),
            borrowedAt: (new DateTimeImmutable())->modify('-10 days'),
            dueAt: (new DateTimeImmutable())->modify('-3 days'),
            returnedAt: (new DateTimeImmutable())->modify('-4 days')
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Suppression refusée : cet objet possède un historique d'emprunts dans le club. Pour préserver la cohérence des archives, il ne peut pas être supprimé.");

        $this->catalogService->deleteItem((int) $item->getId());
    }

    /**
     * Règle métier : refus de suppression d'une catégorie contenant des objets.
     */
    public function testDeleteCategoryFailsIfCategoryContainsItems(): void
    {
        $item = $this->itemRepository->create(new Item(
            id: null,
            name: 'Objet en Catégorie ' . uniqid(),
            description: 'Test',
            categoryId: (int) $this->testCategory->getId()
        ));
        $this->createdItems[] = $item;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf("Suppression refusée : la catégorie '%s' contient", $this->testCategory->getName()));

        $this->catalogService->deleteCategory((int) $this->testCategory->getId());
    }

    /**
     * Suppression autorisée d'une catégorie vide et d'un matériel sans aucun prêt.
     */
    public function testDeleteAllowedForEmptyCategoryAndUnloanedItem(): void
    {
        // Création d'une catégorie temporaire
        $emptyCat = $this->catalogService->createCategory('Catégorie Vide ' . uniqid());
        $this->createdCategories[] = $emptyCat;
        $this->assertNotNull($emptyCat->getId());

        // Création d'un matériel sans prêt
        $item = $this->catalogService->createItem('Objet Vierge ' . uniqid(), 'Sans prêt', (int) $emptyCat->getId());
        $this->createdItems[] = $item;
        $this->assertNotNull($item->getId());

        // 1. Suppression réussie de l'objet sans prêt
        $deletedItem = $this->catalogService->deleteItem((int) $item->getId());
        $this->assertTrue($deletedItem);

        // 2. Suppression réussie de la catégorie désormais vide
        $deletedCat = $this->catalogService->deleteCategory((int) $emptyCat->getId());
        $this->assertTrue($deletedCat);
    }
}
