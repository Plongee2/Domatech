<?php
// ============================================================
// amis.php — Système d'amis entre joueurs
//
// Réservé aux joueurs connectés.
//
// Fonctionnalités :
//   - Voir sa liste d'amis acceptés
//   - Envoyer une demande d'amis (par email ou nom)
//   - Accepter / refuser les demandes reçues
//   - Supprimer un ami
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'joueur') {
    header("Location: dashboard.php");
    exit;
}

$userId  = $_SESSION['user_id'];
$message = '';
$erreur  = '';

// ================================================================
// ACTIONS POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Envoyer une demande d'amis ----
    if ($action === 'send_request') {
        $search = trim($_POST['friend_search'] ?? '');

        if (empty($search)) {
            $erreur = "Entrez un nom ou un email.";
        } else {
            // Cherche l'utilisateur par nom ou email
            $stmt = $pdo->prepare(
                "SELECT id, nom FROM users WHERE (nom = ? OR email = ?) AND id != ? AND role = 'joueur'"
            );
            $stmt->execute([$search, $search, $userId]);
            $target = $stmt->fetch();

            if (!$target) {
                $erreur = "Joueur introuvable. Vérifiez le nom ou l'email.";
            } else {
                // Vérifie qu'une relation n'existe pas déjà
                $stmt = $pdo->prepare(
                    "SELECT id FROM friends
                     WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)"
                );
                $stmt->execute([$userId, $target['id'], $target['id'], $userId]);
                if ($stmt->fetch()) {
                    $erreur = "Une relation existe déjà avec ce joueur.";
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')"
                    );
                    $stmt->execute([$userId, $target['id']]);
                    $message = "Demande envoyée à " . htmlspecialchars($target['nom']) . " !";
                }
            }
        }
    }

    // ---- Accepter une demande ----
    elseif ($action === 'accept') {
        $friendshipId = (int)($_POST['friendship_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?"
        );
        $stmt->execute([$friendshipId, $userId]);
        $message = "Demande acceptée !";
    }

    // ---- Refuser / supprimer une relation ----
    elseif ($action === 'remove') {
        $friendId = (int)($_POST['friend_id'] ?? 0);
        $stmt = $pdo->prepare(
            "DELETE FROM friends
             WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)"
        );
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
        $message = "Ami retiré.";
    }

    header("Location: amis.php" . (!empty($message) ? "?msg=".urlencode($message) : "") . (!empty($erreur) ? "?err=".urlencode($erreur) : ""));
    exit;
}

if (isset($_GET['msg'])) $message = htmlspecialchars(urldecode($_GET['msg']));
if (isset($_GET['err'])) $erreur  = htmlspecialchars(urldecode($_GET['err']));

// ================================================================
// RÉCUPÈRE LES DONNÉES
// ================================================================

// Amis acceptés
$stmt = $pdo->prepare(
    "SELECT u.id, u.nom, u.points, f.id as friendship_id,
            COUNT(uc.id) as nb_cartes
     FROM friends f
     JOIN users u ON (u.id = CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END)
     LEFT JOIN user_cards uc ON uc.user_id = u.id
     WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'
     GROUP BY u.id, f.id
     ORDER BY u.nom ASC"
);
$stmt->execute([$userId, $userId, $userId]);
$friends = $stmt->fetchAll();

// Demandes reçues (en attente)
$stmt = $pdo->prepare(
    "SELECT f.id as friendship_id, u.id as sender_id, u.nom as sender_nom
     FROM friends f
     JOIN users u ON u.id = f.user_id
     WHERE f.friend_id = ? AND f.status = 'pending'
     ORDER BY f.created_at DESC"
);
$stmt->execute([$userId]);
$pendingReceived = $stmt->fetchAll();

// Demandes envoyées (en attente)
$stmt = $pdo->prepare(
    "SELECT f.id, u.nom as target_nom, f.created_at
     FROM friends f
     JOIN users u ON u.id = f.friend_id
     WHERE f.user_id = ? AND f.status = 'pending'
     ORDER BY f.created_at DESC"
);
$stmt->execute([$userId]);
$pendingSent = $stmt->fetchAll();

