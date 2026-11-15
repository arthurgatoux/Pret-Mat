<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Model\Loan;
use App\Model\User;
use App\Repository\UserRepository;
use App\Security\AccessControl;
use App\Security\AuthService;
use App\Security\CsrfManager;
use App\Security\SessionManager;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;

Env::load(dirname(__DIR__));
$session = new SessionManager();
$session->start();

$database = Database::fromEnv();

echo "=== TEST MODULE 4 : SÉCURITÉ & AUTHENTIFICATION ===" . PHP_EOL;

// 1. Tests SessionManager
echo PHP_EOL . "--- 1. Tests SessionManager ---" . PHP_EOL;

$testUser = new User(10, 'sub_test', 'test@pretmat.local', 'Testeur', 'member');
$session->setUser($testUser);
assert($session->isLoggedIn() === true, 'Utilisateur doit être connecté');
assert($session->isMember() === true, 'Utilisateur doit être membre');
assert($session->isAdmin() === false, 'Utilisateur ne doit pas être admin');
echo "[OK] SessionManager: utilisateur membre connecté avec succès" . PHP_EOL;

$adminUser = new User(11, 'sub_admin', 'admin@pretmat.local', 'Administrateur', 'admin');
$session->setUser($adminUser);
assert($session->isAdmin() === true, 'Utilisateur doit être reconnu comme admin');
echo "[OK] SessionManager: utilisateur admin connecté avec succès" . PHP_EOL;

// Flash messages
$session->addFlash('success', 'Message de succès test');
$session->addFlash('error', 'Message d erreur test');
$flashes = $session->getFlashes();
assert(count($flashes) === 2, 'Doit contenir 2 messages flash');
assert(empty($session->getFlashes()), 'Les flashs doivent être vidés après lecture');
echo "[OK] Flash messages: ajout et vidage automatique validés" . PHP_EOL;

// State et Nonce
$session->setOauthState('state_xyz');
$session->setOauthNonce('nonce_abc');
assert($session->getOauthState() === 'state_xyz', 'State doit correspondre');
assert($session->getOauthNonce() === 'nonce_abc', 'Nonce doit correspondre');
$session->clearOauthData();
assert($session->getOauthState() === null, 'State doit être effacé');
echo "[OK] Gestion OAuth state et nonce validée" . PHP_EOL;

// 2. Tests CsrfManager
echo PHP_EOL . "--- 2. Tests CsrfManager ---" . PHP_EOL;
$csrf = new CsrfManager();
$token = $csrf->getToken();
assert(is_string($token) && strlen($token) === 64, 'Le jeton CSRF doit être une chaîne hexadécimale de 64 caractères');
assert($csrf->validate($token) === true, 'Le jeton CSRF doit être valide');
assert($csrf->validate('faux_jeton') === false, 'Un faux jeton doit être rejeté');
assert($csrf->validate(null) === false, 'Un jeton null doit être rejeté');
$htmlInput = $csrf->renderInput();
assert(str_contains($htmlInput, 'name="csrf_token"'), 'L input HTML CSRF doit contenir name="csrf_token"');
assert(str_contains($htmlInput, $token), 'L input HTML CSRF doit contenir la valeur du jeton');
echo "[OK] CsrfManager: génération, validation stricte et rendu HTML validés" . PHP_EOL;

// 3. Tests AccessControl
echo PHP_EOL . "--- 3. Tests AccessControl & Prévention IDOR ---" . PHP_EOL;
$session->setUser(new User(2, 'sub_bob', 'bob@test.local', 'Bob', 'member'));

$bobLoan = new Loan(
    id: 1,
    userId: 2,
    itemId: 1,
    borrowedAt: new DateTimeImmutable(),
    dueAt: (new DateTimeImmutable())->modify('+7 days')
);

$aliceLoan = new Loan(
    id: 2,
    userId: 1,
    itemId: 2,
    borrowedAt: new DateTimeImmutable(),
    dueAt: (new DateTimeImmutable())->modify('+7 days')
);

// Bob peut gérer son propre prêt, mais pas celui d'Alice
assert(AccessControl::canManageLoan($session, $bobLoan) === true, 'Bob doit pouvoir gérer son propre prêt');
assert(AccessControl::canManageLoan($session, $aliceLoan) === false, 'Bob ne doit PAS pouvoir agir sur le prêt d Alice (anti-IDOR)');
echo "[OK] AccessControl: Membre restreint à ses propres prêts (anti-IDOR)" . PHP_EOL;

// L'administrateur peut gérer n'importe quel prêt
$session->setUser(new User(1, 'sub_alice', 'alice@test.local', 'Alice', 'admin'));
assert(AccessControl::canManageLoan($session, $bobLoan) === true, 'L admin peut gérer le prêt de Bob');
assert(AccessControl::canManageLoan($session, $aliceLoan) === true, 'L admin peut gérer son propre prêt');
echo "[OK] AccessControl: Admin autorisé sur tous les prêts" . PHP_EOL;

