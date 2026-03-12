<?php
// ============================================================
// logout.php — Déconnexion de l'utilisateur
//
// Ce fichier ne contient que de la logique PHP, aucun HTML.
// Il détruit la session et redirige vers la page d'accueil.
// ============================================================
session_start();

// Vide toutes les variables de session
$_SESSION = [];

// Détruit le cookie de session dans le navigateur (bonne pratique)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Détruit la session côté serveur
session_destroy();

// Redirige vers la page d'accueil
header("Location: index.php");
exit;
