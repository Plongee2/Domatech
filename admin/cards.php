<?php
// ============================================================
// admin/cards.php — CRUD complet sur la table cards
//
// Réservé au rôle 'admin'.
//
// Fonctionnalités :
//   - Liste toutes les cartes avec pagination (20 par page)
//   - Formulaire d'ajout (tous les champs : nom, rareté, type, nation, stats, special, description, prix)
//   - Formulaire de modification (pré-rempli avec les données existantes)
//   - Suppression avec confirmation JS
//   - Validation complète côté serveur
// ============================================================
session_start();
$rootPath = '../';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Protection : seul l'admin peut accéder ici
requireRole('admin', '../');

// ---- Variables du formulaire ----
$formData = [
    'name'        => '',
    'rarity'      => '',
    'type'        => '',
    'nation'      => '',
    'hp'          => '',
    'attack'      => '',
    'defense'     => '',
    'speed'       => '',
    'special'     => '',
    'description' => '',
    'price'       => '',
];
$erreurs  = [];
$message  = '';
$editMode = false;
$editId   = null;

// ================================================================
// MODE ÉDITION — charge les données de la carte à modifier
// ================================================================
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editMode = true;
    $editId   = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$editId]);
    $existingCard = $stmt->fetch();

    if ($existingCard) {
        // Pré-remplit le formulaire
        $formData = [
            'name'        => $existingCard['name'],
            'rarity'      => $existingCard['rarity'],
            'type'        => $existingCard['type'],
            'nation'      => $existingCard['nation'],
            'hp'          => $existingCard['hp'],
            'attack'      => $existingCard['attack'],
            'defense'     => $existingCard['defense'],
            'speed'       => $existingCard['speed'],
            'special'     => $existingCard['special'],
            'description' => $existingCard['description'],
            'price'       => $existingCard['price'],
        ];
    } else {
        $editMode = false;
        $message  = "Carte introuvable.";
    }
}

// ================================================================
// SUPPRESSION
// ================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Récupère le nom avant suppression pour le message
    $stmt = $pdo->prepare("SELECT name FROM cards WHERE id = ?");
    $stmt->execute([$id]);
    $cardToDelete = $stmt->fetch();

    if ($cardToDelete) {
        $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: cards.php?msg=" . urlencode("Carte \"" . $cardToDelete['name'] . "\" supprimée."));
    } else {
        header("Location: cards.php?err=Carte+introuvable.");
    }
    exit;
}

// Récupère le message/erreur depuis l'URL
if (isset($_GET['msg'])) $message = htmlspecialchars(urldecode($_GET['msg']));
if (isset($_GET['err'])) $erreurs[] = htmlspecialchars(urldecode($_GET['err']));

