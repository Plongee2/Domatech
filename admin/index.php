<?php
// ============================================================
// admin/index.php — Dashboard de l'espace administrateur
//
// Réservé au rôle 'admin'.
//
// Affiche :
//   - Statistiques globales (users, cartes, combats, acquisitions)
//   - Liste des derniers utilisateurs inscrits
//   - Liens vers les pages de gestion
// ============================================================
session_start();
$rootPath = '../';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Protection : seul l'admin peut accéder ici
requireRole('admin', '../');

// ================================================================
// Statistiques globales
// ================================================================
$totalUsers        = count_query($pdo, "SELECT COUNT(*) FROM users");
$totalJoueurs      = count_query($pdo, "SELECT COUNT(*) FROM users WHERE role = 'joueur'");
$totalVisiteurs    = count_query($pdo, "SELECT COUNT(*) FROM users WHERE role = 'visiteur'");
$totalCartes       = count_query($pdo, "SELECT COUNT(*) FROM cards");
$totalCombats      = count_query($pdo, "SELECT COUNT(*) FROM combats");
$totalAcquisitions = count_query($pdo, "SELECT COUNT(*) FROM user_cards");
$totalDecks        = count_query($pdo, "SELECT COUNT(*) FROM decks");

// Répartition des raretés dans les collections
$stmt = $pdo->prepare(
    "SELECT c.rarity, COUNT(*) as nb
     FROM user_cards uc
     JOIN cards c ON c.id = uc.card_id
     GROUP BY c.rarity
     ORDER BY FIELD(c.rarity,'mythique','legendaire','epique','rare','peu-commune','commune')"
);
$stmt->execute();
$raretesStats = $stmt->fetchAll();

// 10 derniers utilisateurs inscrits
$stmt = $pdo->prepare(
    "SELECT u.id, u.nom, u.email, u.role, u.points, u.created_at,
            COUNT(uc.id) as nb_cartes
     FROM users u
     LEFT JOIN user_cards uc ON uc.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT 10"
);
$stmt->execute();
$derniersInscrits = $stmt->fetchAll();

// 5 derniers combats
$stmt = $pdo->prepare(
    "SELECT c.winner, c.opponent_name, c.played_at, u.nom as player_nom
     FROM combats c
     JOIN users u ON u.id = c.player_id
     ORDER BY c.played_at DESC
     LIMIT 5"
);
$stmt->execute();
$derniersCombats = $stmt->fetchAll();

$pageTitle = 'Administration';
require_once '../includes/header.php';
?>

