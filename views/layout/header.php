<?php
$session = $session ?? new \App\Security\SessionManager();
$csrf = $csrf ?? new \App\Security\CsrfManager();
$isLoggedIn = $session->isLoggedIn();
$isAdmin = $session->isAdmin();
$userName = $session->getUserName();
$userRole = $session->getUserRole();
$flashes = $session->getFlashes();
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'PretMat - Prêt de matériel', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex flex-col min-h-full text-slate-800">
    <header class="bg-indigo-700 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
            <a href="/" class="text-2xl font-bold tracking-tight flex items-center gap-2">
                <span class="bg-white text-indigo-700 px-2 py-0.5 rounded text-lg font-black shadow-sm">PM</span>
                PretMat
            </a>

            <!-- Navigation principale -->
            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="/" class="hover:text-indigo-200 transition">Accueil</a>
                <a href="/catalogue" class="hover:text-indigo-200 transition">Catalogue</a>
                <?php if ($isLoggedIn): ?>
                    <a href="/mes-prets" class="hover:text-indigo-200 transition">Mes prêts</a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <div class="h-4 w-px bg-indigo-500"></div>
                    <span class="text-xs uppercase tracking-wider text-indigo-300 font-semibold">Admin:</span>
                    <a href="/admin/items" class="hover:text-indigo-200 transition">Matériel</a>
                    <a href="/admin/categories" class="hover:text-indigo-200 transition">Catégories</a>
                    <a href="/admin/loans" class="hover:text-indigo-200 transition">Prêts</a>
                <?php endif; ?>
            </nav>

            <!-- Espace Authentification -->
            <div class="flex items-center gap-4 text-sm">
                <?php if ($isLoggedIn): ?>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-indigo-100"><?= htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="px-2 py-0.5 text-xs rounded-full font-semibold <?= $isAdmin ? 'bg-amber-400 text-amber-950' : 'bg-indigo-900 text-indigo-200' ?>">
                            <?= $isAdmin ? 'Admin' : 'Membre' ?>
                        </span>
                    </div>
                    <form action="/logout" method="POST" class="inline">
                        <?= $csrf->renderInput() ?>
                        <button type="submit" class="text-xs font-semibold bg-indigo-800 hover:bg-indigo-900 text-white px-3 py-1.5 rounded transition">
                            Déconnexion
                        </button>
                    </form>
                <?php else: ?>
                    <a href="/auth/google" class="inline-flex items-center gap-2 bg-white text-slate-800 hover:bg-slate-100 px-4 py-2 rounded-lg font-semibold shadow-sm transition text-xs">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        Connexion Google
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-6xl w-full mx-auto px-4 py-8">
        <!-- Messages Flash -->
        <?php if (!empty($flashes)): ?>
            <div class="mb-6 space-y-2">
                <?php foreach ($flashes as $flash): ?>
                    <?php
                    $colors = match ($flash['type']) {
                        'success' => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                        'error'   => 'bg-rose-50 text-rose-800 border-rose-300',
                        'info'    => 'bg-sky-50 text-sky-800 border-sky-300',
                        default   => 'bg-slate-50 text-slate-800 border-slate-300',
                    };
                    ?>
                    <div class="p-4 rounded-lg border text-sm font-medium <?= $colors ?>">
                        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
