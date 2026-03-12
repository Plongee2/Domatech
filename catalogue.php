<?php
// ============================================================
// catalogue.php — Catalogue des 50 cartes du jeu
//
// Fonctionnalités :
//   - Affichage de toutes les cartes en grille (style card-item)
//   - Filtres par rareté, type, nation (via GET)
//   - Recherche par nom
//   - Si joueur connecté : bouton Acheter (si pas possédée + assez de points)
//     ou badge "Possédé"
//   - Achat en POST : vérifie les points, insère dans user_cards, soustrait les points
//   - Modal JS au clic sur une carte pour afficher les détails
//   - Accessible sans connexion (lecture seule)
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ================================================================
// TRAITEMENT DE L'ACHAT (POST)
// ================================================================
$msgAchat = '';
$errAchat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_card'])) {

    // Seul un joueur connecté peut acheter
    if (!isLoggedIn() || $_SESSION['role'] !== 'joueur') {
        $errAchat = "Vous devez être connecté en tant que joueur pour acheter.";
    } else {
        $cardId   = (int)($_POST['card_id'] ?? 0);
        $userId   = $_SESSION['user_id'];

        // Récupère le prix de la carte et ses infos
        $stmt = $pdo->prepare("SELECT id, name, price FROM cards WHERE id = ?");
        $stmt->execute([$cardId]);
        $card = $stmt->fetch();

        if (!$card) {
            $errAchat = "Carte introuvable.";
        } else {
            // Vérifie que le joueur ne la possède pas déjà
            $stmt = $pdo->prepare("SELECT id FROM user_cards WHERE user_id = ? AND card_id = ?");
            $stmt->execute([$userId, $cardId]);
            if ($stmt->fetch()) {
                $errAchat = "Vous possédez déjà cette carte.";
            } else {
                // Vérifie que le joueur a assez de points
                $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $currentPoints = (int)$stmt->fetchColumn();

                if ($currentPoints < $card['price']) {
                    $errAchat = "Points insuffisants. Il vous faut {$card['price']} pts, vous en avez {$currentPoints}.";
                } else {
                    // Tout est OK : on effectue l'achat dans une transaction
                    $pdo->beginTransaction();
                    try {
                        // Ajoute la carte à la collection
                        $stmt = $pdo->prepare("INSERT INTO user_cards (user_id, card_id) VALUES (?, ?)");
                        $stmt->execute([$userId, $cardId]);

                        // Soustrait les points
                        $newPoints = $currentPoints - $card['price'];
                        $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
                        $stmt->execute([$newPoints, $userId]);

                        $pdo->commit();

                        // Met à jour la session
                        $_SESSION['points'] = $newPoints;
                        $msgAchat = "Carte \"" . htmlspecialchars($card['name']) . "\" achetée pour {$card['price']} pts !";

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errAchat = "Erreur lors de l'achat. Veuillez réessayer.";
                    }
                }
            }
        }
    }
}

// ================================================================
// RÉCUPÉRATION DES FILTRES (GET)
// ================================================================
$search  = trim($_GET['q']       ?? '');
$fRarity = trim($_GET['rarity']  ?? '');
$fType   = trim($_GET['type']    ?? '');
$fNation = trim($_GET['nation']  ?? '');

// Valeurs autorisées pour les filtres (protection contre valeurs arbitraires)
$validRarities = ['commune','peu-commune','rare','epique','legendaire','mythique'];
$validTypes    = ['personnage','equipement','ville'];
$validNations  = ['bonta','brakmar','astrub','amakna','frigost'];

if (!in_array($fRarity, $validRarities, true)) $fRarity = '';
if (!in_array($fType,   $validTypes,    true)) $fType   = '';
if (!in_array($fNation, $validNations,  true)) $fNation = '';

// ================================================================
// REQUÊTE DYNAMIQUE AVEC FILTRES
// ================================================================
// On construit la clause WHERE dynamiquement avec un tableau de conditions
$conditions = [];
$params     = [];

if (!empty($search)) {
    $conditions[] = "name LIKE ?";
    $params[]     = '%' . $search . '%';
}
if (!empty($fRarity)) {
    $conditions[] = "rarity = ?";
    $params[]     = $fRarity;
}
if (!empty($fType)) {
    $conditions[] = "type = ?";
    $params[]     = $fType;
}
if (!empty($fNation)) {
    $conditions[] = "nation = ?";
    $params[]     = $fNation;
}

