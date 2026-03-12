<?php
// ============================================================
// collection.php — Ma collection de cartes + Deck Builder
//
// Réservé aux joueurs connectés.
//
// Fonctionnalités :
//   - Affiche toutes les cartes possédées (triées par rareté)
//   - Deck Builder : créer, renommer, supprimer des decks (max 25 cartes)
//   - Ajouter / retirer des cartes du deck actif
//   - Modal de détail au clic sur une carte
//   - Stats de la collection
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Page réservée aux joueurs
requireLogin();
if ($_SESSION['role'] !== 'joueur') {
    header("Location: dashboard.php");
    exit;
}

$userId  = $_SESSION['user_id'];
$message = '';
$erreur  = '';

// ================================================================
// ACTIONS POST (deck builder)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Créer un nouveau deck ---
    if ($action === 'create_deck') {
        $deckName = trim($_POST['deck_name'] ?? 'Nouveau Deck');
        $deckName = substr($deckName, 0, 50);
        if (empty($deckName)) $deckName = 'Nouveau Deck';

        $stmt = $pdo->prepare("INSERT INTO decks (user_id, name) VALUES (?, ?)");
        $stmt->execute([$userId, $deckName]);
        $message = "Deck \"" . htmlspecialchars($deckName) . "\" créé !";
    }

    // --- Renommer un deck ---
    elseif ($action === 'rename_deck') {
        $deckId   = (int)($_POST['deck_id'] ?? 0);
        $deckName = trim($_POST['deck_name'] ?? '');
        $deckName = substr($deckName, 0, 50);

        // Vérifie que ce deck appartient bien au joueur
        $stmt = $pdo->prepare("SELECT id FROM decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $userId]);
        if ($stmt->fetch() && !empty($deckName)) {
            $stmt = $pdo->prepare("UPDATE decks SET name = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$deckName, $deckId, $userId]);
            $message = "Deck renommé en \"" . htmlspecialchars($deckName) . "\" !";
        }
    }

    // --- Supprimer un deck ---
    elseif ($action === 'delete_deck') {
        $deckId = (int)($_POST['deck_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT id FROM decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $userId]);
        if ($stmt->fetch()) {
            // Les deck_cards sont supprimées en cascade (ON DELETE CASCADE dans le SQL)
            $stmt = $pdo->prepare("DELETE FROM decks WHERE id = ? AND user_id = ?");
            $stmt->execute([$deckId, $userId]);
            $message = "Deck supprimé.";
        }
    }

    // --- Ajouter une carte au deck ---
    elseif ($action === 'add_to_deck') {
        $deckId = (int)($_POST['deck_id'] ?? 0);
        $cardId = (int)($_POST['card_id'] ?? 0);

        // Vérifie que le deck appartient au joueur
        $stmt = $pdo->prepare("SELECT id FROM decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $userId]);
        if (!$stmt->fetch()) {
            $erreur = "Deck invalide.";
        } else {
            // Vérifie que le joueur possède la carte
            $stmt = $pdo->prepare("SELECT id FROM user_cards WHERE user_id = ? AND card_id = ?");
            $stmt->execute([$userId, $cardId]);
            if (!$stmt->fetch()) {
                $erreur = "Vous ne possédez pas cette carte.";
            } else {
                // Vérifie que la carte n'est pas déjà dans le deck
                $stmt = $pdo->prepare("SELECT id FROM deck_cards WHERE deck_id = ? AND card_id = ?");
                $stmt->execute([$deckId, $cardId]);
                if ($stmt->fetch()) {
                    $erreur = "Cette carte est déjà dans le deck.";
                } else {
                    // Vérifie le nombre de cartes dans le deck (max 25)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM deck_cards WHERE deck_id = ?");
                    $stmt->execute([$deckId]);
                    if ((int)$stmt->fetchColumn() >= 25) {
                        $erreur = "Le deck est plein (25 cartes max).";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO deck_cards (deck_id, card_id) VALUES (?, ?)");
                        $stmt->execute([$deckId, $cardId]);
                        $message = "Carte ajoutée au deck !";
                    }
                }
            }
        }
    }

    // --- Retirer une carte du deck ---
    elseif ($action === 'remove_from_deck') {
        $deckId = (int)($_POST['deck_id'] ?? 0);
        $cardId = (int)($_POST['card_id'] ?? 0);

        // Vérifie que le deck appartient au joueur avant de supprimer
        $stmt = $pdo->prepare("SELECT id FROM decks WHERE id = ? AND user_id = ?");
        $stmt->execute([$deckId, $userId]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM deck_cards WHERE deck_id = ? AND card_id = ?");
            $stmt->execute([$deckId, $cardId]);
            $message = "Carte retirée du deck.";
        }
    }

    // Redirige pour éviter la re-soumission du formulaire (pattern PRG)
    $redirect = "collection.php";
    if (!empty($_POST['deck_id'])) $redirect .= "?deck=" . (int)$_POST['deck_id'];
    $msgParam = !empty($message) ? "&msg=" . urlencode($message) : "";
    $errParam = !empty($erreur)  ? "&err=" . urlencode($erreur)  : "";
    header("Location: $redirect$msgParam$errParam");
    exit;
}

// Récupère les messages depuis l'URL (après redirection)
if (isset($_GET['msg'])) $message = htmlspecialchars(urldecode($_GET['msg']));
if (isset($_GET['err'])) $erreur  = htmlspecialchars(urldecode($_GET['err']));

// ================================================================
// DECK ACTIF (sélectionné via ?deck=ID ou le premier par défaut)
// ================================================================
$activeDeckId = (int)($_GET['deck'] ?? 0);

// Récupère tous les decks du joueur
$stmt = $pdo->prepare("SELECT * FROM decks WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$decks = $stmt->fetchAll();

// Si aucun deck actif sélectionné, prend le premier disponible
if ($activeDeckId === 0 && !empty($decks)) {
    $activeDeckId = (int)$decks[0]['id'];
}

// Cartes dans le deck actif
$deckCards = [];
if ($activeDeckId > 0) {
    $stmt = $pdo->prepare(
        "SELECT dc.card_id, c.name, c.rarity, c.type, c.nation
         FROM deck_cards dc
         JOIN cards c ON c.id = dc.card_id
         WHERE dc.deck_id = ?
         ORDER BY c.rarity DESC, c.name ASC"
    );
    $stmt->execute([$activeDeckId]);
    $deckCards = $stmt->fetchAll();
}
$deckCardIds = array_column($deckCards, 'card_id');

// ================================================================
// COLLECTION DU JOUEUR
// ================================================================
// Ordre de rareté : mythique en premier
$orderRarity = "FIELD(c.rarity, 'mythique','legendaire','epique','rare','peu-commune','commune')";
$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.rarity, c.type, c.nation,
            c.hp, c.attack, c.defense, c.speed,
            c.special, c.description, c.price,
            uc.acquired_at
     FROM user_cards uc
     JOIN cards c ON c.id = uc.card_id
     WHERE uc.user_id = ?
     ORDER BY $orderRarity, c.name ASC"
);
$stmt->execute([$userId]);
$collection = $stmt->fetchAll();

// Nombre total de cartes dans le jeu
$totalCards = count_query($pdo, "SELECT COUNT(*) FROM cards");

$pageTitle = 'Ma Collection';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="catalogue-main">

    <h2 class="form-title">Ma Collection</h2>

    <!-- Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- Stats de la collection -->
    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <span class="stat-number"><?= count($collection) ?> / <?= $totalCards ?></span>
            <span class="stat-label">Cartes collectionnées</span>
        </div>
        <div class="stat-card">
            <span class="stat-number">
                <?= $totalCards > 0 ? round(count($collection) / $totalCards * 100) : 0 ?>%
            </span>
            <span class="stat-label">Complétion</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= count($decks) ?></span>
            <span class="stat-label">Decks</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color:var(--or-eclat)"><?= number_format($_SESSION['points'] ?? 0, 0, ',', ' ') ?></span>
            <span class="stat-label">Points disponibles</span>
        </div>
    </div>

    <?php if (empty($collection)): ?>
        <div class="hero-visual">
            <p>Votre collection est vide.<br>
               <a href="catalogue.php">Visitez le catalogue pour acquérir vos premières cartes !</a></p>
        </div>
    <?php else: ?>

        <!-- ============================================================
             GRILLE DE LA COLLECTION
             ============================================================ -->
        <h3 class="section-title" style="font-family:'Cinzel',serif; color:var(--or-eclat); margin-bottom:0.8rem">
            Mes cartes (<?= count($collection) ?>)
        </h3>

        <!-- Sélecteur de deck actif pour ajouter des cartes -->
        <?php if (!empty($decks)): ?>
            <form method="POST" style="margin-bottom:1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap">
                <span style="font-family:'Cinzel',serif; font-size:0.8rem; color:var(--or-cadre)">Deck actif :</span>
                <select name="deck_id" class="deck-select" id="active-deck-select" style="max-width:250px"
                        onchange="window.location='collection.php?deck='+this.value">
                    <?php foreach ($decks as $deck): ?>
                        <option value="<?= $deck['id'] ?>" <?= $deck['id'] == $activeDeckId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($deck['name']) ?>
                            (<?= count($deckCards) ?> cartes)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:0.85rem; color:var(--or-texte)">
                    Cliquez sur une carte pour l'ajouter/retirer du deck actif
                </span>
            </form>
        <?php else: ?>
            <p style="font-family:'Cinzel',serif; font-size:0.85rem; color:var(--or-cadre); margin-bottom:1rem">
                Créez d'abord un deck ci-dessous pour y ajouter des cartes.
            </p>
        <?php endif; ?>

        <!-- Grille de cartes -->
        <div class="cards-grid-catalogue">
            <?php foreach ($collection as $card): ?>
                <?php $inDeck = in_array($card['id'], $deckCardIds); ?>
                <div class="card-item rarity-<?= htmlspecialchars($card['rarity']) ?> type-<?= htmlspecialchars($card['type']) ?> <?= $inDeck ? 'in-deck' : '' ?>"
                     onclick="openCardModal(<?= $card['id'] ?>)"
                     title="<?= htmlspecialchars($card['name']) ?> — Cliquer pour voir les détails">

                    <div class="card-image"></div>
                    <div class="card-name"><?= htmlspecialchars($card['name']) ?></div>

                    <?php if ($inDeck): ?>
                        <span class="card-indeck-badge">Dans le deck</span>
                    <?php endif; ?>

                    <!-- Boutons ajouter/retirer du deck (si un deck actif existe) -->
                    <?php if ($activeDeckId > 0): ?>
                        <div onclick="event.stopPropagation()">
                            <?php if ($inDeck): ?>
                                <form method="POST">
                                    <input type="hidden" name="action"  value="remove_from_deck">
                                    <input type="hidden" name="deck_id" value="<?= $activeDeckId ?>">
                                    <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                    <button type="submit" class="card-buy-btn" style="background:linear-gradient(180deg,#5a1010 0%,#2a0808 100%); border-color:#aa2020; color:#ffaaaa">
                                        Retirer
                                    </button>
                                </form>
                            <?php elseif (count($deckCards) < 25): ?>
                                <form method="POST">
                                    <input type="hidden" name="action"  value="add_to_deck">
                                    <input type="hidden" name="deck_id" value="<?= $activeDeckId ?>">
                                    <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                    <button type="submit" class="card-buy-btn">+ Deck</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>


    <!-- ============================================================
         DECK BUILDER
         ============================================================ -->
    <div class="deck-builder-section">

        <!-- Entête du deck builder -->
        <div class="deck-builder-header">
            <span class="deck-builder-title">Deck Builder</span>

            <div class="deck-builder-controls">
                <!-- Sélecteur de deck -->
                <?php if (!empty($decks)): ?>
                    <select class="deck-select" onchange="window.location='collection.php?deck='+this.value">
                        <?php foreach ($decks as $deck): ?>
                            <option value="<?= $deck['id'] ?>" <?= $deck['id'] == $activeDeckId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($deck['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <!-- Créer un nouveau deck -->
                <button class="deck-new-btn" onclick="toggleNewDeckForm()">+ Nouveau</button>

                <?php if ($activeDeckId > 0): ?>
                    <!-- Renommer le deck actif -->
                    <button class="deck-rename-btn" onclick="toggleRenameDeckForm()">Renommer</button>

                    <!-- Supprimer le deck actif (avec confirmation JS) -->
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Supprimer ce deck ? Les cartes ne seront pas perdues.')">
                        <input type="hidden" name="action"  value="delete_deck">
                        <input type="hidden" name="deck_id" value="<?= $activeDeckId ?>">
                        <button type="submit" class="deck-delete-btn">Supprimer</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Compteur de cartes dans le deck -->
            <?php if ($activeDeckId > 0): ?>
                <span class="deck-count"><?= count($deckCards) ?> / 25</span>
            <?php endif; ?>
        </div>

        <!-- Formulaire de création de deck (caché par défaut) -->
        <div id="new-deck-form" style="display:none; margin-bottom:1rem">
            <form method="POST" style="display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap">
                <input type="hidden" name="action" value="create_deck">
                <input type="text" name="deck_name" class="deck-select"
                       placeholder="Nom du nouveau deck" maxlength="50" style="max-width:250px" required>
                <button type="submit" class="deck-new-btn">Créer</button>
                <button type="button" class="deck-rename-btn" onclick="toggleNewDeckForm()">Annuler</button>
            </form>
        </div>

        <!-- Formulaire de renommage (caché par défaut) -->
        <?php if ($activeDeckId > 0): ?>
            <div id="rename-deck-form" style="display:none; margin-bottom:1rem">
                <?php
                $activeDeckName = '';
                foreach ($decks as $d) {
                    if ($d['id'] == $activeDeckId) {
                        $activeDeckName = $d['name'];
                        break;
                    }
                }
                ?>
                <form method="POST" style="display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap">
                    <input type="hidden" name="action"  value="rename_deck">
                    <input type="hidden" name="deck_id" value="<?= $activeDeckId ?>">
                    <input type="text" name="deck_name" class="deck-select"
                           value="<?= htmlspecialchars($activeDeckName) ?>"
                           maxlength="50" style="max-width:250px" required>
                    <button type="submit" class="deck-new-btn">Enregistrer</button>
                    <button type="button" class="deck-rename-btn" onclick="toggleRenameDeckForm()">Annuler</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Grille 5×5 des emplacements du deck -->
        <?php if ($activeDeckId > 0): ?>
            <div class="deck-slots">
                <?php
                // Affiche les cartes du deck puis les emplacements vides
                for ($i = 0; $i < 25; $i++):
                    if (isset($deckCards[$i])):
                        $dc = $deckCards[$i];
                ?>
                    <div class="deck-slot rarity-<?= htmlspecialchars($dc['rarity']) ?>"
                         style="border:2px solid; border-color:var(--r, var(--or-bord));
                                background:rgba(0,0,0,0.4); font-size:0.5rem; text-align:center;
                                overflow:hidden; padding:0.3rem; cursor:pointer"
                         onclick="openCardModal(<?= $dc['card_id'] ?>)"
                         title="<?= htmlspecialchars($dc['name']) ?>">
                        <div style="font-family:'Cinzel',serif; color:var(--or-texte); line-height:1.2; font-size:0.5rem">
                            <?= htmlspecialchars(strlen($dc['name']) > 18 ? substr($dc['name'], 0, 15) . '...' : $dc['name']) ?>
                        </div>
                    </div>
                <?php
                    else:
                ?>
                    <div class="deck-slot empty" style="
                        aspect-ratio:2/1; border:2px dashed rgba(122,82,0,0.25);
                        border-radius:4px; display:flex; align-items:center; justify-content:center;
                        color:rgba(122,82,0,0.3); font-family:'Cinzel',serif;
                        font-size:0.55rem; text-transform:uppercase; letter-spacing:0.5px">
                        Vide
                    </div>
                <?php
                    endif;
                endfor;
                ?>
            </div>

            <!-- Liste des cartes dans le deck (tableau lisible) -->
            <?php if (!empty($deckCards)): ?>
                <details style="margin-top:1rem">
                    <summary style="font-family:'Cinzel',serif; font-size:0.85rem; color:var(--or-cadre);
                                    cursor:pointer; padding:0.5rem 0">
                        Voir la liste du deck (<?= count($deckCards) ?> cartes)
                    </summary>
                    <table class="data-table" style="margin-top:0.8rem">
                        <thead>
                            <tr><th>Carte</th><th>Rareté</th><th>Type</th><th>Nation</th><th>Retirer</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deckCards as $dc): ?>
                            <tr>
                                <td><?= htmlspecialchars($dc['name']) ?></td>
                                <td><?= htmlspecialchars($dc['rarity']) ?></td>
                                <td><?= htmlspecialchars($dc['type']) ?></td>
                                <td><?= htmlspecialchars($dc['nation']) ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action"  value="remove_from_deck">
                                        <input type="hidden" name="deck_id" value="<?= $activeDeckId ?>">
                                        <input type="hidden" name="card_id" value="<?= $dc['card_id'] ?>">
                                        <button type="submit" class="btn-small btn-danger">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

        <?php else: ?>
            <p style="color:var(--or-texte); font-style:italic; text-align:center; padding:1rem">
                Créez un deck pour commencer à y ajouter des cartes.
            </p>
        <?php endif; ?>

    </div><!-- fin .deck-builder-section -->

</div>


<!-- ================================================================
     MODAL DE DÉTAIL (identique au catalogue)
     ================================================================ -->
<div id="card-modal" style="
    display:none; position:fixed; inset:0; z-index:500;
    background:rgba(0,0,0,0.85); align-items:center; justify-content:center">

    <div id="modal-content" style="
        max-width:480px; width:90%; max-height:90vh; overflow-y:auto;
        background:linear-gradient(180deg, #1a2040 0%, #0b0d1a 100%);
        border:2px solid var(--or-cadre); border-radius:8px; padding:2rem;
        box-shadow:0 0 0 1px var(--or-bord), 0 20px 60px rgba(0,0,0,0.9)">

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem">
            <button onclick="closeCardModal()" style="
                background:none; border:1px solid var(--or-bord); color:var(--or-texte);
                padding:0.3rem 0.8rem; border-radius:4px; cursor:pointer; font-family:'Cinzel',serif">
                ✕ Fermer
            </button>
        </div>
        <div id="modal-body"></div>
    </div>
</div>

<script>
// Données JSON de toutes les cartes (injectées depuis PHP)
const CARDS_DATA = <?php
    $stmtAll = $pdo->prepare("SELECT * FROM cards ORDER BY id");
    $stmtAll->execute();
    $allCards = $stmtAll->fetchAll();
    $cardsById = [];
    foreach ($allCards as $c) { $cardsById[(int)$c['id']] = $c; }
    echo json_encode($cardsById, JSON_UNESCAPED_UNICODE);
?>;

// IDs des cartes dans le deck actif
const DECK_CARD_IDS = <?= json_encode($deckCardIds) ?>;

function openCardModal(cardId) {
    const card = CARDS_DATA[cardId];
    if (!card) return;

    const inDeck = DECK_CARD_IDS.includes(parseInt(cardId));
    const rarityColors = {
        'commune':'#8a8a8a','peu-commune':'#2ecc40','rare':'#3a7aff',
        'epique':'#9b59b6','legendaire':'#ff8c00','mythique':'#ff2050'
    };
    const rarityLabels = {
        'commune':'Commune','peu-commune':'Peu commune','rare':'Rare',
        'epique':'Épique','legendaire':'Légendaire','mythique':'Mythique'
    };
    const nationLabels = {
        'bonta':'Bonta','brakmar':'Brakmar','astrub':'Astrub','amakna':'Amakna','frigost':'Frigost'
    };
    const color = rarityColors[card.rarity] || '#c9a227';

    document.getElementById('modal-body').innerHTML = `
        <div style="text-align:center; margin-bottom:1.5rem">
            <div style="
                width:80px; height:107px; margin:0 auto 1rem;
                border:2px solid ${color}; border-radius:4px;
                background:rgba(0,0,0,0.4);
                display:flex; align-items:center; justify-content:center;
                font-size:2rem">
                ${card.type === 'personnage' ? '⚔️' : card.type === 'equipement' ? '🛡️' : '🏰'}
            </div>
            <h3 style="font-family:'Cinzel',serif; font-size:1.2rem; color:#f5d060; letter-spacing:2px; text-transform:uppercase">
                ${card.name}
            </h3>
            <div style="display:flex; gap:0.5rem; justify-content:center; margin-top:0.5rem; flex-wrap:wrap">
                <span style="font-size:0.75rem; padding:0.2rem 0.7rem; border-radius:3px;
                    background:rgba(0,0,0,0.4); border:1px solid ${color}; color:${color}">
                    ${rarityLabels[card.rarity] || card.rarity}
                </span>
                <span style="font-size:0.75rem; padding:0.2rem 0.7rem; border-radius:3px;
                    background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); color:var(--or-texte)">
                    ${card.type.charAt(0).toUpperCase() + card.type.slice(1)}
                </span>
                <span style="font-size:0.75rem; padding:0.2rem 0.7rem; border-radius:3px;
                    background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); color:var(--or-texte)">
                    ${nationLabels[card.nation] || card.nation}
                </span>
                ${inDeck ? '<span style="font-size:0.75rem; padding:0.2rem 0.7rem; border-radius:3px; background:var(--or-eclat); color:#000; font-weight:bold">Dans le deck</span>' : ''}
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.7rem; margin-bottom:1.2rem">
            <div style="background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px; padding:0.7rem; text-align:center">
                <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px">PV</div>
                <div style="font-size:1.3rem; font-weight:bold; color:#2ecc40">${card.hp}</div>
            </div>
            <div style="background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px; padding:0.7rem; text-align:center">
                <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px">ATK</div>
                <div style="font-size:1.3rem; font-weight:bold; color:#ff4444">${card.attack}</div>
            </div>
            <div style="background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px; padding:0.7rem; text-align:center">
                <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px">DEF</div>
                <div style="font-size:1.3rem; font-weight:bold; color:#3a7aff">${card.defense}</div>
            </div>
            <div style="background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px; padding:0.7rem; text-align:center">
                <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px">VIT</div>
                <div style="font-size:1.3rem; font-weight:bold; color:#ff8c00">${card.speed}</div>
            </div>
        </div>

        <div style="margin-bottom:1rem; background:rgba(9,11,21,0.7); border:1px solid var(--or-bord); border-radius:4px; padding:0.8rem">
            <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px; margin-bottom:0.4rem">POUVOIR SPÉCIAL</div>
            <p style="color:var(--or-texte); font-style:italic; line-height:1.5">${card.special}</p>
        </div>

        <p style="color:#a89060; font-size:0.95rem; line-height:1.6">${card.description || ''}</p>
    `;

    document.getElementById('card-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCardModal() {
    document.getElementById('card-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('card-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCardModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCardModal();
});

// Affiche/cache le formulaire de création de deck
function toggleNewDeckForm() {
    const f = document.getElementById('new-deck-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

// Affiche/cache le formulaire de renommage
function toggleRenameDeckForm() {
    const f = document.getElementById('rename-deck-form');
    if (f) f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