// 4. Tests AuthService & Google OAuth
echo PHP_EOL . "--- 4. Tests AuthService (Google OAuth, State, Nonce, Rôles) ---" . PHP_EOL;
$userRepo = new UserRepository($database);
$authService = new AuthService($userRepo, $session);

// Test génération URL
$authUrl = $authService->getAuthorizationUrl();
assert(str_starts_with($authUrl, 'https://accounts.google.com/o/oauth2/v2/auth'), 'URL Google auth valide');
assert(str_contains($authUrl, 'scope=openid%20email%20profile'), 'Scopes openid email profile présents');
assert(str_contains($authUrl, 'nonce='), 'Paramètre nonce présent');
assert(str_contains($authUrl, 'state='), 'Paramètre state présent');
echo "[OK] getAuthorizationUrl: URL Google conforme (openid, email, profile, nonce, state)" . PHP_EOL;

// Test simulation callback avec Provider mocké
class FakeGoogleProvider extends Google {
    public ?GoogleUser $fakeUser = null;
    public function getAccessToken($grant, array $options = []): AccessToken {
        return new AccessToken(['access_token' => 'fake_token', 'expires' => time() + 3600]);
    }
    public function getResourceOwner(\League\OAuth2\Client\Token\AccessToken $token) {
        return $this->fakeUser;
    }
}

$fakeProvider = new FakeGoogleProvider([
    'clientId' => 'test_id',
    'clientSecret' => 'test_secret',
    'redirectUri' => 'http://localhost:8080/auth/google/callback',
]);

// Test 4.1 : Rejet si email_verified est faux
$session->setOauthState('valid_state');
$fakeProvider->fakeUser = new GoogleUser([
    'sub' => 'sub_unverified',
    'email' => 'unverified@test.local',
    'name' => 'Non Vérifié',
    'email_verified' => false,
]);
$authServiceMock = new AuthService($userRepo, $session, $fakeProvider);

$rejected = false;
try {
    $authServiceMock->handleCallback('fake_code', 'valid_state');
} catch (RuntimeException $e) {
    $rejected = true;
    assert(str_contains($e->getMessage(), "n'est pas vérifiée"), 'Message d erreur adéquat');
}
assert($rejected, 'La connexion doit être rejetée si email_verified est faux');
echo "[OK] Callback OAuth: Rejet strict si email_verified est faux" . PHP_EOL;

// Test 4.2 : Rejet si state invalide
$rejectedState = false;
try {
    $authServiceMock->handleCallback('fake_code', 'mauvais_state');
} catch (RuntimeException $e) {
    $rejectedState = true;
    assert(str_contains($e->getMessage(), "jeton 'state' invalide"), 'Message d erreur de state');
}
assert($rejectedState, 'La connexion doit être rejetée si le state ne correspond pas');
echo "[OK] Callback OAuth: Rejet strict si le state est invalide (anti-CSRF OAuth)" . PHP_EOL;

// Test 4.3 : Première connexion d'un membre normal -> rôle member
$session->setOauthState('state_member');
$subMember = 'sub_member_' . uniqid();
$emailMember = 'membre_' . uniqid() . '@test.local';
$fakeProvider->fakeUser = new GoogleUser([
    'sub' => $subMember,
    'email' => $emailMember,
    'name' => 'Nouveau Membre Test',
    'email_verified' => true,
]);
$newUser = $authServiceMock->handleCallback('fake_code', 'state_member');
assert($newUser->getRole() === 'member', 'Nouveau membre doit avoir le rôle member');
echo "[OK] Première connexion: compte créé automatiquement avec le rôle member" . PHP_EOL;

// Test 4.4 : Connexion avec e-mail == ADMIN_EMAIL -> promotion en admin
$adminEmail = (string) Env::get('ADMIN_EMAIL');
if ($adminEmail !== '') {
    $session->setOauthState('state_admin');
    $fakeProvider->fakeUser = new GoogleUser([
        'sub' => 'sub_admin_matching_' . uniqid(),
        'email' => $adminEmail,
        'name' => 'Super Admin',
        'email_verified' => true,
    ]);
    $adminUser = $authServiceMock->handleCallback('fake_code', 'state_admin');
    assert($adminUser->isAdmin(), "Le compte correspondant à ADMIN_EMAIL ({$adminEmail}) doit être admin");
    echo "[OK] Correspondance ADMIN_EMAIL: utilisateur créé ou promu admin avec succès" . PHP_EOL;
}

// Nettoyage des utilisateurs de test créés
$database->getConnection()->exec("DELETE FROM users WHERE google_sub IN ('{$subMember}', '{$adminUser->getGoogleSub()}')");

echo PHP_EOL . "=== TOUS LES TESTS DU MODULE 4 ONT RÉUSSI ===" . PHP_EOL;