// Ordre de rareté : mythique > légendaire > épique > rare > peu-commune > commune
$orderRarity = "FIELD(rarity, 'mythique','legendaire','epique','rare','peu-commune','commune')";
$sql = "SELECT * FROM cards";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY $orderRarity, name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

// ================================================================
// COLLECTION DU JOUEUR CONNECTÉ (pour savoir quelles cartes il possède)
// ================================================================
$ownedCards = [];
if (isLoggedIn() && $_SESSION['role'] === 'joueur') {
    $stmt = $pdo->prepare("SELECT card_id FROM user_cards WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    foreach ($stmt->fetchAll() as $row) {
        $ownedCards[$row['card_id']] = true;
    }
}

$pageTitle = 'Catalogue';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="catalogue-main">

    <!-- ---- Barre de recherche + solde de points ---- -->
    <div class="catalogue-search">
        <form method="GET" action="catalogue.php" style="display:contents">
            <input type="text"
                   name="q"
                   class="search-input"
                   placeholder="Rechercher une carte..."
                   value="<?= htmlspecialchars($search) ?>">
            <?php if (!empty($search) || !empty($fRarity) || !empty($fType) || !empty($fNation)): ?>
                <a href="catalogue.php" class="btn-small" style="white-space:nowrap">Tout afficher</a>
            <?php endif; ?>
        </form>
        <?php if (isLoggedIn() && $_SESSION['role'] === 'joueur'): ?>
            <div class="points-display">
                <?= number_format($_SESSION['points'] ?? 0, 0, ',', ' ') ?> pts
            </div>
        <?php endif; ?>
    </div>

    <!-- Messages d'achat -->
    <?php if (!empty($msgAchat)): ?>
        <div class="alert alert-success"><?= $msgAchat ?></div>
    <?php endif; ?>
    <?php if (!empty($errAchat)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errAchat) ?></div>
    <?php endif; ?>

    <!-- ---- Layout : filtres à gauche + grille de cartes à droite ---- -->
    <div class="catalogue-layout">

        <!-- SIDEBAR FILTRES -->
        <form method="GET" action="catalogue.php">
            <?php if (!empty($search)): ?>
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <aside class="filters-panel">

                <div class="filters-title">Filtres</div>

                <!-- Filtre Rareté -->
                <div class="filter-group">
                    <div class="filter-group-title">Rareté</div>
                    <?php
                    $raretesLabels = [
                        'commune'     => ['label' => 'Commune',      'dot' => 'dot-commune'],
                        'peu-commune' => ['label' => 'Peu commune',   'dot' => 'dot-peu-commune'],
                        'rare'        => ['label' => 'Rare',          'dot' => 'dot-rare'],
                        'epique'      => ['label' => 'Épique',        'dot' => 'dot-epique'],
                        'legendaire'  => ['label' => 'Légendaire',    'dot' => 'dot-legendaire'],
                        'mythique'    => ['label' => 'Mythique',      'dot' => 'dot-mythique'],
                    ];
                    foreach ($raretesLabels as $val => $info): ?>
                        <label class="filter-option">
                            <input type="radio" name="rarity" value="<?= $val ?>"
                                   <?= $fRarity === $val ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <span class="rarity-dot <?= $info['dot'] ?>"></span>
                            <?= $info['label'] ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!empty($fRarity)): ?>
                        <a href="catalogue.php?<?= http_build_query(array_filter(['q'=>$search,'type'=>$fType,'nation'=>$fNation])) ?>"
                           style="font-size:0.75rem; color:var(--or-cadre); margin-top:0.3rem; display:block">
                            Effacer
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Filtre Type -->
                <div class="filter-group">
                    <div class="filter-group-title">Type</div>
                    <?php foreach (['personnage' => 'Personnage', 'equipement' => 'Équipement', 'ville' => 'Ville'] as $val => $label): ?>
                        <label class="filter-option">
                            <input type="radio" name="type" value="<?= $val ?>"
                                   <?= $fType === $val ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <?= $label ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!empty($fType)): ?>
                        <a href="catalogue.php?<?= http_build_query(array_filter(['q'=>$search,'rarity'=>$fRarity,'nation'=>$fNation])) ?>"
                           style="font-size:0.75rem; color:var(--or-cadre); margin-top:0.3rem; display:block">
                            Effacer
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Filtre Nation -->
                <div class="filter-group">
                    <div class="filter-group-title">Nation</div>
                    <?php foreach (['bonta'=>'Bonta','brakmar'=>'Brakmar','astrub'=>'Astrub','amakna'=>'Amakna','frigost'=>'Frigost'] as $val => $label): ?>
                        <label class="filter-option">
                            <input type="radio" name="nation" value="<?= $val ?>"
                                   <?= $fNation === $val ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <?= $label ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!empty($fNation)): ?>
                        <a href="catalogue.php?<?= http_build_query(array_filter(['q'=>$search,'rarity'=>$fRarity,'type'=>$fType])) ?>"
                           style="font-size:0.75rem; color:var(--or-cadre); margin-top:0.3rem; display:block">
                            Effacer
                        </a>
                    <?php endif; ?>
                </div>

            </aside>
        </form>

        <!-- GRILLE DE CARTES -->
        <div class="cards-grid-catalogue">
            <?php if (empty($cards)): ?>
                <p style="grid-column:1/-1; color:var(--or-texte); text-align:center; padding:2rem">
                    Aucune carte ne correspond à ces filtres.
                </p>
            <?php else: ?>
                <?php foreach ($cards as $card): ?>
                    <?php
                    $isOwned = isset($ownedCards[$card['id']]);
                    $canBuy  = isJoueur() && !$isOwned;
                    ?>
                    <!-- Carte cliquable → ouvre le modal -->
                    <div class="card-item rarity-<?= htmlspecialchars($card['rarity']) ?> type-<?= htmlspecialchars($card['type']) ?>"
                         onclick="openCardModal(<?= $card['id'] ?>)"
                         title="<?= htmlspecialchars($card['name']) ?>">

                        <!-- Illustration (placeholder) -->
                        <div class="card-image"></div>

                        <!-- Nom -->
                        <div class="card-name"><?= htmlspecialchars($card['name']) ?></div>

                        <!-- Prix -->
                        <div class="card-price"><?= number_format($card['price'], 0, ',', ' ') ?> pts</div>

                        <!-- Badge "Possédé" ou bouton "Acheter" -->
                        <?php if ($isOwned): ?>
                            <span class="card-owned-badge">Possédé</span>
                        <?php elseif ($canBuy): ?>
                            <!-- On stoppe la propagation du clic pour ne pas ouvrir le modal -->
                            <form method="POST" onclick="event.stopPropagation()">
                                <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                <button type="submit" name="buy_card" class="card-buy-btn"
                                        <?= ($_SESSION['points'] ?? 0) < $card['price'] ? 'disabled title="Points insuffisants"' : '' ?>>
                                    Acheter
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- fin .catalogue-layout -->

    <!-- Compteur de résultats -->
    <p style="text-align:center; color:var(--or-texte); font-size:0.9rem; margin-top:0.5rem">
        <?= count($cards) ?> carte<?= count($cards) > 1 ? 's' : '' ?> affichée<?= count($cards) > 1 ? 's' : '' ?>
        <?php if (!empty($search) || !empty($fRarity) || !empty($fType) || !empty($fNation)): ?>
            — <a href="catalogue.php">Voir tout</a>
        <?php endif; ?>
    </p>

