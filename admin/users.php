<?php
// ============================================================
// admin/users.php — Gestion des utilisateurs
//
// Réservé au rôle 'admin'.
//
// Fonctionnalités :
//   - Liste tous les utilisateurs avec leurs statistiques
//   - Modification du rôle d'un utilisateur
//   - Ajout/retrait de points manuellement
//   - Voir la collection d'un utilisateur (en modal)
//   - Suppression d'un compte (avec confirmation, sauf son propre compte)
// ============================================================
session_start();
$rootPath = '../';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Protection : seul l'admin peut accéder ici
requireRole('admin', '../');

$message = '';
$erreur  = '';

// ================================================================
// ACTIONS POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Changer le rôle d'un utilisateur ----
    if ($action === 'change_role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole  = trim($_POST['role'] ?? '');

        if ($targetId === $_SESSION['user_id']) {
            $erreur = "Vous ne pouvez pas changer votre propre rôle.";
        } elseif (!in_array($newRole, ['admin','joueur','visiteur'], true)) {
            $erreur = "Rôle invalide.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $targetId]);
            $message = "Rôle mis à jour.";
        }
    }

    // ---- Modifier les points d'un utilisateur ----
    elseif ($action === 'update_points') {
        $targetId  = (int)($_POST['user_id'] ?? 0);
        $newPoints = (int)($_POST['points']  ?? 0);

        if ($newPoints < 0) $newPoints = 0;

        $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->execute([$newPoints, $targetId]);
        $message = "Points mis à jour.";
    }

    // Redirige pour éviter la re-soumission
    header("Location: users.php" . (!empty($message) ? "?msg=" . urlencode($message) : "") . (!empty($erreur) ? "?err=" . urlencode($erreur) : ""));
    exit;
}

// ---- Suppression (GET) ----
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id === $_SESSION['user_id']) {
        header("Location: users.php?err=" . urlencode("Vous ne pouvez pas supprimer votre propre compte."));
        exit;
    }

    // Récupère le nom avant suppression
    $stmt = $pdo->prepare("SELECT nom FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $userToDelete = $stmt->fetch();

    if ($userToDelete) {
        // Les user_cards, decks, combats seront supprimés en cascade
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: users.php?msg=" . urlencode("Utilisateur \"" . $userToDelete['nom'] . "\" supprimé."));
    } else {
        header("Location: users.php?err=Utilisateur+introuvable.");
    }
    exit;
}

// Récupère les messages depuis l'URL
if (isset($_GET['msg'])) $message = htmlspecialchars(urldecode($_GET['msg']));
if (isset($_GET['err'])) $erreur  = htmlspecialchars(urldecode($_GET['err']));

// ================================================================
// RÉCUPÈRE LA COLLECTION D'UN UTILISATEUR (pour le modal)
// ================================================================
$viewUserId      = (int)($_GET['view_collection'] ?? 0);
$userCollection  = [];
$viewUserNom     = '';
if ($viewUserId > 0) {
    $stmt = $pdo->prepare("SELECT nom FROM users WHERE id = ?");
    $stmt->execute([$viewUserId]);
    $viewUser = $stmt->fetch();
    if ($viewUser) {
        $viewUserNom = $viewUser['nom'];
        $stmt = $pdo->prepare(
            "SELECT c.name, c.rarity, c.type, c.nation, uc.acquired_at
             FROM user_cards uc
             JOIN cards c ON c.id = uc.card_id
             WHERE uc.user_id = ?
             ORDER BY FIELD(c.rarity,'mythique','legendaire','epique','rare','peu-commune','commune'), c.name ASC"
        );
        $stmt->execute([$viewUserId]);
        $userCollection = $stmt->fetchAll();
    }
}

// ================================================================
// LISTE DES UTILISATEURS avec leurs stats
// ================================================================
$searchUser = trim($_GET['q'] ?? '');

