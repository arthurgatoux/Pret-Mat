<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Model\Loan;
use App\Repository\ItemRepository;
use App\Repository\LoanRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LoanService
{
    public function __construct(
        private Database $database,
        private LoanRepository $loanRepository,
        private ItemRepository $itemRepository,
        private ?AuditService $auditService = null
    ) {}

    /**
     * Enregistre un emprunt de matériel avec validation stricte et transaction d'isolation.
     *
     * @param int $userId Identifiant de l'emprunteur
     * @param int $itemId Identifiant du matériel
     * @param int $days Durée en jours (entre 1 et 14)
     *
     * @throws InvalidArgumentException Si la durée n'est pas comprise entre 1 et 14 jours
     * @throws RuntimeException Si le membre a déjà 3 prêts actifs ou si le matériel n'est pas disponible
     */
    public function borrow(int $userId, int $itemId, int $days): Loan
    {
        // 1. Validation côté serveur de la durée du prêt (entre 1 et 14 jours)
        if ($days < 1 || $days > 14) {
            throw new InvalidArgumentException("La durée d'emprunt doit être comprise entre 1 et 14 jours.");
        }

        // 2. Règle métier : plafond de 3 prêts actifs par membre
        $activeLoansCount = $this->loanRepository->countActiveByUserId($userId);
        if ($activeLoansCount >= 3) {
            throw new RuntimeException("Vous avez atteint le plafond maximal de 3 prêts actifs simultanés.");
        }

        $pdo = $this->database->getConnection();
        $pdo->beginTransaction();

        try {
            // 3. Verrouillage exclusif (SELECT ... FOR UPDATE) pour garantir un seul prêt actif par objet
            $itemStmt = $pdo->prepare('SELECT id, name FROM items WHERE id = :item_id FOR UPDATE');
            $itemStmt->execute(['item_id' => $itemId]);
            $itemRow = $itemStmt->fetch();

            if (!$itemRow) {
                throw new RuntimeException("Le matériel sélectionné n'existe pas.");
            }

            // Vérification de l'absence de prêt actif sous verrou transactionnel
            $activeStmt = $pdo->prepare(
                'SELECT id FROM loans WHERE item_id = :item_id AND returned_at IS NULL LIMIT 1 FOR UPDATE'
            );
            $activeStmt->execute(['item_id' => $itemId]);

            if ($activeStmt->fetch()) {
                throw new RuntimeException("Ce matériel est actuellement indisponible (déjà emprunté).");
            }

            // 4. Calcul de l'échéance : due_at = borrowed_at + N jours
            $borrowedAt = new DateTimeImmutable();
            $dueAt = $borrowedAt->modify(sprintf('+%d days', $days));

            $loan = new Loan(
                id: null,
                userId: $userId,
                itemId: $itemId,
                borrowedAt: $borrowedAt,
                dueAt: $dueAt,
                returnedAt: null
            );

            $createdLoan = $this->loanRepository->create($loan);

            $pdo->commit();

            // 5. Journalisation d'audit MongoDB (F8 - non bloquante)
            $this->auditService?->logBorrow($userId, $itemId);

            return $createdLoan;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Clôture un prêt en enregistrant sa restitution, avec contrôle d'accès strict.
     *
     * @param int $loanId Identifiant du prêt
     * @param int $currentUserId Identifiant de l'utilisateur demandeur
     * @param bool $isAdmin Si l'utilisateur est administrateur
     *
     * @throws RuntimeException Si le prêt est introuvable, déjà restitué ou non autorisé
     */
    public function returnLoan(int $loanId, int $currentUserId, bool $isAdmin): bool
    {
        $loan = $this->loanRepository->findById($loanId);
        if ($loan === null) {
            throw new RuntimeException("Prêt introuvable.");
        }

        if ($loan->isReturned()) {
            throw new RuntimeException("Ce matériel a déjà été marqué comme rendu.");
        }

        // Règle métier : seul le propriétaire de l'emprunt ou un administrateur peut clore le prêt
        if (!$isAdmin && $loan->getUserId() !== $currentUserId) {
            throw new RuntimeException("Action refusée : vous ne pouvez pas rendre un prêt qui ne vous appartient pas.");
        }

        $success = $this->loanRepository->closeLoan($loanId);

        if ($success) {
            // Journalisation d'audit MongoDB (F8 - non bloquante)
            $this->auditService?->logReturn($currentUserId, $loan->getItemId());
        }

        return $success;
    }

    /**
     * @return array<Loan>
     */
    public function getUserLoans(int $userId): array
    {
        return $this->loanRepository->findByUserId($userId);
    }

    /**
     * @return array<Loan>
     */
    public function getAllLoans(): array
    {
        return $this->loanRepository->findAllWithDetails();
    }
}
