<?php
// ============================================================
// register.php — Page d'inscription
//
// Fonctionnement :
//   1. Affiche le formulaire (nom, email, mot de passe x2, rôle)
//   2. En POST : validation complète de tous les champs
//   3. Si OK → crée le compte, donne les 25 cartes de départ (si joueur),
//      connecte automatiquement et redirige vers dashboard.php
//   4. Si erreur → réaffiche avec les valeurs conservées et les messages d'erreur
//
// SÉCURITÉ : le rôle 'admin' ne peut PAS être sélectionné depuis ce formulaire.
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Si déjà connecté, inutile de s'inscrire à nouveau
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// ---- Cartes données à chaque nouveau joueur (Starter Pack) ----
// Ce sont 25 cartes choisies pour représenter toutes les raretés et les types
const STARTER_CARDS = [1, 2, 3, 6, 7, 8, 10, 12, 15, 17, 19, 21, 23, 26, 27, 29, 32, 33, 35, 37, 39, 41, 43, 45, 48];

// Variables d'état du formulaire (conservées en cas d'erreur)
$erreurs = [];
$nom     = '';
$email   = '';
$role    = 'joueur';  // Rôle par défaut proposé

// ---- Traitement du formulaire (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère et nettoie les champs
    $nom      = trim($_POST['nom']      ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $role     = trim($_POST['role']     ?? 'joueur');

    // ----------------------------------------------------------------
    // VALIDATION — chaque champ est vérifié individuellement
    // Les erreurs sont stockées dans $erreurs['champ']
    // ----------------------------------------------------------------

    // Nom : obligatoire, min 2 caractères
    if (empty($nom)) {
        $erreurs['nom'] = "Le nom est obligatoire.";
    } elseif (strlen($nom) < 2) {
        $erreurs['nom'] = "Le nom doit comporter au moins 2 caractères.";
    } elseif (strlen($nom) > 50) {
        $erreurs['nom'] = "Le nom ne peut pas dépasser 50 caractères.";
    }

    // Email : obligatoire, format valide
    if (empty($email)) {
        $erreurs['email'] = "L'adresse email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = "L'adresse email n'est pas valide.";
    }

    // Mot de passe : obligatoire, min 6 caractères
    if (empty($password)) {
        $erreurs['password'] = "Le mot de passe est obligatoire.";
    } elseif (strlen($password) < 6) {
        $erreurs['password'] = "Le mot de passe doit faire au moins 6 caractères.";
    }

    // Confirmation : doit correspondre au mot de passe
    if ($password !== $confirm) {
        $erreurs['confirm'] = "Les mots de passe ne correspondent pas.";
    }

    // Rôle : seulement 'joueur' ou 'visiteur' (jamais 'admin' depuis ce formulaire)
    if (!in_array($role, ['joueur', 'visiteur'], true)) {
        $erreurs['role'] = "Rôle invalide.";
        $role = 'joueur'; // Sécurité : on force le rôle à joueur
    }

    // ----------------------------------------------------------------
    // INSERTION EN BASE (seulement si pas d'erreurs)
    // ----------------------------------------------------------------
    if (empty($erreurs)) {

        // Vérifie que l'email n'est pas déjà utilisé
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erreurs['email'] = "Cette adresse email est déjà utilisée.";

        } else {
            // Hash le mot de passe AVANT de le stocker (jamais en clair !)
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insertion du nouvel utilisateur (500 points de départ)
            $stmt = $pdo->prepare(
                "INSERT INTO users (nom, email, password_hash, role, points)
                 VALUES (?, ?, ?, ?, 500)"
            );
            $stmt->execute([$nom, $email, $hash, $role]);
            $newUserId = (int)$pdo->lastInsertId();

            // Si c'est un joueur → lui donner les 25 cartes de départ
            if ($role === 'joueur') {
                $stmtCard = $pdo->prepare(
                    "INSERT INTO user_cards (user_id, card_id) VALUES (?, ?)"
                );
                foreach (STARTER_CARDS as $cardId) {
                    $stmtCard->execute([$newUserId, $cardId]);
                }
            }

            // Connexion automatique après inscription
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['nom']     = $nom;
            $_SESSION['role']    = $role;
            $_SESSION['points']  = 500;

            header("Location: dashboard.php");
            exit;
        }
    }
}

$pageTitle = 'Inscription';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="form-container">
    <h2 class="form-title">Créer un compte</h2>

    <!--
        Formulaire d'inscription.
        Les valeurs sont conservées en cas d'erreur (sauf les mots de passe).
        Chaque champ affiche son message d'erreur juste en dessous.
    -->
    <form method="POST" action="register.php">

        <!-- Nom d'aventurier -->
        <div class="form-group">
            <label for="nom">Nom d'aventurier</label>
            <input type="text"
                   id="nom"
                   name="nom"
                   value="<?= htmlspecialchars($nom) ?>"
                   placeholder="Votre nom dans le jeu"
                   maxlength="50"
                   required>
            <?php if (isset($erreurs['nom'])): ?>
                <span class="form-error"><?= htmlspecialchars($erreurs['nom']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?= htmlspecialchars($email) ?>"
                   placeholder="votre@email.com"
                   autocomplete="email"
                   required>
            <?php if (isset($erreurs['email'])): ?>
                <span class="form-error"><?= htmlspecialchars($erreurs['email']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Mot de passe -->
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="6 caractères minimum"
                   autocomplete="new-password"
                   required>
            <?php if (isset($erreurs['password'])): ?>
                <span class="form-error"><?= htmlspecialchars($erreurs['password']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Confirmation du mot de passe -->
        <div class="form-group">
            <label for="confirm">Confirmer le mot de passe</label>
            <input type="password"
                   id="confirm"
                   name="confirm"
                   placeholder="Répétez votre mot de passe"
                   autocomplete="new-password"
                   required>
            <?php if (isset($erreurs['confirm'])): ?>
                <span class="form-error"><?= htmlspecialchars($erreurs['confirm']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Choix du rôle (admin non disponible à l'inscription) -->
        <div class="form-group">
            <label for="role">Type de compte</label>
            <select id="role" name="role">
                <option value="joueur"   <?= $role === 'joueur'   ? 'selected' : '' ?>>
                    Joueur — Collectionner et combattre (recommandé)
                </option>
                <option value="visiteur" <?= $role === 'visiteur' ? 'selected' : '' ?>>
                    Visiteur — Consulter le catalogue seulement
                </option>
                <!-- Le rôle admin n'est volontairement pas disponible ici -->
            </select>
            <?php if (isset($erreurs['role'])): ?>
                <span class="form-error"><?= htmlspecialchars($erreurs['role']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit">Créer mon compte</button>

    </form>

    <!-- Information sur le pack de départ -->
    <div class="hero-visual" style="margin-top:1.5rem; font-size:0.9rem; padding:1rem 1.5rem;">
        <strong style="color:var(--or-eclat)">Pack de départ Joueur :</strong>
        500 points + 25 cartes offertes dont des raretés épiques, légendaires et mythiques !
    </div>

    <p class="form-link">
        Déjà un compte ? <a href="login.php">Se connecter</a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>
