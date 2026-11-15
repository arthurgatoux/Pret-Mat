<?php
/** @var array<\App\Model\Loan> $loans */
/** @var int $activeLoansCount */
/** @var int $lateLoansCount */
/** @var \App\Security\CsrfManager $csrf */
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Suivi global des prêts</h1>
    <p class="text-slate-600 mt-1">Supervisez l'intégralité des emprunts du club, identifiez immédiatement les retards et clôturez les prêts.</p>
</div>

<!-- Cartes de synthèse -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total des prêts enregistrés</p>
        <p class="text-3xl font-extrabold text-slate-900 mt-2"><?= count($loans) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Prêts actifs en cours</p>
        <p class="text-3xl font-extrabold text-indigo-600 mt-2"><?= $activeLoansCount ?></p>
    </div>
    <div class="bg-white rounded-xl border <?= $lateLoansCount > 0 ? 'border-rose-300 bg-rose-50/30 ring-2 ring-rose-200' : 'border-slate-200' ?> p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider <?= $lateLoansCount > 0 ? 'text-rose-700 font-extrabold' : 'text-slate-500' ?>">
            Prêts en retard
        </p>
        <p class="text-3xl font-extrabold <?= $lateLoansCount > 0 ? 'text-rose-600' : 'text-slate-900' ?> mt-2">
            <?= $lateLoansCount ?>
        </p>
    </div>
</div>

<!-- Table de tous les prêts -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Journal des emprunts</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">ID</th>
                    <th class="py-3 px-4">Statut</th>
                    <th class="py-3 px-4">Matériel</th>
                    <th class="py-3 px-4">Emprunteur</th>
                    <th class="py-3 px-4">Date emprunt</th>
                    <th class="py-3 px-4">Échéance</th>
                    <th class="py-3 px-4">Date retour</th>
                    <th class="py-3 px-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($loans)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400 italic">Aucun prêt enregistré.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        $isActive = $loan->isActive();
                        $isLate = $loan->isLate();
                        ?>
                        <tr class="hover:bg-slate-50/50 transition <?= $isLate ? 'bg-rose-50/20' : '' ?>">
                            <td class="py-3 px-4 font-mono text-xs text-slate-400">#<?= (int) $loan->getId() ?></td>
                            <td class="py-3 px-4">
                                <?php if ($isLate): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                        En retard
                                    </span>
                                <?php elseif ($isActive): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        En cours
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                                        Rendu
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">
                                <?= htmlspecialchars((string) $loan->getItemName(), ENT_QUOTES, 'UTF-8') ?>
                                <span class="block text-xs font-normal text-slate-500">
                                    <?= htmlspecialchars((string) $loan->getCategoryName(), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-slate-800 block">
                                    <?= htmlspecialchars((string) $loan->getUserName(), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="text-xs text-slate-500">
                                    <?= htmlspecialchars((string) $loan->getUserEmail(), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 text-xs">
                                <?= $loan->getBorrowedAt()->format('d/m/Y H:i') ?>
                            </td>
                            <td class="py-3 px-4 text-xs font-semibold <?= $isLate ? 'text-rose-600 font-bold' : 'text-slate-800' ?>">
                                <?= $loan->getDueAt()->format('d/m/Y') ?>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600">
                                <?= $loan->getReturnedAt()?->format('d/m/Y H:i') ?? '-' ?>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <?php if ($isActive): ?>
                                    <form action="/admin/loans/close" method="POST" class="inline" onsubmit="return confirm('Confirmez-vous la clôture forcée de cet emprunt ?');">
                                        <?= $csrf->renderInput() ?>
                                        <input type="hidden" name="loan_id" value="<?= (int) $loan->getId() ?>">
                                        <button type="submit" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded transition">
                                            Clôturer le prêt
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
