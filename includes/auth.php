<?php
// ============================================================
// includes/auth.php — Fonctions d'authentification et de contrôle d'accès
//
// Ce fichier fournit des fonctions utilitaires pour gérer :
//   - La vérification de la connexion
//   - La protection des pages selon le rôle
//   - L'accès aux informations de l'utilisateur connecté
//
// UTILISATION : toujours inclure APRÈS session_start()
// ============================================================


/**
 * Vérifie si l'utilisateur est connecté.
 * @return bool true si connecté, false sinon
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}


/**
 * Récupère les informations de l'utilisateur connecté depuis la session.
 * @return array|null Tableau avec id, nom, role — ou null si non connecté
 */
function getUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'nom'   => $_SESSION['nom'],
        'role'  => $_SESSION['role'],
        'points'=> $_SESSION['points'] ?? 0,
    ];
}


/**
 * Protège une page : redirige vers login.php si l'utilisateur n'est pas connecté.
 * @param string $rootPath Chemin vers la racine ('' pour pages racine, '../' pour admin/)
 */
function requireLogin(string $rootPath = ''): void {
    if (!isLoggedIn()) {
        header("Location: " . $rootPath . "login.php");
        exit;
    }
}


/**
 * Protège une page selon le rôle : redirige vers 403 si le rôle ne correspond pas.
 * Appelle requireLogin() en premier pour s'assurer que l'utilisateur est connecté.
 * @param string $role     Le rôle requis ('admin', 'joueur', 'visiteur')
 * @param string $rootPath Chemin vers la racine
 */
function requireRole(string $role, string $rootPath = ''): void {
    // D'abord vérifier la connexion
    requireLogin($rootPath);

    // Ensuite vérifier le rôle
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        // Page d'erreur simple mais lisible
        echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>";
        echo "<title>Accès interdit — PafAlan</title>";
        echo "<link rel='stylesheet' href='" . $rootPath . "style.css'></head><body>";
        echo "<div style='max-width:600px;margin:4rem auto;text-align:center;padding:2rem'>";
        echo "<h1 style='color:#ff2050;font-family:Cinzel,serif'>403 — Accès Interdit</h1>";
        echo "<p style='color:#f0d89a'>Vous n'avez pas les droits pour accéder à cette page.</p>";
        echo "<a href='" . $rootPath . "dashboard.php' style='color:#c9a227'>Retour au tableau de bord</a>";
        echo "</div></body></html>";
        exit;
    }
}


/**
 * Vérifie si l'utilisateur connecté est un administrateur.
 * @return bool
 */
function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}


/**
 * Vérifie si l'utilisateur connecté est un joueur.
 * @return bool
 */
function isJoueur(): bool {
    return isLoggedIn() && $_SESSION['role'] === 'joueur';
}


/**
 * Met à jour les points en session (après achat ou combat).
 * @param int $newPoints Nouveau solde de points
 */
function updateSessionPoints(int $newPoints): void {
    $_SESSION['points'] = $newPoints;
}
