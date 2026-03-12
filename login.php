<?php
// ============================================================
// login.php — Page de connexion
//
// Fonctionnement :
//   1. Affiche le formulaire email + mot de passe
//   2. En POST : valide les données et vérifie en BDD
//   3. Si OK → stocke en session et redirige vers dashboard.php
//   4. Si erreur → réaffiche le formulaire avec l'email conservé
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Si déjà connecté, pas besoin d'afficher le formulaire
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// Variables d'état du formulaire
$erreur = '';
$email  = '';  // Conserve l'email en cas d'erreur pour ne pas le resaisir

// ---- Traitement du formulaire (méthode POST uniquement) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère et nettoie les champs
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validation : champs obligatoires
    if (empty($email) || empty($password)) {
        $erreur = "Veuillez remplir tous les champs.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Validation : format email
        $erreur = "L'adresse email n'est pas valide.";

    } else {
        // Recherche l'utilisateur par email (requête préparée = protection injection SQL)
        $stmt = $pdo->prepare("SELECT id, nom, role, password_hash, points FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Mot de passe correct → on crée la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom']     = $user['nom'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['points']  = $user['points'];

            // Redirection vers le tableau de bord
            header("Location: dashboard.php");
            exit;

        } else {
            // Email inconnu ou mauvais mot de passe
            // Message volontairement vague pour ne pas aider les attaquants
            $erreur = "Email ou mot de passe incorrect.";
        }
    }
}

$pageTitle = 'Connexion';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="form-container">
    <h2 class="form-title">Se connecter</h2>

    <!-- Message d'erreur -->
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($erreur) ?>
        </div>
    <?php endif; ?>

    <!--
        Formulaire de connexion.
        L'email est conservé (value="...") en cas d'erreur pour le confort de l'utilisateur.
        Le mot de passe ne doit jamais être re-rempli pour des raisons de sécurité.
    -->
    <form method="POST" action="login.php">

        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?= htmlspecialchars($email) ?>"
                   placeholder="ex: joueur@test.com"
                   autocomplete="email"
                   required>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="Votre mot de passe"
                   autocomplete="current-password"
                   required>
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>

    </form>

    <!-- Comptes de test (utile en développement) -->
    <div class="hero-visual" style="margin-top:1.5rem; font-size:0.9rem; text-align:left; padding:1rem 1.5rem;">
        <strong style="color:var(--or-eclat)">Comptes de test (mot de passe : motdepasse123)</strong><br><br>
        <span style="color:#8a8a8a">Admin&nbsp;&nbsp;&nbsp; :</span> admin@test.com<br>
        <span style="color:#2ecc40">Joueur&nbsp;&nbsp; :</span> user@test.com<br>
        <span style="color:#3a7aff">Visiteur :</span> visiteur@test.com
    </div>

    <p class="form-link">
        Pas encore de compte ? <a href="register.php">S'inscrire gratuitement</a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>
