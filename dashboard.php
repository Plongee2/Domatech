<?php
// ============================================================
// dashboard.php — Tableau de bord (Admin / Joueur / Visiteur)
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];

// ================================================================
// Données selon le rôle
// ================================================================

if ($role === 'admin') {

    $totalUsers        = count_query($pdo, "SELECT COUNT(*) FROM users");
    $totalCartes       = count_query($pdo, "SELECT COUNT(*) FROM cards");
    $totalCombats      = count_query($pdo, "SELECT COUNT(*) FROM combats");
    $totalAcquisitions = count_query($pdo, "SELECT COUNT(*) FROM user_cards");

    $stmt = $pdo->prepare(
        "SELECT id, nom, email, role, points, created_at
         FROM users ORDER BY created_at DESC LIMIT 5"
    );
    $stmt->execute();
    $derniersInscrits = $stmt->fetchAll();

} elseif ($role === 'joueur') {

    $points      = count_query($pdo, "SELECT points FROM users WHERE id = ?", [$uid]);
    $mesCartes   = count_query($pdo, "SELECT COUNT(*) FROM user_cards WHERE user_id = ?", [$uid]);
    $totalCartes = count_query($pdo, "SELECT COUNT(*) FROM cards");
    $nbDecks     = count_query($pdo, "SELECT COUNT(*) FROM decks WHERE user_id = ?", [$uid]);

    $_SESSION['points'] = $points; // Synchronise la session

    $stmt = $pdo->prepare(
        "SELECT winner, opponent_name, played_at
         FROM combats WHERE player_id = ?
         ORDER BY played_at DESC LIMIT 1"
    );
    $stmt->execute([$uid]);
    $dernierCombat = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT c.name, c.rarity, c.type, uc.acquired_at
         FROM user_cards uc
         JOIN cards c ON c.id = uc.card_id
         WHERE uc.user_id = ?
         ORDER BY uc.acquired_at DESC LIMIT 5"
    );
    $stmt->execute([$uid]);
    $dernieresCartes = $stmt->fetchAll();

} else { // visiteur

    $totalCartes  = count_query($pdo, "SELECT COUNT(*) FROM cards");
    $totalJoueurs = count_query($pdo, "SELECT COUNT(*) FROM users WHERE role = 'joueur'");
    $totalCombats = count_query($pdo, "SELECT COUNT(*) FROM combats");

}

