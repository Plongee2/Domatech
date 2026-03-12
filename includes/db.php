<?php
// ============================================================
// includes/db.php — Connexion PDO à la base de données MySQL
//
// Ce fichier est inclus dans TOUTES les pages PHP via require_once.
// Il crée la variable $pdo utilisée pour toutes les requêtes SQL.
//
// CONFIGURATION :
//   - Hôte    : localhost (XAMPP/WAMP par défaut)
//   - BDD     : pafalan
//   - User    : root (XAMPP par défaut)
//   - Mot de passe : vide (XAMPP par défaut)
// ============================================================

try {
    $pdo = new PDO(
        // DSN : type:host=...;dbname=...;charset=...
        "mysql:host=localhost;dbname=pafalan;charset=utf8mb4",
        "root",   // Utilisateur MySQL (adapter si besoin)
        "",       // Mot de passe MySQL (vide sur XAMPP par défaut)
        [
            // Affiche les erreurs SQL sous forme d'exceptions PHP
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            // Retourne les résultats en tableaux associatifs par défaut
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Désactive l'émulation des requêtes préparées (plus sécurisé)
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Si la connexion échoue, on arrête tout avec un message d'erreur
    die("<h2>Erreur de connexion à la base de données</h2><p>" . $e->getMessage() . "</p>");
}

/**
 * Exécute une requête et retourne le premier résultat sous forme d'entier.
 * Utile pour les requêtes COUNT(*) ou SELECT un_champ.
 *
 * Exemple : count_query($pdo, "SELECT COUNT(*) FROM users")
 *           count_query($pdo, "SELECT points FROM users WHERE id = ?", [$id])
 */
function count_query(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
