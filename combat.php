<?php
// ============================================================
// combat.php — Système de combat PvE contre l'IA
//
// Déroulement :
//   Phase 1 - Sélection : le joueur choisit 3 cartes personnage de sa collection
//   Phase 2 - Combat    : résolution JS animée tour par tour
//   Phase 3 - Résultat  : sauvegarde en BDD et gain de points
//
// Logique de combat :
//   - L'IA pioche 3 personnages aléatoires du catalogue
//   - Chaque round : la carte avec le plus grand SPD attaque en premier
//   - Dégâts = max(1, ATK attaquant - DEF défenseur)
//   - Quand HP <= 0 : carte éliminée, passage à la suivante
//   - Victoire = toutes les cartes adverses éliminées
//   - Victoire : +150 pts | Défaite : +30 pts
//
// Premier combat : affiche un panneau tutoriel avant la sélection
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

$userId = $_SESSION['user_id'];

// ================================================================
// SAUVEGARDE DU RÉSULTAT (POST après un combat)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {

    $winner       = in_array($_POST['winner'] ?? '', ['player','opponent','draw'])
                    ? $_POST['winner'] : 'draw';
    $oppName      = htmlspecialchars(trim($_POST['opponent_name'] ?? 'IA Gardienne'));
    $playerScore  = max(0, (int)($_POST['player_score']   ?? 0));
    $oppScore     = max(0, (int)($_POST['opponent_score'] ?? 0));
    $roundsJson   = $_POST['rounds_json'] ?? '[]';

    // Validation minimale du JSON
    json_decode($roundsJson);
    if (json_last_error() !== JSON_ERROR_NONE) $roundsJson = '[]';

    // Points gagnés selon le résultat
    $pointsGain = ($winner === 'player') ? 150 : 30;

    // Sauvegarde en BDD dans une transaction
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO combats (player_id, opponent_name, player_score, opponent_score, winner, rounds_json)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $oppName, $playerScore, $oppScore, $winner, $roundsJson]);

        // Ajoute les points au joueur
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$pointsGain, $userId]);

        $pdo->commit();

        // Met à jour la session
        $_SESSION['points'] = ($_SESSION['points'] ?? 0) + $pointsGain;

        // Répond en JSON pour la requête AJAX du JS
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'points_gain' => $pointsGain, 'new_total' => $_SESSION['points']]);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur de sauvegarde']);
        exit;
    }
}

// ================================================================
// DONNÉES POUR LA PAGE
// ================================================================

// Vérifie si c'est le premier combat du joueur (pour afficher le tuto)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM combats WHERE player_id = ?");
$stmt->execute([$userId]);
$isFirstCombat = ((int)$stmt->fetchColumn() === 0);

// Récupère les cartes PERSONNAGE du joueur (seules les personnages combattent)
$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.rarity, c.hp, c.attack, c.defense, c.speed, c.special, c.nation
     FROM user_cards uc
     JOIN cards c ON c.id = uc.card_id
     WHERE uc.user_id = ? AND c.type = 'personnage'
     ORDER BY FIELD(c.rarity,'mythique','legendaire','epique','rare','peu-commune','commune'), c.name ASC"
);
$stmt->execute([$userId]);
$myPersonnages = $stmt->fetchAll();

// Récupère TOUS les personnages du catalogue pour l'IA
$stmt = $pdo->prepare("SELECT * FROM cards WHERE type = 'personnage' ORDER BY id");
$stmt->execute();
$allPersonnages = $stmt->fetchAll();

$pageTitle = 'Combat';
$rootPath  = '';
require_once 'includes/header.php';
?>