if (!empty($searchUser)) {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nom, u.email, u.role, u.points, u.created_at,
                COUNT(uc.id) as nb_cartes,
                COUNT(DISTINCT d.id) as nb_decks,
                COUNT(DISTINCT c.id) as nb_combats
         FROM users u
         LEFT JOIN user_cards uc ON uc.user_id = u.id
         LEFT JOIN decks d ON d.user_id = u.id
         LEFT JOIN combats c ON c.player_id = u.id
         WHERE u.nom LIKE ? OR u.email LIKE ?
         GROUP BY u.id
         ORDER BY u.created_at DESC"
    );
    $stmt->execute(['%'.$searchUser.'%', '%'.$searchUser.'%']);
} else {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nom, u.email, u.role, u.points, u.created_at,
                COUNT(uc.id) as nb_cartes,
                COUNT(DISTINCT d.id) as nb_decks,
                COUNT(DISTINCT c.id) as nb_combats
         FROM users u
         LEFT JOIN user_cards uc ON uc.user_id = u.id
         LEFT JOIN decks d ON d.user_id = u.id
         LEFT JOIN combats c ON c.player_id = u.id
         GROUP BY u.id
         ORDER BY u.created_at DESC"
    );
    $stmt->execute();
}
$users = $stmt->fetchAll();

// Stats globales
$totalUsers     = count($users);
$totalJoueurs   = count(array_filter($users, fn($u) => $u['role'] === 'joueur'));
$totalVisiteurs = count(array_filter($users, fn($u) => $u['role'] === 'visiteur'));
$totalAdmins    = count(array_filter($users, fn($u) => $u['role'] === 'admin'));

$pageTitle = 'Gestion des utilisateurs';
require_once '../includes/header.php';
?>