</div><!-- fin .catalogue-main -->


<!-- ================================================================
     MODAL DE DÉTAIL — affiche les stats complètes d'une carte
     ================================================================ -->
<div id="card-modal" style="
    display:none; position:fixed; inset:0; z-index:500;
    background:rgba(0,0,0,0.85); align-items:center; justify-content:center">

    <div id="modal-content" style="
        max-width:480px; width:90%; max-height:90vh; overflow-y:auto;
        background:linear-gradient(180deg, #1a2040 0%, #0b0d1a 100%);
        border:2px solid var(--or-cadre); border-radius:8px; padding:2rem;
        box-shadow:0 0 0 1px var(--or-bord), 0 20px 60px rgba(0,0,0,0.9)">

        <!-- Bouton fermer -->
        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem">
            <button onclick="closeCardModal()" style="
                background:none; border:1px solid var(--or-bord); color:var(--or-texte);
                padding:0.3rem 0.8rem; border-radius:4px; cursor:pointer; font-family:'Cinzel',serif">
                ✕ Fermer
            </button>
        </div>

        <!-- Contenu dynamique injecté par JS -->
        <div id="modal-body"></div>
    </div>
</div>

<!-- Données des cartes en JSON pour le modal (injectées depuis PHP) -->
<script>
// Tableau JSON de toutes les cartes affichées — utilisé par openCardModal()
const CARDS_DATA = <?php
    // On re-récupère toutes les cartes pour le JS (pas seulement celles filtrées)
    $stmtAll = $pdo->prepare("SELECT * FROM cards ORDER BY id");
    $stmtAll->execute();
    $allCards = $stmtAll->fetchAll();
    // Transforme en objet indexé par ID pour un accès rapide
    $cardsById = [];
    foreach ($allCards as $c) {
        $cardsById[(int)$c['id']] = $c;
    }
    echo json_encode($cardsById, JSON_UNESCAPED_UNICODE);
?>;

// Cartes possédées par le joueur connecté
const OWNED_IDS = <?= json_encode(array_keys($ownedCards)) ?>;

/**
 * Ouvre le modal et affiche les détails de la carte d'ID donné.
 */
function openCardModal(cardId) {
    const card = CARDS_DATA[cardId];
    if (!card) return;

    const owned    = OWNED_IDS.includes(parseInt(cardId));
    const rarityLabels = {
        'commune':     'Commune',
        'peu-commune': 'Peu commune',
        'rare':        'Rare',
        'epique':      'Épique',
        'legendaire':  'Légendaire',
        'mythique':    'Mythique'
    };
    const nationLabels = {
        'bonta':   'Bonta',
        'brakmar': 'Brakmar',
        'astrub':  'Astrub',
        'amakna':  'Amakna',
        'frigost': 'Frigost'
    };

    // Couleurs selon la rareté
    const rarityColors = {
        'commune':     '#8a8a8a',
        'peu-commune': '#2ecc40',
        'rare':        '#3a7aff',
        'epique':      '#9b59b6',
        'legendaire':  '#ff8c00',
        'mythique':    '#ff2050'
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
                ${owned ? '<span style="font-size:0.75rem; padding:0.2rem 0.7rem; border-radius:3px; background:#2ecc40; color:#000; font-weight:bold">Possédée</span>' : ''}
            </div>
        </div>

        <!-- Stats de combat -->
        ${card.type !== 'equipement' ? `
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
        </div>` : `
        <div style="margin-bottom:1.2rem; background:rgba(0,0,0,0.3); border:1px solid var(--or-bord); border-radius:4px; padding:0.8rem; text-align:center">
            <div style="font-family:'Cinzel',serif; font-size:0.75rem; color:var(--or-cadre); margin-bottom:0.3rem">BONUS D'ÉQUIPEMENT</div>
            ${card.hp > 0 ? `<span style="color:#2ecc40; margin:0 0.3rem">+${card.hp} PV</span>` : ''}
            ${card.attack > 0 ? `<span style="color:#ff4444; margin:0 0.3rem">+${card.attack} ATK</span>` : ''}
            ${card.defense > 0 ? `<span style="color:#3a7aff; margin:0 0.3rem">+${card.defense} DEF</span>` : ''}
            ${card.speed > 0 ? `<span style="color:#ff8c00; margin:0 0.3rem">+${card.speed} VIT</span>` : ''}
        </div>`}

        <!-- Pouvoir spécial -->
        <div style="margin-bottom:1rem; background:rgba(9,11,21,0.7); border:1px solid var(--or-bord); border-radius:4px; padding:0.8rem">
            <div style="font-family:'Cinzel',serif; font-size:0.7rem; color:var(--or-cadre); letter-spacing:1px; margin-bottom:0.4rem">POUVOIR SPÉCIAL</div>
            <p style="color:var(--or-texte); font-style:italic; line-height:1.5">${card.special}</p>
        </div>

        <!-- Description -->
        <p style="color:#a89060; font-size:0.95rem; line-height:1.6">${card.description || ''}</p>

        <!-- Prix -->
        <div style="margin-top:1rem; text-align:center; font-family:'Cinzel',serif; font-size:0.9rem; color:var(--or-eclat)">
            Prix : ${parseInt(card.price).toLocaleString('fr-FR')} points
        </div>
    `;

    // Affiche le modal
    document.getElementById('card-modal').style.display = 'flex';
    // Empêche le scroll de la page derrière
    document.body.style.overflow = 'hidden';
}

/**
 * Ferme le modal.
 */
function closeCardModal() {
    document.getElementById('card-modal').style.display = 'none';
    document.body.style.overflow = '';
}

// Ferme le modal en cliquant en dehors
document.getElementById('card-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCardModal();
});

// Ferme le modal avec Échap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCardModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