<div class="catalogue-main">

    <!-- Titre -->
    <div class="hero-content" style="padding:1.2rem 2rem; margin-bottom:0">
        <h2 class="hero-title" style="font-size:1.5rem">Espace Administrateur</h2>
        <p class="hero-sub">Gérez le catalogue, les utilisateurs et surveillez l'activité</p>
    </div>

    <!-- ============================================================
         STATISTIQUES GLOBALES
         ============================================================ -->
    <h3 class="section-title">
        Statistiques globales
    </h3>

    <!-- Ligne 1 : utilisateurs -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?= $totalUsers ?></span>
            <span class="stat-label">Utilisateurs total</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:#2ecc40"><?= $totalJoueurs ?></span>
            <span class="stat-label">Joueurs</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:#3a7aff"><?= $totalVisiteurs ?></span>
            <span class="stat-label">Visiteurs</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $totalCartes ?></span>
            <span class="stat-label">Cartes dans le catalogue</span>
        </div>
    </div>

    <!-- Ligne 2 : activité -->
    <div class="stats-grid" style="margin-top:1rem">
        <div class="stat-card">
            <span class="stat-number"><?= $totalCombats ?></span>
            <span class="stat-label">Combats joués</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $totalAcquisitions ?></span>
            <span class="stat-label">Cartes achetées (total)</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $totalDecks ?></span>
            <span class="stat-label">Decks créés</span>
        </div>
        <div class="stat-card">
            <span class="stat-number">
                <?= $totalJoueurs > 0 ? round($totalAcquisitions / $totalJoueurs, 1) : 0 ?>
            </span>
            <span class="stat-label">Cartes / joueur (moy.)</span>
        </div>
    </div>

    <!-- Répartition des raretés dans les collections -->
    <?php if (!empty($raretesStats)): ?>
        <h3 class="section-title">
            Raretés les plus collectionnées
        </h3>
        <div style="display:flex; gap:0.8rem; flex-wrap:wrap">
            <?php
            $rarityColors = [
                'commune'=>'#8a8a8a','peu-commune'=>'#2ecc40','rare'=>'#3a7aff',
                'epique'=>'#9b59b6','legendaire'=>'#ff8c00','mythique'=>'#ff2050'
            ];
            $rarityLabels = [
                'commune'=>'Commune','peu-commune'=>'Peu commune','rare'=>'Rare',
                'epique'=>'Épique','legendaire'=>'Légendaire','mythique'=>'Mythique'
            ];
            foreach ($raretesStats as $r):
                $col = $rarityColors[$r['rarity']] ?? '#c9a227';
                $lab = $rarityLabels[$r['rarity']] ?? $r['rarity'];
            ?>
                <div style="
                    border:1px solid <?= $col ?>; border-radius:4px;
                    padding:0.5rem 1rem; background:rgba(0,0,0,0.3);
                    font-family:'Cinzel',serif; font-size:0.8rem">
                    <span style="color:<?= $col ?>"><?= $lab ?></span>
                    <span style="color:var(--or-texte); margin-left:0.5rem"><?= $r['nb'] ?> possédées</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <!-- ============================================================
         DERNIERS INSCRITS
         ============================================================ -->
    <h3 class="section-title">
        Derniers utilisateurs inscrits
    </h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th>
                <th>Points</th><th>Cartes</th><th>Inscrit le</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($derniersInscrits as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['nom']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span style="font-family:'Cinzel',serif; font-size:0.75rem; padding:0.15rem 0.6rem;
                        border-radius:3px; background:rgba(0,0,0,0.3); border:1px solid var(--or-bord);
                        color:var(--or-texte)">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td><?= number_format($u['points'], 0, ',', ' ') ?></td>
                <td><?= $u['nb_cartes'] ?></td>
                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <a href="users.php" class="btn-small">Gérer</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>


    <!-- ============================================================
         DERNIERS COMBATS
         ============================================================ -->
    <?php if (!empty($derniersCombats)): ?>
        <h3 class="section-title">
            Derniers combats joués
        </h3>
        <table class="data-table">
            <thead>
                <tr><th>Joueur</th><th>Adversaire</th><th>Résultat</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($derniersCombats as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['player_nom']) ?></td>
                    <td><?= htmlspecialchars($c['opponent_name']) ?></td>
                    <td>
                        <?php
                        $wColors = ['player'=>'#2ecc40','opponent'=>'#ff4444','draw'=>'#ff8c00'];
                        $wLabels = ['player'=>'Victoire','opponent'=>'Défaite','draw'=>'Nul'];
                        $wColor  = $wColors[$c['winner']] ?? '#888';
                        $wLabel  = $wLabels[$c['winner']] ?? '?';
                        ?>
                        <span style="color:<?= $wColor ?>; font-weight:600"><?= $wLabel ?></span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($c['played_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>


    <!-- ============================================================
         LIENS DE NAVIGATION ADMIN
         ============================================================ -->
    <div style="display:flex; gap:1rem; margin-top:2rem; flex-wrap:wrap">
        <a href="cards.php" class="btn-hero">Gérer les cartes (CRUD)</a>
        <a href="users.php" class="btn-hero">Gérer les utilisateurs</a>
        <a href="../catalogue.php" class="btn-hero">Voir le catalogue</a>
        <a href="../dashboard.php" class="btn-hero">Tableau de bord</a>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