$pageTitle = 'Mon tableau de bord';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="catalogue-main">

    <!-- En-tête : nom + badge de rôle -->
    <div class="hero-content" style="padding:1.5rem 2rem; margin-bottom:0">
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap">
            <h2 class="hero-title" style="font-size:1.6rem">
                Bonjour, <?= htmlspecialchars($_SESSION['nom']) ?> !
            </h2>
            <span class="role-badge role-<?= $role ?>">
                <?= ucfirst($role) ?>
            </span>
        </div>
    </div>


    <?php if ($role === 'admin'): ?>
    <!-- ======================================================
         ADMIN
         ====================================================== -->

        <!-- Statistiques globales -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?= $totalUsers ?></span>
                <span class="stat-label">Utilisateurs</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $totalCartes ?></span>
                <span class="stat-label">Cartes dans le catalogue</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $totalCombats ?></span>
                <span class="stat-label">Combats joués</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $totalAcquisitions ?></span>
                <span class="stat-label">Cartes possédées (total)</span>
            </div>
        </div>

        <!-- 5 derniers inscrits -->
        <h3 class="section-title">Derniers utilisateurs inscrits</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nom</th><th>Email</th><th>Rôle</th>
                    <th>Points</th><th>Inscrit le</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($derniersInscrits as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nom']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= ucfirst($u['role']) ?></td>
                    <td><?= number_format($u['points'], 0, ',', ' ') ?></td>
                    <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td><a href="admin/users.php" class="btn-small">Gérer</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Raccourcis -->
        <div style="display:flex; gap:1rem; margin-top:1.5rem; flex-wrap:wrap">
            <a href="admin/index.php" class="btn-hero">Dashboard Admin</a>
            <a href="admin/cards.php" class="btn-hero">Gérer les cartes</a>
            <a href="admin/users.php" class="btn-hero">Gérer les utilisateurs</a>
            <a href="catalogue.php"   class="btn-hero">Voir le catalogue</a>
        </div>


    <?php elseif ($role === 'joueur'): ?>
    <!-- ======================================================
         JOUEUR
         ====================================================== -->

        <!-- Statistiques personnelles -->
        <div class="stats-grid">

            <div class="stat-card">
                <span class="stat-number" style="color:var(--or-eclat)">
                    <?= number_format($points, 0, ',', ' ') ?>
                </span>
                <span class="stat-label">Points disponibles</span>
                <p class="stat-desc">Pour acheter de nouvelles cartes dans le catalogue.</p>
            </div>

            <div class="stat-card">
                <span class="stat-number"><?= $mesCartes ?> / <?= $totalCartes ?></span>
                <span class="stat-label">Cartes collectionnées</span>
                <p class="stat-desc">
                    <?= $totalCartes > 0 ? round($mesCartes / $totalCartes * 100) : 0 ?>% du catalogue complété.
                </p>
            </div>

            <div class="stat-card">
                <span class="stat-number"><?= $nbDecks ?></span>
                <span class="stat-label">Decks créés</span>
                <p class="stat-desc">Construisez jusqu'à 25 cartes par deck.</p>
            </div>

            <div class="stat-card">
                <?php if ($dernierCombat):
                    $resultat = match($dernierCombat['winner']) {
                        'player' => ['texte' => 'Victoire', 'couleur' => '#2ecc40'],
                        'draw'   => ['texte' => 'Nul',      'couleur' => '#aaa'],
                        default  => ['texte' => 'Défaite',  'couleur' => '#ff4444'],
                    };
                ?>
                    <span class="stat-number" style="color:<?= $resultat['couleur'] ?>">
                        <?= $resultat['texte'] ?>
                    </span>
                    <span class="stat-label">Dernier combat</span>
                    <p class="stat-desc">
                        vs <?= htmlspecialchars($dernierCombat['opponent_name']) ?>
                        — <?= date('d/m/Y', strtotime($dernierCombat['played_at'])) ?>
                    </p>
                <?php else: ?>
                    <span class="stat-number">—</span>
                    <span class="stat-label">Aucun combat</span>
                    <p class="stat-desc">Lancez votre premier combat !</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- 5 dernières cartes acquises -->
        <?php if (!empty($dernieresCartes)): ?>
            <h3 class="section-title">Dernières cartes acquises</h3>
            <table class="data-table">
                <thead>
                    <tr><th>Carte</th><th>Rareté</th><th>Type</th><th>Acquise le</th></tr>
                </thead>
                <tbody>
                <?php foreach ($dernieresCartes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['rarity']) ?></td>
                        <td><?= htmlspecialchars($c['type']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($c['acquired_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="hero-visual" style="margin-top:1rem">
                <p>Vous n'avez pas encore de cartes.<br>
                   <a href="catalogue.php">Visitez le catalogue pour en acquérir !</a></p>
            </div>
        <?php endif; ?>

        <!-- Accès rapides -->
        <div style="display:flex; gap:1rem; margin-top:1.5rem; flex-wrap:wrap">
            <a href="catalogue.php"  class="btn-hero">Acheter des cartes</a>
            <a href="collection.php" class="btn-hero">Ma collection</a>
            <a href="combat.php"     class="btn-hero">Combattre</a>
            <a href="amis.php"       class="btn-hero">Mes amis</a>
        </div>


    <?php else: ?>
    <!-- ======================================================
         VISITEUR
         ====================================================== -->

        <div class="hero-visual" style="margin-top:1rem">
            <p>
                Bienvenue, <?= htmlspecialchars($_SESSION['nom']) ?> !<br><br>
                En tant que <strong>visiteur</strong>, vous pouvez consulter le catalogue.<br>
                Pour <strong>collectionner des cartes, créer des decks et combattre</strong>,
                créez un compte Joueur !
            </p>
        </div>

        <!-- Stats globales publiques -->
        <div class="stats-grid" style="margin-top:1.5rem">
            <div class="stat-card">
                <span class="stat-number"><?= $totalCartes ?></span>
                <span class="stat-label">Cartes dans le catalogue</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $totalJoueurs ?></span>
                <span class="stat-label">Joueurs actifs</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $totalCombats ?></span>
                <span class="stat-label">Combats joués</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">Gratuit</span>
                <span class="stat-label">Compte Joueur</span>
            </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:1.5rem; flex-wrap:wrap">
            <a href="catalogue.php" class="btn-hero">Voir le catalogue</a>
            <a href="register.php"  class="btn-hero">Créer un compte Joueur</a>
        </div>

    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