$pageTitle = 'Mes Amis';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="catalogue-main">

    <h2 class="form-title">Mes Amis</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- Formulaire d'ajout d'ami -->
    <div style="background:linear-gradient(180deg,#1a2040 0%,#0b0d1a 100%);
                border:2px solid var(--or-bord); border-radius:6px; padding:1.2rem 1.5rem; margin-bottom:1.5rem">
        <h3 style="font-family:'Cinzel',serif; color:var(--or-eclat); margin-bottom:0.8rem; font-size:0.9rem">
            Ajouter un ami
        </h3>
        <form method="POST" style="display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap">
            <input type="hidden" name="action" value="send_request">
            <input type="text" name="friend_search" class="search-input" style="max-width:300px"
                   placeholder="Nom ou email du joueur" required>
            <button type="submit" class="btn-hero" style="white-space:nowrap">Envoyer une demande</button>
        </form>
    </div>

    <!-- Demandes reçues -->
    <?php if (!empty($pendingReceived)): ?>
        <h3 style="font-family:'Cinzel',serif; color:#ff8c00; margin-bottom:0.8rem">
            Demandes reçues (<?= count($pendingReceived) ?>)
        </h3>
        <div style="display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.5rem">
            <?php foreach ($pendingReceived as $req): ?>
                <div style="display:flex; align-items:center; gap:1rem; padding:0.8rem 1rem;
                            background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px">
                    <span style="flex:1; font-family:'Cinzel',serif; font-size:0.9rem; color:var(--or-texte)">
                        <?= htmlspecialchars($req['sender_nom']) ?>
                    </span>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action"        value="accept">
                        <input type="hidden" name="friendship_id" value="<?= $req['friendship_id'] ?>">
                        <button type="submit" class="btn-small" style="background:linear-gradient(180deg,#1a4020 0%,#0a2010 100%); border-color:#2ecc40; color:#2ecc40">
                            Accepter
                        </button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action"    value="remove">
                        <input type="hidden" name="friend_id" value="<?= $req['sender_id'] ?>">
                        <button type="submit" class="btn-small btn-danger">Refuser</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Liste des amis -->
    <h3 style="font-family:'Cinzel',serif; color:var(--or-eclat); margin-bottom:0.8rem">
        Mes amis (<?= count($friends) ?>)
    </h3>

    <?php if (empty($friends)): ?>
        <div class="hero-visual">
            <p>Vous n'avez pas encore d'amis.<br>Envoyez une demande en entrant le nom ou l'email d'un joueur !</p>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:0.8rem">
            <?php foreach ($friends as $friend): ?>
                <div style="background:linear-gradient(180deg,#1a2040 0%,#0b0d1a 100%);
                            border:2px solid var(--or-bord); border-radius:6px; padding:1rem">
                    <div style="font-family:'Cinzel',serif; font-size:0.95rem; color:var(--or-eclat); margin-bottom:0.5rem">
                        <?= htmlspecialchars($friend['nom']) ?>
                    </div>
                    <div style="font-size:0.85rem; color:var(--or-texte); line-height:1.8">
                        <span style="color:var(--or-cadre)"><?= $friend['nb_cartes'] ?></span> cartes<br>
                        <span style="color:var(--or-cadre)"><?= number_format($friend['points'], 0, ',', ' ') ?></span> points
                    </div>
                    <form method="POST" style="margin-top:0.8rem"
                          onsubmit="return confirm('Retirer <?= htmlspecialchars($friend['nom'], ENT_QUOTES) ?> de vos amis ?')">
                        <input type="hidden" name="action"    value="remove">
                        <input type="hidden" name="friend_id" value="<?= $friend['id'] ?>">
                        <button type="submit" class="btn-small btn-danger" style="font-size:0.7rem">
                            Retirer
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Demandes envoyées -->
    <?php if (!empty($pendingSent)): ?>
        <h3 style="font-family:'Cinzel',serif; color:#666; margin-top:1.5rem; margin-bottom:0.8rem; font-size:0.9rem">
            Demandes envoyées en attente (<?= count($pendingSent) ?>)
        </h3>
        <div style="display:flex; flex-direction:column; gap:0.5rem">
            <?php foreach ($pendingSent as $req): ?>
                <div style="display:flex; align-items:center; gap:1rem; padding:0.6rem 1rem;
                            background:rgba(0,0,0,0.2); border:1px solid #333; border-radius:4px; opacity:0.7">
                    <span style="flex:1; font-size:0.9rem; color:#888">
                        En attente : <?= htmlspecialchars($req['target_nom']) ?>
                    </span>
                    <span style="font-size:0.75rem; color:#555">
                        <?= date('d/m/Y', strtotime($req['created_at'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
