<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\Loan;
use App\Repository\UserRepository;
use App\Security\AccessControl;
use App\Security\CsrfManager;
use App\Security\SessionManager;
use App\Service\LoanService;
use Throwable;

class MyLoansController
{
    public function __construct(
        private LoanService $loanService,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private ?UserRepository $userRepository = null
    ) {}

    /**
     * Page Mes prêts : prêts en cours (avec action rendre) et historique des prêts restitués.
     */
    public function index(Request $request): Response
    {
        AccessControl::requireAuth($this->sessionManager, $this->userRepository);

        $userId = (int) $this->sessionManager->getUserId();
        $allUserLoans = $this->loanService->getUserLoans($userId);

        $activeLoans = array_filter($allUserLoans, fn(Loan $l) => $l->isActive());
        $historyLoans = array_filter($allUserLoans, fn(Loan $l) => $l->isReturned());

        return Response::html(View::render('my_loans/index', [
            'title'        => 'Mes prêts - PretMat',
            'session'      => $this->sessionManager,
            'csrf'         => $this->csrfManager,
            'activeLoans'  => $activeLoans,
            'historyLoans' => $historyLoans,
        ]));
    }

    /**
     * Restitution d'un matériel emprunté par le membre (POST).
     */
    public function returnLoan(Request $request): Response
    {
        AccessControl::requireAuth($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/mes-prets');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Session ou jeton de sécurité invalide.');

            return Response::redirect('/mes-prets');
        }

        $loanId = (int) $request->getPost('loan_id', 0);
        $userId = (int) $this->sessionManager->getUserId();
        $isAdmin = $this->sessionManager->isAdmin();

        try {
            $this->loanService->returnLoan($loanId, $userId, $isAdmin);
            $this->sessionManager->addFlash('success', 'Le matériel a bien été enregistré comme rendu. Merci !');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/mes-prets');
    }
}