<div class="catalogue-main">

    <h2 class="form-title">
        Gestion des Utilisateurs
        <small style="font-size:0.55em; color:var(--or-texte)"> — Espace Administrateur</small>
    </h2>

    <!-- Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- ============================================================
         MODAL COLLECTION D'UN UTILISATEUR
         ============================================================ -->
    <?php if ($viewUserId > 0 && !empty($viewUserNom)): ?>
        <div style="
            background:linear-gradient(180deg,#1a2040 0%,#0b0d1a 100%);
            border:2px solid var(--or-cadre); border-radius:6px; padding:1.5rem;
            margin-bottom:1.5rem">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
                <h3 style="font-family:'Cinzel',serif; color:var(--or-eclat)">
                    Collection de <?= htmlspecialchars($viewUserNom) ?>
                    (<?= count($userCollection) ?> cartes)
                </h3>
                <a href="users.php" class="btn-small">Fermer</a>
            </div>

            <?php if (empty($userCollection)): ?>
                <p style="color:var(--or-texte); font-style:italic">Aucune carte dans cette collection.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>Carte</th><th>Rareté</th><th>Type</th><th>Nation</th><th>Acquise le</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($userCollection as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['rarity']) ?></td>
                            <td><?= htmlspecialchars($c['type']) ?></td>
                            <td><?= htmlspecialchars($c['nation']) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['acquired_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Stats rapides -->
    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <span class="stat-number"><?= $totalUsers ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:#ff8c00"><?= $totalAdmins ?></span>
            <span class="stat-label">Admins</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:#2ecc40"><?= $totalJoueurs ?></span>
            <span class="stat-label">Joueurs</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:#3a7aff"><?= $totalVisiteurs ?></span>
            <span class="stat-label">Visiteurs</span>
        </div>
    </div>

    <!-- Recherche -->
    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem">
        <form method="GET" action="users.php" style="display:flex; gap:0.5rem">
            <input type="text" name="q" class="search-input" style="max-width:250px"
                   placeholder="Rechercher par nom ou email..."
                   value="<?= htmlspecialchars($searchUser) ?>">
            <button type="submit" class="btn-small">Chercher</button>
            <?php if (!empty($searchUser)): ?>
                <a href="users.php" class="btn-small">Tout</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tableau des utilisateurs -->
    <?php if (empty($users)): ?>
        <p class="empty-msg">Aucun utilisateur trouvé.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th>
                    <th>Points</th><th>Cartes</th><th>Decks</th><th>Combats</th>
                    <th>Inscrit le</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td style="color:#666"><?= $user['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($user['nom']) ?></strong>
                        <?php if ($user['id'] === $_SESSION['user_id']): ?>
                            <span style="font-size:0.7rem; color:var(--or-cadre); margin-left:0.3rem">(vous)</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.85rem"><?= htmlspecialchars($user['email']) ?></td>

                    <!-- Modification du rôle en inline -->
                    <td>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"  value="change_role">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="role" onchange="this.form.submit()"
                                        style="font-family:'Cinzel',serif; font-size:0.72rem;
                                               background:rgba(0,0,0,0.4); border:1px solid var(--or-bord);
                                               border-radius:3px; color:var(--or-texte); padding:0.2rem 0.4rem">
                                    <option value="admin"    <?= $user['role']==='admin'    ? 'selected' : '' ?>>Admin</option>
                                    <option value="joueur"   <?= $user['role']==='joueur'   ? 'selected' : '' ?>>Joueur</option>
                                    <option value="visiteur" <?= $user['role']==='visiteur' ? 'selected' : '' ?>>Visiteur</option>
                                </select>
                            </form>
                        <?php else: ?>
                            <span style="font-family:'Cinzel',serif; font-size:0.75rem; color:var(--or-cadre)">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Modification des points en inline -->
                    <td>
                        <form method="POST" style="display:flex; align-items:center; gap:0.3rem">
                            <input type="hidden" name="action"  value="update_points">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="number" name="points"
                                   value="<?= $user['points'] ?>"
                                   min="0" max="999999"
                                   style="width:70px; font-size:0.8rem; background:rgba(0,0,0,0.3);
                                          border:1px solid var(--or-bord); border-radius:3px;
                                          color:var(--or-eclat); padding:0.2rem 0.4rem; text-align:right">
                            <button type="submit" class="btn-small" style="padding:0.15rem 0.5rem; font-size:0.7rem">
                                OK
                            </button>
                        </form>
                    </td>

                    <td>
                        <?php if ($user['nb_cartes'] > 0): ?>
                            <a href="users.php?view_collection=<?= $user['id'] ?>#collection"
                               class="btn-small" style="font-size:0.7rem">
                                <?= $user['nb_cartes'] ?> cartes
                            </a>
                        <?php else: ?>
                            <span style="color:#555">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user['nb_decks'] ?></td>
                    <td><?= $user['nb_combats'] ?></td>
                    <td style="font-size:0.8rem"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>

                    <!-- Actions -->
                    <td style="white-space:nowrap">
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <a href="users.php?action=delete&id=<?= $user['id'] ?>"
                               class="btn-small btn-danger"
                               onclick="return confirm('Supprimer l\'utilisateur &laquo;<?= htmlspecialchars($user['nom'], ENT_QUOTES) ?>&raquo; ?\n\nToutes ses cartes, decks et combats seront supprimés.')">
                                Supprimer
                            </a>
                        <?php else: ?>
                            <span style="color:#555; font-size:0.8rem; font-style:italic">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="color:var(--or-texte); font-size:0.85rem; margin-top:0.5rem">
            <?= $totalUsers ?> utilisateur<?= $totalUsers > 1 ? 's' : '' ?>
            <?= !empty($searchUser) ? 'trouvé' . ($totalUsers > 1 ? 's' : '') . ' pour "' . htmlspecialchars($searchUser) . '"' : 'au total' ?>
        </p>

    <?php endif; ?>

    <!-- Navigation admin -->
    <div style="display:flex; gap:1rem; margin-top:2rem; flex-wrap:wrap">
        <a href="index.php"  class="btn-hero">Dashboard Admin</a>
        <a href="cards.php"  class="btn-hero">Gérer les cartes</a>
        <a href="../catalogue.php" class="btn-hero">Voir le catalogue</a>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
