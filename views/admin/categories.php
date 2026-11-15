<?php
/** @var array<\App\Model\Category> $categories */
/** @var \App\Security\CsrfManager $csrf */
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Administration des catégories</h1>
    <p class="text-slate-600 mt-1">Créez et organisez les catégories de matériel du club.</p>
</div>

<!-- Formulaire d'ajout -->
<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm mb-10 max-w-xl">
    <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Créer une nouvelle catégorie
    </h2>
    <form action="/admin/categories/create" method="POST" class="flex items-end gap-3">
        <?= $csrf->renderInput() ?>
        <div class="flex-grow">
            <label for="new_cat_name" class="block text-xs font-semibold text-slate-700 mb-1">Nom de la catégorie *</label>
            <input type="text" id="new_cat_name" name="name" required placeholder="Ex : Informatique & Réseau" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-5 rounded-lg text-sm shadow-sm transition whitespace-nowrap">
            Ajouter
        </button>
    </form>
</div>

<!-- Liste des catégories -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm max-w-3xl">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Catégories enregistrées (<?= count($categories) ?>)</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">ID</th>
                    <th class="py-3 px-4">Nom de la catégorie</th>
                    <th class="py-3 px-4">Objets associés</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($categories as $cat): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono text-xs text-slate-400">#<?= (int) $cat->getId() ?></td>
                        <td class="py-3 px-4 font-semibold text-slate-900">
                            <?= htmlspecialchars($cat->getName(), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cat->getItemCount() > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-500' ?>">
                                <?= $cat->getItemCount() ?> objet(s)
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" onclick="document.getElementById('edit_cat_<?= (int) $cat->getId() ?>').classList.toggle('hidden');" class="text-xs font-semibold text-slate-600 hover:text-indigo-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded transition">
                                    Modifier
                                </button>
                                <form action="/admin/categories/delete" method="POST" class="inline" onsubmit="return confirm('Confirmez-vous la suppression de cette catégorie ?');">
                                    <?= $csrf->renderInput() ?>
                                    <input type="hidden" name="id" value="<?= (int) $cat->getId() ?>">
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded transition">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <!-- Ligne d'édition masquée -->
                    <tr id="edit_cat_<?= (int) $cat->getId() ?>" class="hidden bg-indigo-50/40">
                        <td colspan="4" class="p-4 border-t border-indigo-100">
                            <form action="/admin/categories/update" method="POST" class="flex items-end gap-3 max-w-md">
                                <?= $csrf->renderInput() ?>
                                <input type="hidden" name="id" value="<?= (int) $cat->getId() ?>">
                                <div class="flex-grow">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nouveau nom *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($cat->getName(), ENT_QUOTES, 'UTF-8') ?>" required class="w-full bg-white border border-slate-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded text-xs transition">
                                    Enregistrer
                                </button>
                                <button type="button" onclick="document.getElementById('edit_cat_<?= (int) $cat->getId() ?>').classList.add('hidden');" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-3 py-1.5 rounded text-xs transition">
                                    Annuler
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