// ================================================================
// TRAITEMENT DU FORMULAIRE (ajout ou modification)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère tous les champs du formulaire
    $formData = [
        'name'        => trim($_POST['name']        ?? ''),
        'rarity'      => trim($_POST['rarity']      ?? ''),
        'type'        => trim($_POST['type']         ?? ''),
        'nation'      => trim($_POST['nation']       ?? ''),
        'hp'          => trim($_POST['hp']           ?? ''),
        'attack'      => trim($_POST['attack']       ?? ''),
        'defense'     => trim($_POST['defense']      ?? ''),
        'speed'       => trim($_POST['speed']        ?? ''),
        'special'     => trim($_POST['special']      ?? ''),
        'description' => trim($_POST['description']  ?? ''),
        'price'       => trim($_POST['price']        ?? ''),
    ];

    $editId   = isset($_POST['edit_id']) && $_POST['edit_id'] !== '' ? (int)$_POST['edit_id'] : null;
    $editMode = ($editId !== null);

    // ---- VALIDATION ----
    $validRarities = ['commune','peu-commune','rare','epique','legendaire','mythique'];
    $validTypes    = ['personnage','equipement','ville'];
    $validNations  = ['bonta','brakmar','astrub','amakna','frigost'];

    if (empty($formData['name'])) {
        $erreurs['name'] = "Le nom est obligatoire.";
    } elseif (strlen($formData['name']) > 100) {
        $erreurs['name'] = "Le nom ne peut pas dépasser 100 caractères.";
    }

    if (!in_array($formData['rarity'], $validRarities, true)) {
        $erreurs['rarity'] = "Veuillez choisir une rareté valide.";
    }

    if (!in_array($formData['type'], $validTypes, true)) {
        $erreurs['type'] = "Veuillez choisir un type valide.";
    }

    if (!in_array($formData['nation'], $validNations, true)) {
        $erreurs['nation'] = "Veuillez choisir une nation valide.";
    }

    // Stats numériques : obligatoires, entiers positifs ou nuls
    foreach (['hp','attack','defense','speed'] as $stat) {
        if ($formData[$stat] === '' || !is_numeric($formData[$stat]) || (int)$formData[$stat] < 0) {
            $erreurs[$stat] = ucfirst($stat) . " doit être un nombre positif ou nul.";
        }
    }

    if ($formData['price'] === '' || !is_numeric($formData['price']) || (int)$formData['price'] < 1) {
        $erreurs['price'] = "Le prix doit être un nombre positif (minimum 1).";
    }

    // Si validation OK → insertion ou mise à jour
    if (empty($erreurs)) {
        $values = [
            $formData['name'],
            $formData['rarity'],
            $formData['type'],
            $formData['nation'],
            (int)$formData['hp'],
            (int)$formData['attack'],
            (int)$formData['defense'],
            (int)$formData['speed'],
            $formData['special'],
            $formData['description'],
            (int)$formData['price'],
        ];

        if ($editMode) {
            // MODIFICATION
            $values[] = $editId;
            $stmt = $pdo->prepare(
                "UPDATE cards SET
                    name=?, rarity=?, type=?, nation=?,
                    hp=?, attack=?, defense=?, speed=?,
                    special=?, description=?, price=?
                 WHERE id=?"
            );
            $stmt->execute($values);
            header("Location: cards.php?msg=" . urlencode("Carte \"" . $formData['name'] . "\" modifiée avec succès."));

        } else {
            // CRÉATION
            $stmt = $pdo->prepare(
                "INSERT INTO cards (name, rarity, type, nation, hp, attack, defense, speed, special, description, price)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute($values);
            header("Location: cards.php?msg=" . urlencode("Carte \"" . $formData['name'] . "\" ajoutée avec succès."));
        }
        exit;
    }
}

// ================================================================
// LISTE DES CARTES AVEC PAGINATION
// ================================================================
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

// Filtre de recherche rapide dans la liste admin
$searchAdmin = trim($_GET['q'] ?? '');

if (!empty($searchAdmin)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cards WHERE name LIKE ?");
    $stmt->execute(['%' . $searchAdmin . '%']);
    $totalCards = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM cards WHERE name LIKE ?
         ORDER BY FIELD(rarity,'mythique','legendaire','epique','rare','peu-commune','commune'), name ASC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(['%' . $searchAdmin . '%', $perPage, $offset]);
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cards");
    $stmt->execute();
    $totalCards = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM cards
         ORDER BY FIELD(rarity,'mythique','legendaire','epique','rare','peu-commune','commune'), name ASC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$perPage, $offset]);
}

$cards      = $stmt->fetchAll();
$totalPages = max(1, (int)ceil($totalCards / $perPage));

// Options des selects (utilisées dans le formulaire et les filtres)
$rarities = ['commune','peu-commune','rare','epique','legendaire','mythique'];
$types    = ['personnage','equipement','ville'];
$nations  = ['bonta','brakmar','astrub','amakna','frigost'];

$rarityLabels = ['commune'=>'Commune','peu-commune'=>'Peu commune','rare'=>'Rare',
                 'epique'=>'Épique','legendaire'=>'Légendaire','mythique'=>'Mythique'];
$rarityColors = ['commune'=>'#8a8a8a','peu-commune'=>'#2ecc40','rare'=>'#3a7aff',
                 'epique'=>'#9b59b6','legendaire'=>'#ff8c00','mythique'=>'#ff2050'];

$pageTitle = 'Gestion des cartes';
require_once '../includes/header.php';
?>