<div class="catalogue-main">

    <!-- ============================================================
         TUTORIEL (premier combat seulement)
         ============================================================ -->
    <?php if ($isFirstCombat): ?>
        <div id="tuto-panel" class="hero-content" style="margin-bottom:1.5rem">
            <h2 class="hero-title" style="font-size:1.4rem">Bienvenue au Combat !</h2>
            <p class="hero-sub">Votre premier duel vous attend</p>

            <div class="hero-visual" style="text-align:left; line-height:2">
                <strong style="color:var(--or-eclat); font-family:'Cinzel',serif">Règles du combat :</strong><br>
                <br>
                <span style="color:#2ecc40">1.</span> Sélectionnez <strong>3 cartes Personnage</strong> de votre collection.<br>
                <span style="color:#2ecc40">2.</span> L'IA choisit aussi 3 personnages aléatoires.<br>
                <span style="color:#2ecc40">3.</span> La carte avec la <strong>Vitesse (VIT)</strong> la plus haute attaque en premier.<br>
                <span style="color:#2ecc40">4.</span> <strong>Dégâts</strong> = max(1, ATK attaquant — DEF défenseur).<br>
                <span style="color:#2ecc40">5.</span> Quand les <strong>PV tombent à 0</strong>, la carte est éliminée.<br>
                <span style="color:#2ecc40">6.</span> Gagne l'équipe qui <strong>élimine toutes les cartes adverses</strong>.<br>
                <br>
                <span style="color:var(--or-eclat)">Victoire : +150 pts</span> &nbsp;|&nbsp;
                <span style="color:#ff6060">Défaite : +30 pts</span>
            </div>

            <button class="btn-hero" onclick="document.getElementById('tuto-panel').style.display='none'">
                Compris ! Choisir mes cartes
            </button>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         PHASE 1 : SÉLECTION DES CARTES
         ============================================================ -->
    <div id="selection-phase">
        <h2 class="form-title">Choisissez vos 3 Combattants</h2>

        <?php if (count($myPersonnages) < 3): ?>
            <div class="alert alert-error">
                Vous n'avez pas assez de cartes Personnage (<?= count($myPersonnages) ?>/3 requises).<br>
                <a href="catalogue.php?type=personnage">Achetez des personnages dans le catalogue !</a>
            </div>
        <?php else: ?>

            <p style="text-align:center; color:var(--or-texte); margin-bottom:1rem; font-style:italic">
                Cliquez sur 3 cartes pour les sélectionner (sélectionnées = bordure or)
            </p>

            <!-- Grille de sélection -->
            <div class="cards-grid-catalogue" id="selection-grid">
                <?php foreach ($myPersonnages as $card): ?>
                    <div class="card-item rarity-<?= htmlspecialchars($card['rarity']) ?> type-personnage"
                         id="sel-<?= $card['id'] ?>"
                         onclick="toggleSelectCard(<?= $card['id'] ?>)"
                         data-card='<?= json_encode($card, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                         style="cursor:pointer; transition:outline 0.15s">

                        <div class="card-image"></div>
                        <div class="card-name"><?= htmlspecialchars($card['name']) ?></div>

                        <!-- Stats résumées -->
                        <div style="display:flex; gap:0.3rem; font-size:0.58rem; font-family:'Cinzel',serif; flex-wrap:wrap; justify-content:center">
                            <span style="color:#2ecc40">♥<?= $card['hp'] ?></span>
                            <span style="color:#ff4444">⚔<?= $card['attack'] ?></span>
                            <span style="color:#3a7aff">🛡<?= $card['defense'] ?></span>
                            <span style="color:#ff8c00">⚡<?= $card['speed'] ?></span>
                        </div>

                        <!-- Indicateur de sélection -->
                        <div id="badge-<?= $card['id'] ?>" style="display:none">
                            <span class="card-indeck-badge">Sélectionné</span>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Compteur et bouton lancer le combat -->
            <div style="text-align:center; margin-top:1.5rem">
                <p id="selection-count" style="font-family:'Cinzel',serif; color:var(--or-cadre); margin-bottom:1rem">
                    0 / 3 cartes sélectionnées
                </p>
                <button id="start-combat-btn" class="btn-hero" onclick="startCombat()" disabled
                        style="opacity:0.4; cursor:not-allowed">
                    Lancer le combat !
                </button>
            </div>

        <?php endif; ?>
    </div>


    <!-- ============================================================
         PHASE 2 : ARÈNE DE COMBAT
         (caché jusqu'au lancement du combat)
         ============================================================ -->
    <div id="combat-phase" style="display:none">

        <h2 class="form-title">⚔ Combat en cours ⚔</h2>

        <!-- Terrain de jeu -->
        <div style="display:grid; grid-template-columns:1fr auto 1fr; gap:1rem; align-items:start; margin-bottom:1.5rem">

            <!-- Équipe joueur -->
            <div>
                <h3 style="font-family:'Cinzel',serif; color:#2ecc40; text-align:center; margin-bottom:0.8rem; font-size:0.9rem">
                    VOTRE ÉQUIPE
                </h3>
                <div id="player-cards" style="display:flex; flex-direction:column; gap:0.6rem"></div>
            </div>

            <!-- VS -->
            <div style="display:flex; align-items:center; justify-content:center; padding:2rem 0">
                <span style="font-family:'Cinzel',serif; font-size:1.8rem; color:var(--or-eclat); font-weight:800">VS</span>
            </div>

            <!-- Équipe IA -->
            <div>
                <h3 style="font-family:'Cinzel',serif; color:#ff4444; text-align:center; margin-bottom:0.8rem; font-size:0.9rem">
                    ÉQUIPE IA
                </h3>
                <div id="ai-cards" style="display:flex; flex-direction:column; gap:0.6rem"></div>
            </div>

        </div>

        <!-- Journal de combat -->
        <div style="
            background:rgba(0,0,0,0.5); border:1px solid var(--or-bord); border-radius:4px;
            padding:1rem; max-height:220px; overflow-y:auto; margin-bottom:1rem">
            <h4 style="font-family:'Cinzel',serif; font-size:0.8rem; color:var(--or-cadre); margin-bottom:0.5rem">
                Journal de combat
            </h4>
            <div id="combat-log" style="font-size:0.9rem; line-height:1.8"></div>
        </div>

        <!-- Bouton résoudre le combat -->
        <div style="text-align:center">
            <button id="resolve-btn" class="btn-hero" onclick="resolveCombat()">
                Résoudre le combat
            </button>
        </div>

    </div>


    <!-- ============================================================
         PHASE 3 : RÉSULTAT
         (caché jusqu'à la fin du combat)
         ============================================================ -->
    <div id="result-phase" style="display:none">
        <div class="hero-content">
            <h2 id="result-title" class="hero-title" style="font-size:1.8rem"></h2>
            <div id="result-details" class="hero-visual"></div>
            <div id="result-points" style="font-family:'Cinzel',serif; color:var(--or-eclat); font-size:1.1rem; margin-top:1rem"></div>
            <div class="hero-actions">
                <a href="combat.php" class="btn-hero">Rejouer</a>
                <a href="dashboard.php" class="btn-hero">Tableau de bord</a>
            </div>
        </div>
    </div>

</div><!-- fin .catalogue-main -->


<!-- ================================================================
     DONNÉES ET LOGIQUE DE COMBAT (JavaScript)
     ================================================================ -->
<script>
// ---- Données injectées depuis PHP ----
const MY_PERSONNAGES  = <?= json_encode($myPersonnages,  JSON_UNESCAPED_UNICODE) ?>;
const ALL_PERSONNAGES = <?= json_encode($allPersonnages, JSON_UNESCAPED_UNICODE) ?>;

// ---- État du combat ----
let selectedCards  = [];    // IDs des cartes sélectionnées par le joueur
let playerTeam     = [];    // Objets cartes de l'équipe joueur (avec HP courant)
let aiTeam         = [];    // Objets cartes de l'équipe IA
let combatLog      = [];    // Historique des rounds
let combatRounds   = [];    // Données détaillées pour la BDD

// ---- Couleurs selon la rareté ----
const RARITY_COLORS = {
    'commune':'#8a8a8a','peu-commune':'#2ecc40','rare':'#3a7aff',
    'epique':'#9b59b6','legendaire':'#ff8c00','mythique':'#ff2050'
};


/**
 * SÉLECTION DES CARTES — toggle une carte sélectionnée/désélectionnée
 */
function toggleSelectCard(cardId) {
    const idx = selectedCards.indexOf(cardId);
    const badge = document.getElementById('badge-' + cardId);
    const el    = document.getElementById('sel-' + cardId);

    if (idx === -1) {
        // Ajoute si moins de 3 sélectionnées
        if (selectedCards.length >= 3) return;
        selectedCards.push(cardId);
        badge.style.display = 'block';
        el.style.outline       = '3px solid var(--or-eclat)';
        el.style.outlineOffset = '3px';
    } else {
        // Désélectionne
        selectedCards.splice(idx, 1);
        badge.style.display    = 'none';
        el.style.outline       = '';
        el.style.outlineOffset = '';
    }

    // Met à jour le compteur et le bouton
    const count = selectedCards.length;
    document.getElementById('selection-count').textContent = count + ' / 3 cartes sélectionnées';
    const btn = document.getElementById('start-combat-btn');
    btn.disabled = (count < 3);
    btn.style.opacity = count >= 3 ? '1' : '0.4';
    btn.style.cursor  = count >= 3 ? 'pointer' : 'not-allowed';
}


/**
 * Crée une copie profonde d'une carte avec HP courant (pour le combat)
 */
function cloneCard(card, isAI) {
    return {
        id:      card.id,
        name:    card.name,
        rarity:  card.rarity,
        hp:      parseInt(card.hp),
        maxHp:   parseInt(card.hp),
        attack:  parseInt(card.attack),
        defense: parseInt(card.defense),
        speed:   parseInt(card.speed),
        special: card.special,
        nation:  card.nation,
        isAI:    isAI,
        alive:   true
    };
}


/**
 * Renvoie un tableau de N éléments choisis aléatoirement dans arr
 */
function pickRandom(arr, n) {
    const shuffled = [...arr].sort(() => Math.random() - 0.5);
    return shuffled.slice(0, n);
}


/**
 * DÉMARRAGE DU COMBAT
 * Construit les équipes et passe à la phase de combat
 */
function startCombat() {
    if (selectedCards.length < 3) return;

    // Construit l'équipe du joueur
    playerTeam = selectedCards.map(id => {
        const card = MY_PERSONNAGES.find(c => parseInt(c.id) === parseInt(id));
        return cloneCard(card, false);
    });

    // L'IA pioche 3 personnages aléatoires
    const aiPick = pickRandom(ALL_PERSONNAGES, 3);
    aiTeam = aiPick.map(c => cloneCard(c, true));

    // Affiche la phase de combat
    document.getElementById('selection-phase').style.display = 'none';
    document.getElementById('combat-phase').style.display    = 'block';

    // Rend les cartes à l'écran
    renderTeam('player-cards', playerTeam, '#2ecc40');
    renderTeam('ai-cards',     aiTeam,     '#ff4444');

    addLog('<span style="color:var(--or-eclat)">⚔ Le combat commence !</span>');
}


/**
 * Affiche une équipe dans le conteneur HTML donné
 */
function renderTeam(containerId, team, borderColor) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    team.forEach(card => {
        const hpPercent = Math.max(0, Math.round(card.hp / card.maxHp * 100));
        const hpColor   = hpPercent > 50 ? '#2ecc40' : hpPercent > 25 ? '#ff8c00' : '#ff2020';
        const opacity   = card.alive ? '1' : '0.35';
        const color     = RARITY_COLORS[card.rarity] || '#c9a227';

        container.innerHTML += `
            <div id="card-combat-${card.id}-${card.isAI ? 'ai' : 'pl'}"
                 style="
                border:2px solid ${card.alive ? color : '#333'}; border-radius:6px;
                background:rgba(0,0,0,0.4); padding:0.7rem; opacity:${opacity};
                transition:opacity 0.4s">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem">
                    <span style="font-family:'Cinzel',serif; font-size:0.8rem; color:${card.alive ? '#f0d89a' : '#555'}">
                        ${card.alive ? '' : '✝ '} ${card.name}
                    </span>
                    <span style="font-size:0.75rem; color:${color}">${card.rarity}</span>
                </div>
                <!-- Barre de vie -->
                <div style="background:#111; border-radius:3px; height:8px; overflow:hidden; margin-bottom:0.4rem">
                    <div id="hp-bar-${card.id}-${card.isAI ? 'ai' : 'pl'}"
                         style="height:100%; background:${hpColor}; width:${hpPercent}%;
                                transition:width 0.5s, background 0.5s; border-radius:3px">
                    </div>
                </div>
                <div style="font-size:0.72rem; color:${hpColor}">
                    <span id="hp-val-${card.id}-${card.isAI ? 'ai' : 'pl'}">${card.hp}</span> / ${card.maxHp} PV
                </div>
                <div style="display:flex; gap:0.5rem; margin-top:0.3rem; font-size:0.65rem; flex-wrap:wrap">
                    <span style="color:#ff4444">⚔ ${card.attack}</span>
                    <span style="color:#3a7aff">🛡 ${card.defense}</span>
                    <span style="color:#ff8c00">⚡ ${card.speed}</span>
                </div>
            </div>`;
    });
}


/**
 * Ajoute un message au journal de combat
 */
function addLog(msg) {
    combatLog.push(msg);
    const log = document.getElementById('combat-log');
    log.innerHTML += '<div>' + msg + '</div>';
    log.scrollTop = log.scrollHeight;
}


/**
 * Met à jour l'affichage de la barre de vie d'une carte
 */
function updateHpDisplay(card) {
    const suffix  = card.isAI ? 'ai' : 'pl';
    const hpVal   = document.getElementById('hp-val-' + card.id + '-' + suffix);
    const hpBar   = document.getElementById('hp-bar-' + card.id + '-' + suffix);
    const cardDiv = document.getElementById('card-combat-' + card.id + '-' + suffix);

    if (!hpVal || !hpBar) return;

    const hpPercent = Math.max(0, Math.round(card.hp / card.maxHp * 100));
    const hpColor   = hpPercent > 50 ? '#2ecc40' : hpPercent > 25 ? '#ff8c00' : '#ff2020';

    hpVal.textContent = Math.max(0, card.hp);
    hpBar.style.width      = hpPercent + '%';
    hpBar.style.background = hpColor;

    if (!card.alive && cardDiv) {
        cardDiv.style.opacity     = '0.35';
        cardDiv.style.borderColor = '#333';
    }
}


/**
 * RÉSOLUTION DU COMBAT
 * Calcule tous les rounds et affiche le résultat animé
 */
function resolveCombat() {
    document.getElementById('resolve-btn').style.display = 'none';

    let roundNum = 1;
    const MAX_ROUNDS = 50; // Sécurité : évite les boucles infinies

    // Copie pour ne pas modifier les originaux
    let pTeam = playerTeam.map(c => ({...c}));
    let aTeam = aiTeam.map(c => ({...c}));

    // Fonction pour trouver la première carte vivante d'une équipe
    const firstAlive = arr => arr.find(c => c.alive && c.hp > 0);

    // Calcule tous les rounds en avance (pour l'animation)
    const roundsData = [];

    while (roundNum <= MAX_ROUNDS) {
        const pCard = firstAlive(pTeam);
        const aCard = firstAlive(aTeam);

        if (!pCard || !aCard) break;

        // Détermine l'ordre d'attaque selon la vitesse
        const pFirst = pCard.speed >= aCard.speed;

        const roundInfo = { round: roundNum, events: [] };

        // Attaque 1 : le plus rapide frappe en premier
        const atk1 = pFirst ? pCard : aCard;
        const def1 = pFirst ? aCard : pCard;

        const dmg1 = Math.max(1, atk1.attack - def1.defense);
        def1.hp -= dmg1;
        if (def1.hp <= 0) { def1.hp = 0; def1.alive = false; }

        roundInfo.events.push({
            attacker: atk1.name, defender: def1.name,
            damage: dmg1, defenderHp: def1.hp, defenderAlive: def1.alive
        });

        // Attaque 2 : riposte si le défenseur est encore vivant
        if (def1.alive) {
            const dmg2 = Math.max(1, def1.attack - atk1.defense);
            atk1.hp -= dmg2;
            if (atk1.hp <= 0) { atk1.hp = 0; atk1.alive = false; }

            roundInfo.events.push({
                attacker: def1.name, defender: atk1.name,
                damage: dmg2, defenderHp: atk1.hp, defenderAlive: atk1.alive
            });
        }

        roundsData.push(roundInfo);
        roundNum++;

        // Vérifie si le combat est terminé
        if (!firstAlive(pTeam) || !firstAlive(aTeam)) break;
    }

    // Détermine le vainqueur
    const pAlive = pTeam.filter(c => c.alive).length;
    const aAlive = aTeam.filter(c => c.alive).length;
    const pHpTotal = pTeam.reduce((s, c) => s + Math.max(0, c.hp), 0);
    const aHpTotal = aTeam.reduce((s, c) => s + Math.max(0, c.hp), 0);

    let winner = 'draw';
    if (pAlive > aAlive)                      winner = 'player';
    else if (aAlive > pAlive)                 winner = 'opponent';
    else if (pAlive === 0 && aAlive === 0)    winner = pHpTotal >= aHpTotal ? 'player' : 'opponent';

    // ---- Animation des rounds (délai entre chaque) ----
    combatRounds = roundsData;
    let delay = 0;
    const DELAY_PER_EVENT = 600; // ms entre chaque événement

    // Remet les équipes à l'état initial pour l'animation
    playerTeam.forEach(c => { c.hp = c.maxHp; c.alive = true; });
    aiTeam.forEach(c => { c.hp = c.maxHp; c.alive = true; });
    renderTeam('player-cards', playerTeam, '#2ecc40');
    renderTeam('ai-cards',     aiTeam,     '#ff4444');

    // Rejoue les rounds avec animation
    let pTeamAnim = playerTeam.map(c => ({...c}));
    let aTeamAnim = aiTeam.map(c => ({...c}));

    roundsData.forEach((round, ri) => {
        setTimeout(() => {
            addLog(`<span style="color:var(--or-cadre); font-family:'Cinzel',serif">— Round ${round.round} —</span>`);
        }, delay);
        delay += 200;

        round.events.forEach(ev => {
            setTimeout(() => {
                // Applique les dégâts à l'objet animé
                // Trouve la carte par nom dans les deux équipes
                const findCard = (name) => {
                    return pTeamAnim.find(c => c.name === name) || aTeamAnim.find(c => c.name === name);
                };
                const defCard = findCard(ev.defender);
                if (defCard) {
                    defCard.hp    = ev.defenderHp;
                    defCard.alive = ev.defenderAlive;
                    updateHpDisplay(defCard);
                }

                const color = ev.defenderAlive ? 'var(--or-texte)' : '#ff4444';
                const elimMsg = ev.defenderAlive ? '' : ` <span style="color:#ff2050">✝ ÉLIMINÉ</span>`;
                addLog(`<span style="color:#f0d89a">${ev.attacker}</span> → <span style="color:${color}">${ev.defender}</span> : <span style="color:#ff4444">-${ev.damage} PV</span>${elimMsg}`);
            }, delay);
            delay += DELAY_PER_EVENT;
        });
    });

    // Affiche le résultat final après toute l'animation
    setTimeout(() => {
        showResult(winner, pHpTotal, aHpTotal);
    }, delay + 500);
}


/**
 * Affiche la phase de résultat et sauvegarde en BDD
 */
function showResult(winner, playerScore, oppScore) {
    const opponentName = 'IA Gardienne';
    const isWin  = winner === 'player';
    const isDraw = winner === 'draw';

    // Titre et style selon le résultat
    const titleEl  = document.getElementById('result-title');
    const detailEl = document.getElementById('result-details');
    const ptsEl    = document.getElementById('result-points');

    if (isWin) {
        titleEl.textContent = 'Victoire !';
        titleEl.style.color = '#2ecc40';
    } else if (isDraw) {
        titleEl.textContent = 'Égalité !';
        titleEl.style.color = 'var(--or-eclat)';
    } else {
        titleEl.textContent = 'Défaite...';
        titleEl.style.color = '#ff4444';
    }

    const pointsGain = isWin ? 150 : 30;
    ptsEl.textContent = '+' + pointsGain + ' points gagnés !';

    detailEl.innerHTML = `
        <div style="text-align:left; line-height:2">
            <strong>Votre équipe :</strong> ${playerScore} PV restants<br>
            <strong>IA :</strong> ${oppScore} PV restants<br>
            <br>
            ${isWin ? '🏆 Vous avez éliminé toutes les cartes adverses !' :
              isDraw ? '🤝 Personne ne l\'emporte...' :
                       '⚔️ L\'IA a résisté à vos assauts.'}
        </div>`;

    // Affiche la phase résultat
    document.getElementById('combat-phase').style.display = 'none';
    document.getElementById('result-phase').style.display = 'block';

    // Sauvegarde en BDD via une requête POST
    fetch('combat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            save_result:    '1',
            winner:         winner,
            opponent_name:  opponentName,
            player_score:   playerScore,
            opponent_score: oppScore,
            rounds_json:    JSON.stringify(combatRounds)
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            ptsEl.textContent = `+${data.points_gain} points ! Nouveau solde : ${data.new_total} pts`;
        }
    })
    .catch(() => {
        // Sauvegarde échouée mais on ne bloque pas l'affichage
        console.warn('Sauvegarde du combat échouée');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
