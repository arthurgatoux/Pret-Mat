<?php
/** @var array<\App\Model\Item> $items */
/** @var array<\App\Model\Category> $categories */
/** @var \App\Security\CsrfManager $csrf */
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Administration du matériel</h1>
    <p class="text-slate-600 mt-1">Créez, modifiez ou gérez le parc d'équipements du club.</p>
</div>

<!-- Formulaire d'ajout -->
<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm mb-10">
    <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Ajouter un nouvel équipement
    </h2>
    <form action="/admin/items/create" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?= $csrf->renderInput() ?>
        <div>
            <label for="new_item_name" class="block text-xs font-semibold text-slate-700 mb-1">Nom du matériel *</label>
            <input type="text" id="new_item_name" name="name" required placeholder="Ex : Micro Shure Beta 58" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label for="new_item_cat" class="block text-xs font-semibold text-slate-700 mb-1">Catégorie *</label>
            <select id="new_item_cat" name="category_id" required class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Sélectionner une catégorie...</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat->getId() ?>">
                        <?= htmlspecialchars($cat->getName(), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="new_item_desc" class="block text-xs font-semibold text-slate-700 mb-1">Description (optionnelle)</label>
            <input type="text" id="new_item_desc" name="description" placeholder="Ex : État neuf, fourni avec pince et housse" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg text-sm shadow-sm transition">
                Enregistrer le matériel
            </button>
        </div>
    </form>
</div>

<!-- Liste des matériels -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Inventaire complet (<?= count($items) ?> objets)</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">ID</th>
                    <th class="py-3 px-4">Nom</th>
                    <th class="py-3 px-4">Catégorie</th>
                    <th class="py-3 px-4">Description</th>
                    <th class="py-3 px-4">Disponibilité</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono text-xs text-slate-400">#<?= (int) $item->getId() ?></td>
                        <td class="py-3 px-4 font-semibold text-slate-900">
                            <?= htmlspecialchars($item->getName(), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                <?= htmlspecialchars((string) $item->getCategoryName(), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-500 max-w-xs truncate">
                            <?= htmlspecialchars($item->getDescription() ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($item->isAvailable()): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Disponible
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                    Emprunté
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Bouton pour ouvrir/déplier le formulaire d'édition -->
                                <button type="button" onclick="document.getElementById('edit_item_<?= (int) $item->getId() ?>').classList.toggle('hidden');" class="text-xs font-semibold text-slate-600 hover:text-indigo-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded transition">
                                    Modifier
                                </button>

                                <!-- Formulaire de suppression -->
                                <form action="/admin/items/delete" method="POST" class="inline" onsubmit="return confirm('Confirmez-vous la suppression définitive de cet objet ?');">
                                    <?= $csrf->renderInput() ?>
                                    <input type="hidden" name="id" value="<?= (int) $item->getId() ?>">
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded transition">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <!-- Ligne d'édition masquée par défaut -->
                    <tr id="edit_item_<?= (int) $item->getId() ?>" class="hidden bg-indigo-50/40">
                        <td colspan="6" class="p-4 border-t border-indigo-100">
                            <form action="/admin/items/update" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <?= $csrf->renderInput() ?>
                                <input type="hidden" name="id" value="<?= (int) $item->getId() ?>">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nom *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($item->getName(), ENT_QUOTES, 'UTF-8') ?>" required class="w-full bg-white border border-slate-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catégorie *</label>
                                    <select name="category_id" required class="w-full bg-white border border-slate-300 rounded px-2.5 py-1.5 text-xs">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int) $cat->getId() ?>" <?= $cat->getId() === $item->getCategoryId() ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat->getName(), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                                    <input type="text" name="description" value="<?= htmlspecialchars($item->getDescription() ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-white border border-slate-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div class="flex items-end gap-2">
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded text-xs transition">
                                        Enregistrer
                                    </button>
                                    <button type="button" onclick="document.getElementById('edit_item_<?= (int) $item->getId() ?>').classList.add('hidden');" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-3 py-1.5 rounded text-xs transition">
                                        Annuler
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
