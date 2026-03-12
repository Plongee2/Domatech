<?php
// ============================================================
// includes/header.php — En-tête HTML commun à toutes les pages
//
// VARIABLES ATTENDUES (à définir avant d'inclure ce fichier) :
//   $pageTitle  — Titre de la page (ex: 'Catalogue')
//   $rootPath   — Chemin vers la racine du site :
//                   ''    pour les pages à la racine (index.php, login.php...)
//                   '../' pour les pages dans admin/ (admin/cards.php...)
//
// Ce fichier inclut db.php et auth.php automatiquement.
// Il démarre aussi la session si elle n'est pas encore démarrée.
// ============================================================

// Démarre la session PHP si elle n'est pas encore active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Valeur par défaut pour $rootPath (pages à la racine)
$rootPath = $rootPath ?? '';

// Inclusion des dépendances (connexion BDD + fonctions auth)
require_once $rootPath . 'includes/db.php';
require_once $rootPath . 'includes/auth.php';

// Si le joueur est connecté, on synchronise ses points depuis la BDD
// (pour que l'affichage soit toujours à jour après un achat)
if (isLoggedIn()) {
    $stmtPts = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmtPts->execute([$_SESSION['user_id']]);
    $freshPoints = $stmtPts->fetchColumn();
    if ($freshPoints !== false) {
        $_SESSION['points'] = (int)$freshPoints;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – PafAlan' : 'PafAlan' ?></title>
    <link rel="stylesheet" href="<?= $rootPath ?>style.css">
</head>
<body>

<header>

    <!-- Gauche : lien vers l'accueil + titre du site -->
    <div class="header-left">
        <a href="<?= $rootPath ?>index.php" style="text-decoration:none">
            <h1 class="site-title">PafAlan</h1>
        </a>

        <?php if (isLoggedIn()): ?>
            <!-- Affiche le nom et les points du joueur connecté -->
            <span class="avatar logged-in" title="Connecté en tant que <?= htmlspecialchars($_SESSION['nom']) ?>">
                <?= htmlspecialchars($_SESSION['nom']) ?>
                <?php if ($_SESSION['role'] === 'joueur'): ?>
                    &nbsp;·&nbsp;<span style="color:var(--or-eclat)"><?= number_format($_SESSION['points'] ?? 0, 0, ',', ' ') ?> pts</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Centre : barre de navigation adaptée au rôle -->
    <nav class="header-nav">

        <!-- Accueil : toujours visible -->
        <a href="<?= $rootPath ?>index.php" class="nav-btn">Accueil</a>

        <!-- Catalogue : visible par tous -->
        <a href="<?= $rootPath ?>catalogue.php" class="nav-btn">Catalogue</a>

        <?php if (isLoggedIn()): ?>

            <?php if ($_SESSION['role'] === 'joueur'): ?>
                <!-- Pages exclusives au joueur -->
                <a href="<?= $rootPath ?>collection.php" class="nav-btn">Collection</a>
                <a href="<?= $rootPath ?>combat.php"     class="nav-btn">Combat</a>
                <a href="<?= $rootPath ?>amis.php"       class="nav-btn">Amis</a>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <!-- Pages exclusives à l'admin -->
                <a href="<?= $rootPath ?>admin/index.php" class="nav-btn">Admin</a>
            <?php endif; ?>

            <!-- Tableau de bord et déconnexion : tous les connectés -->
            <a href="<?= $rootPath ?>dashboard.php" class="nav-btn">Mon compte</a>
            <a href="<?= $rootPath ?>logout.php"    class="nav-btn">Déconnexion</a>

        <?php else: ?>

            <!-- Non connecté : liens d'accès au compte -->
            <a href="<?= $rootPath ?>login.php"    class="nav-btn">Connexion</a>
            <a href="<?= $rootPath ?>register.php" class="nav-btn">Inscription</a>

        <?php endif; ?>

    </nav>

    <!-- Droite : signature -->
    <div class="header-right">DOMATECH IT</div>

</header>

<!-- Début du contenu principal de la page -->
<main>
