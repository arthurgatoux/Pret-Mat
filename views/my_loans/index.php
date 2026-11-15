<?php
/** @var array<\App\Model\Loan> $activeLoans */
/** @var array<\App\Model\Loan> $historyLoans */
/** @var \App\Security\CsrfManager $csrf */
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Mes prêts</h1>
    <p class="text-slate-600 mt-1">Suivez vos emprunts en cours et consultez l'historique complet de votre matériel rendu.</p>
</div>

<!-- Section 1 : Prêts en cours -->
<section class="mb-12">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            Prêts en cours
            <span class="bg-indigo-100 text-indigo-700 text-xs px-2.5 py-0.5 rounded-full font-bold">
                <?= count($activeLoans) ?> / 3 max
            </span>
        </h2>
    </div>

    <?php if (empty($activeLoans)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500 shadow-sm">
            <p class="font-medium">Vous n'avez aucun prêt en cours actuellement.</p>
            <a href="/catalogue" class="mt-3 inline-block font-semibold text-sm text-indigo-600 hover:text-indigo-800">
                Parcourir le catalogue &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($activeLoans as $loan): ?>
                <?php $isLate = $loan->isLate(); ?>
                <div class="bg-white rounded-xl border <?= $isLate ? 'border-rose-300 ring-2 ring-rose-200' : 'border-slate-200' ?> shadow-sm p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                <?= htmlspecialchars((string) $loan->getCategoryName(), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($isLate): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 border border-rose-200 animate-pulse">
                                    En retard
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                    Dans les temps
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900">
                            <?= htmlspecialchars((string) $loan->getItemName(), ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <dl class="mt-4 text-xs space-y-1.5 text-slate-600">
                            <div class="flex justify-between">
                                <dt class="font-medium">Emprunté le :</dt>
                                <dd class="font-semibold text-slate-800"><?= $loan->getBorrowedAt()->format('d/m/Y à H:i') ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="font-medium">À rendre avant le :</dt>
                                <dd class="font-bold <?= $isLate ? 'text-rose-600' : 'text-slate-800' ?>">
                                    <?= $loan->getDueAt()->format('d/m/Y') ?>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <form action="/mes-prets/rendre" method="POST" class="mt-6 border-t border-slate-100 pt-4" onsubmit="return confirm('Confirmez-vous la restitution de ce matériel ?');">
                        <?= $csrf->renderInput() ?>
                        <input type="hidden" name="loan_id" value="<?= (int) $loan->getId() ?>">
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow-sm transition">
                            Rendre cet objet
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Section 2 : Historique des prêts -->
<section>
    <h2 class="text-xl font-bold text-slate-900 mb-4">Historique des prêts restitués</h2>

    <?php if (empty($historyLoans)): ?>
        <p class="text-sm text-slate-500 italic">Aucun historique disponible pour le moment.</p>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-700">
                    <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Matériel</th>
                            <th class="py-3 px-4">Catégorie</th>
                            <th class="py-3 px-4">Date emprunt</th>
                            <th class="py-3 px-4">Date restitution</th>
                            <th class="py-3 px-4">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($historyLoans as $loan): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    <?= htmlspecialchars((string) $loan->getItemName(), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?= htmlspecialchars((string) $loan->getCategoryName(), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?= $loan->getBorrowedAt()->format('d/m/Y') ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-medium">
                                    <?= $loan->getReturnedAt()?->format('d/m/Y à H:i') ?? '-' ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                        Rendu
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
