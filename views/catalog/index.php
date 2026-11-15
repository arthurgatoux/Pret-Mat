<?php
/** @var array<\App\Model\Category> $categories */
/** @var array<\App\Model\Item> $items */
/** @var int|null $selectedCategoryId */
/** @var \App\Security\SessionManager $session */
/** @var \App\Security\CsrfManager $csrf */
$isLoggedIn = $session->isLoggedIn();
?>

<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Catalogue du matériel</h1>
        <p class="text-slate-600 mt-1">Consultez l'inventaire et empruntez le matériel disponible pour vos projets (1 à 14 jours).</p>
    </div>

    <!-- Filtre par catégorie -->
    <div class="flex items-center gap-2">
        <label for="categoryFilter" class="text-sm font-semibold text-slate-700">Catégorie :</label>
        <select id="categoryFilter" onchange="window.location.href=this.value" class="bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="/catalogue" <?= $selectedCategoryId === null ? 'selected' : '' ?>>Toutes les catégories</option>
            <?php foreach ($categories as $category): ?>
                <option value="/catalogue?category=<?= (int) $category->getId() ?>" <?= $selectedCategoryId === $category->getId() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($category->getName(), ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Grille des matériels -->
<?php if (empty($items)): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-500 shadow-sm">
        <p class="text-lg font-medium">Aucun matériel disponible dans cette sélection.</p>
        <a href="/catalogue" class="mt-4 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-800">
            &larr; Réinitialiser les filtres
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($items as $item): ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                            <?= htmlspecialchars((string) $item->getCategoryName(), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($item->isAvailable()): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Disponible
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Emprunté
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">
                        <?= htmlspecialchars($item->getName(), ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <p class="text-sm text-slate-600 mt-2 line-clamp-3">
                        <?= htmlspecialchars($item->getDescription() ?? 'Aucune description disponible.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <div class="p-6 pt-0 border-t border-slate-100 mt-4 bg-slate-50/50">
                    <?php if ($item->isAvailable()): ?>
                        <?php if ($isLoggedIn): ?>
                            <form action="/emprunt" method="POST" class="mt-4 flex flex-col gap-3">
                                <?= $csrf->renderInput() ?>
                                <input type="hidden" name="item_id" value="<?= (int) $item->getId() ?>">
                                <div class="flex items-center gap-2">
                                    <label for="days_<?= (int) $item->getId() ?>" class="text-xs font-semibold text-slate-700">Durée (jours) :</label>
                                    <select id="days_<?= (int) $item->getId() ?>" name="days" class="w-full bg-white border border-slate-300 rounded px-2 py-1.5 text-sm font-medium text-slate-700">
                                        <?php for ($d = 1; $d <= 14; $d++): ?>
                                            <option value="<?= $d ?>" <?= $d === 7 ? 'selected' : '' ?>>
                                                <?= $d ?> jour<?= $d > 1 ? 's' : '' ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow-sm transition">
                                    Emprunter ce matériel
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="mt-4 text-center">
                                <a href="/auth/google" class="inline-block w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold py-2 px-4 rounded-lg text-xs transition">
                                    Connectez-vous pour emprunter
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mt-4 text-center">
                            <span class="inline-block w-full py-2 px-4 rounded-lg text-xs font-semibold bg-slate-100 text-slate-400 cursor-not-allowed">
                                Matériel actuellement indisponible
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