<div class="catalogue-main">

    <h2 class="form-title">
        Gestion des Cartes
        <small style="font-size:0.55em; color:var(--or-texte)"> — Espace Administrateur</small>
    </h2>

    <!-- Messages de succès/erreur globaux -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($erreurs) && !is_array(array_values($erreurs)[0] ?? null)): ?>
        <!-- Erreurs globales (pas d'erreurs par champ) -->
        <?php foreach ($erreurs as $key => $err): ?>
            <?php if (is_numeric($key)): ?>
                <div class="alert alert-error"><?= $err ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ============================================================
         FORMULAIRE D'AJOUT / MODIFICATION
         ============================================================ -->
    <div style="background:linear-gradient(180deg,#1a2040 0%,#0b0d1a 100%);
                border:2px solid var(--or-bord); border-radius:6px; padding:1.5rem 2rem; margin-bottom:2rem">

        <h3 style="font-family:'Cinzel',serif; color:var(--or-eclat); margin-bottom:1.2rem; font-size:1rem">
            <?= $editMode ? '✎ Modifier la carte (ID : ' . $editId . ')' : '+ Ajouter une nouvelle carte' ?>
        </h3>

        <form method="POST" action="cards.php">
            <!-- Champ caché pour l'ID en mode édition -->
            <input type="hidden" name="edit_id" value="<?= $editMode ? $editId : '' ?>">

            <!-- Layout 2 colonnes pour les champs -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem">

                <!-- Nom -->
                <div class="form-group">
                    <label for="name">Nom de la carte <span style="color:#ff4444">*</span></label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($formData['name']) ?>"
                           placeholder="Ex: Iop Guerrier" maxlength="100" required>
                    <?php if (isset($erreurs['name'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['name']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Prix -->
                <div class="form-group">
                    <label for="price">Prix (points) <span style="color:#ff4444">*</span></label>
                    <input type="number" id="price" name="price"
                           value="<?= htmlspecialchars($formData['price']) ?>"
                           min="1" max="99999" placeholder="Ex: 360" required>
                    <?php if (isset($erreurs['price'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['price']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Rareté -->
                <div class="form-group">
                    <label for="rarity">Rareté <span style="color:#ff4444">*</span></label>
                    <select id="rarity" name="rarity" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($rarities as $r): ?>
                            <option value="<?= $r ?>" <?= $formData['rarity'] === $r ? 'selected' : '' ?>>
                                <?= $rarityLabels[$r] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['rarity'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['rarity']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Type -->
                <div class="form-group">
                    <label for="type">Type <span style="color:#ff4444">*</span></label>
                    <select id="type" name="type" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= $formData['type'] === $t ? 'selected' : '' ?>>
                                <?= ucfirst($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['type'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['type']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Nation -->
                <div class="form-group">
                    <label for="nation">Nation <span style="color:#ff4444">*</span></label>
                    <select id="nation" name="nation" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($nations as $n): ?>
                            <option value="<?= $n ?>" <?= $formData['nation'] === $n ? 'selected' : '' ?>>
                                <?= ucfirst($n) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['nation'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['nation']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Stats : HP, ATK, DEF, VIT -->
                <div class="form-group">
                    <label for="hp">PV (Points de Vie) <span style="color:#ff4444">*</span></label>
                    <input type="number" id="hp" name="hp"
                           value="<?= htmlspecialchars($formData['hp']) ?>"
                           min="0" max="999" placeholder="Ex: 80" required>
                    <?php if (isset($erreurs['hp'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['hp']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="attack">ATK (Attaque) <span style="color:#ff4444">*</span></label>
                    <input type="number" id="attack" name="attack"
                           value="<?= htmlspecialchars($formData['attack']) ?>"
                           min="0" max="999" placeholder="Ex: 45" required>
                    <?php if (isset($erreurs['attack'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['attack']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="defense">DEF (Défense) <span style="color:#ff4444">*</span></label>
                    <input type="number" id="defense" name="defense"
                           value="<?= htmlspecialchars($formData['defense']) ?>"
                           min="0" max="999" placeholder="Ex: 10" required>
                    <?php if (isset($erreurs['defense'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['defense']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="speed">VIT (Vitesse) <span style="color:#ff4444">*</span></label>
                    <input type="number" id="speed" name="speed"
                           value="<?= htmlspecialchars($formData['speed']) ?>"
                           min="0" max="99" placeholder="Ex: 7" required>
                    <?php if (isset($erreurs['speed'])): ?>
                        <span class="form-error"><?= htmlspecialchars($erreurs['speed']) ?></span>
                    <?php endif; ?>
                </div>

            </div><!-- fin grid 2 colonnes -->

            <!-- Pouvoir spécial (pleine largeur) -->
            <div class="form-group" style="margin-top:0.5rem">
                <label for="special">Pouvoir spécial</label>
                <input type="text" id="special" name="special"
                       value="<?= htmlspecialchars($formData['special']) ?>"
                       placeholder="Ex: Coup de Jument : double les dégâts une fois par combat"
                       maxlength="200">
            </div>

            <!-- Description (pleine largeur) -->
            <div class="form-group">
                <label for="description">Description (lore)</label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Description narrative de la carte..."><?= htmlspecialchars($formData['description']) ?></textarea>
            </div>

            <!-- Boutons de soumission -->
            <div style="display:flex; gap:1rem; margin-top:0.5rem; flex-wrap:wrap">
                <button type="submit" class="btn-submit" style="flex:none">
                    <?= $editMode ? 'Enregistrer les modifications' : 'Ajouter la carte' ?>
                </button>
                <?php if ($editMode): ?>
                    <a href="cards.php" class="btn-hero">Annuler</a>
                <?php endif; ?>
            </div>

        </form>
    </div>


    <!-- ============================================================
         LISTE DES CARTES AVEC PAGINATION
         ============================================================ -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:0.8rem">
        <h3 style="font-family:'Cinzel',serif; color:var(--or-eclat)">
            Toutes les cartes (<?= $totalCards ?>)
        </h3>

        <!-- Recherche rapide dans la liste -->
        <form method="GET" action="cards.php" style="display:flex; gap:0.5rem">
            <input type="text" name="q" class="search-input" style="max-width:220px"
                   placeholder="Rechercher..." value="<?= htmlspecialchars($searchAdmin) ?>">
            <button type="submit" class="btn-small">Chercher</button>
            <?php if (!empty($searchAdmin)): ?>
                <a href="cards.php" class="btn-small">Tout</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($cards)): ?>
        <p class="empty-msg">Aucune carte trouvée.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Nom</th><th>Rareté</th><th>Type</th><th>Nation</th>
                    <th>PV</th><th>ATK</th><th>DEF</th><th>VIT</th><th>Prix</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cards as $card): ?>
                <tr>
                    <td style="color:#666"><?= $card['id'] ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($card['name']) ?></td>
                    <td>
                        <span style="
                            font-size:0.75rem; padding:0.15rem 0.5rem; border-radius:3px;
                            background:rgba(0,0,0,0.3);
                            border:1px solid <?= $rarityColors[$card['rarity']] ?? '#888' ?>;
                            color:<?= $rarityColors[$card['rarity']] ?? '#888' ?>">
                            <?= $rarityLabels[$card['rarity']] ?? $card['rarity'] ?>
                        </span>
                    </td>
                    <td><?= ucfirst(htmlspecialchars($card['type'])) ?></td>
                    <td><?= ucfirst(htmlspecialchars($card['nation'])) ?></td>
                    <td style="color:#2ecc40"><?= $card['hp'] ?></td>
                    <td style="color:#ff4444"><?= $card['attack'] ?></td>
                    <td style="color:#3a7aff"><?= $card['defense'] ?></td>
                    <td style="color:#ff8c00"><?= $card['speed'] ?></td>
                    <td><?= number_format($card['price'], 0, ',', ' ') ?></td>
                    <td style="white-space:nowrap">
                        <!-- Bouton modifier -->
                        <a href="cards.php?action=edit&id=<?= $card['id'] ?>" class="btn-small">
                            Modifier
                        </a>
                        <!-- Bouton supprimer avec confirmation -->
                        <a href="cards.php?action=delete&id=<?= $card['id'] ?>"
                           class="btn-small btn-danger"
                           style="margin-left:0.3rem"
                           onclick="return confirm('Supprimer la carte &laquo;<?= htmlspecialchars($card['name'], ENT_QUOTES) ?>&raquo; ?\nCette action supprimera aussi la carte de toutes les collections.')">
                            Supprimer
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:1.5rem; flex-wrap:wrap">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="cards.php?page=<?= $p ?><?= !empty($searchAdmin) ? '&q='.urlencode($searchAdmin) : '' ?>"
                       class="nav-btn <?= $p === $page ? 'active' : '' ?>"
                       style="padding:0.3rem 0.8rem; font-size:0.8rem">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
            <p style="text-align:center; color:var(--or-texte); font-size:0.85rem; margin-top:0.5rem">
                Page <?= $page ?> / <?= $totalPages ?> — <?= $totalCards ?> cartes au total
            </p>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Navigation admin -->
    <div style="display:flex; gap:1rem; margin-top:2rem; flex-wrap:wrap">
        <a href="index.php"  class="btn-hero">Dashboard Admin</a>
        <a href="users.php"  class="btn-hero">Gérer les utilisateurs</a>
        <a href="../catalogue.php" class="btn-hero">Voir le catalogue</a>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
