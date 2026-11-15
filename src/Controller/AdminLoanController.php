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

class AdminLoanController
{
    public function __construct(
        private LoanService $loanService,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private ?UserRepository $userRepository = null
    ) {}

    public function index(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        $allLoans = $this->loanService->getAllLoans();

        $activeLoansCount = count(array_filter($allLoans, fn(Loan $l) => $l->isActive()));
        $lateLoansCount = count(array_filter($allLoans, fn(Loan $l) => $l->isLate()));

        return Response::html(View::render('admin/loans', [
            'title'            => 'Suivi des prêts - Admin PretMat',
            'session'          => $this->sessionManager,
            'csrf'             => $this->csrfManager,
            'loans'            => $allLoans,
            'activeLoansCount' => $activeLoansCount,
            'lateLoansCount'   => $lateLoansCount,
        ]));
    }

    public function close(Request $request): Response
    {
        AccessControl::requireAdmin($this->sessionManager, $this->userRepository);

        if (!$request->isPost()) {
            return Response::redirect('/admin/loans');
        }

        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!$this->csrfManager->validate($csrfToken)) {
            $this->sessionManager->addFlash('error', 'Jeton CSRF invalide.');

            return Response::redirect('/admin/loans');
        }

        $loanId = (int) $request->getPost('loan_id', 0);
        $userId = (int) $this->sessionManager->getUserId();

        try {
            $this->loanService->returnLoan($loanId, $userId, true);
            $this->sessionManager->addFlash('success', 'Le prêt a été clôturé avec succès par l administration.');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return Response::redirect('/admin/loans');
    }
}
