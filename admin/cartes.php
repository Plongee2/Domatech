<?php
// ============================================================
// admin/cartes.php — Gestion des cartes (CRUD) — Espace admin
// ============================================================
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Protection : seul l'admin peut accéder ici
requireRole('admin', '../');

// ---- Variables du formulaire (conservées en cas d'erreur) ----
$nom         = '';
$type        = '';
$rarete      = '';
$description = '';
$prix        = '';
$erreurs     = [];
$succes      = '';
$editMode    = false;  // true = on modifie une carte existante
$editId      = null;

// ---- MODE ÉDITION : charge les données de la carte à modifier ----
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editMode = true;
    $editId   = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM cartes WHERE id = ?");
    $stmt->execute([$editId]);
    $carte = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($carte) {
        // Pré-remplit le formulaire avec les données existantes
        $nom         = $carte['nom'];
        $type        = $carte['type'];
        $rarete      = $carte['rarete'];
        $description = $carte['description'];
        $prix        = $carte['prix'];
    }
}

// ---- SUPPRESSION d'une carte ----
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM cartes WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: cartes.php?succes=supprimee");
    exit;
}

// ---- TRAITEMENT DU FORMULAIRE (ajout ou modification) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère les données du formulaire
    $nom         = trim($_POST['nom'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $rarete      = trim($_POST['rarete'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix        = trim($_POST['prix'] ?? '');
    $editId      = isset($_POST['edit_id']) && $_POST['edit_id'] !== '' ? (int)$_POST['edit_id'] : null;
    $editMode    = ($editId !== null);

    // ---- VALIDATION ----
    if (empty($nom)) {
        $erreurs['nom'] = "Le nom de la carte est obligatoire.";
    }
    if (empty($type) || !in_array($type, ['personnage', 'equipement', 'ville'])) {
        $erreurs['type'] = "Veuillez choisir un type valide.";
    }
    if (empty($rarete) || !in_array($rarete, ['commune','peu-commune','rare','epique','legendaire','mythique'])) {
        $erreurs['rarete'] = "Veuillez choisir une rareté valide.";
    }
    if ($prix === '' || !is_numeric($prix) || (int)$prix <= 0) {
        $erreurs['prix'] = "Le prix doit être un nombre positif.";
    }

    // Si la validation est OK, on insère ou modifie
    if (empty($erreurs)) {
        $prix = (int)$prix;

        if ($editMode) {
            // MODIFICATION d'une carte existante
            $stmt = $pdo->prepare(
                "UPDATE cartes SET nom=?, type=?, rarete=?, description=?, prix=? WHERE id=?"
            );
            $stmt->execute([$nom, $type, $rarete, $description, $prix, $editId]);
            header("Location: cartes.php?succes=modifiee");
        } else {
            // CRÉATION d'une nouvelle carte
            $stmt = $pdo->prepare(
                "INSERT INTO cartes (nom, type, rarete, description, prix) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nom, $type, $rarete, $description, $prix]);
            header("Location: cartes.php?succes=ajoutee");
        }
        exit;
    }
}

// ---- Récupère toutes les cartes pour l'affichage ----
$stmt = $pdo->prepare("SELECT * FROM cartes ORDER BY created_at DESC");
$stmt->execute();
$cartes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gérer les cartes';
$rootPath  = '../';
require_once '../includes/header.php';
?>

<div class="catalogue-main">

    <h2 class="form-title">
        <?= $editMode ? 'Modifier la carte' : 'Ajouter une carte' ?>
        <small style="font-size:0.6em; color:var(--or-texte);">— Espace Administrateur</small>
    </h2>

    <!-- Messages de succès -->
    <?php if (isset($_GET['succes'])): ?>
        <div class="alert alert-success">
            <?php
            $msgs = ['ajoutee' => 'Carte ajoutée !', 'modifiee' => 'Carte modifiée !', 'supprimee' => 'Carte supprimée !'];
            echo $msgs[$_GET['succes']] ?? 'Opération réussie !';
            ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         FORMULAIRE DE CRÉATION / MODIFICATION
         TP – Exigence : "Un formulaire de création" + "Un formulaire de modification"
         ============================================================ -->
    <div class="form-container" style="max-width:600px; margin-bottom:2rem">
        <form method="POST" action="cartes.php">

            <!-- Champ caché : contient l'ID si on est en mode édition -->
            <input type="hidden" name="edit_id" value="<?= $editMode ? $editId : '' ?>">

            <!-- Nom -->
            <div class="form-group">
                <label for="nom">Nom de la carte</label>
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($nom) ?>"
                       placeholder="Ex: Iop, Cra, Bonta..." required>
                <?php if (isset($erreurs['nom'])): ?>
                    <span class="form-error"><?= htmlspecialchars($erreurs['nom']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Type -->
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" required>
                    <option value="">-- Choisir --</option>
                    <option value="personnage" <?= $type === 'personnage' ? 'selected' : '' ?>>Personnage</option>
                    <option value="equipement" <?= $type === 'equipement' ? 'selected' : '' ?>>Équipement</option>
                    <option value="ville"      <?= $type === 'ville'      ? 'selected' : '' ?>>Ville</option>
                </select>
                <?php if (isset($erreurs['type'])): ?>
                    <span class="form-error"><?= htmlspecialchars($erreurs['type']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Rareté -->
            <div class="form-group">
                <label for="rarete">Rareté</label>
                <select id="rarete" name="rarete" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach (['commune','peu-commune','rare','epique','legendaire','mythique'] as $r): ?>
                        <option value="<?= $r ?>" <?= $rarete === $r ? 'selected' : '' ?>>
                            <?= ucfirst($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erreurs['rarete'])): ?>
                    <span class="form-error"><?= htmlspecialchars($erreurs['rarete']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Décrivez la carte..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- Prix -->
            <div class="form-group">
                <label for="prix">Prix (en kamas)</label>
                <input type="number" id="prix" name="prix"
                       value="<?= htmlspecialchars($prix) ?>"
                       min="1" placeholder="Ex: 250" required>
                <?php if (isset($erreurs['prix'])): ?>
                    <span class="form-error"><?= htmlspecialchars($erreurs['prix']) ?></span>
                <?php endif; ?>
            </div>

            <div style="display:flex; gap:1rem">
                <button type="submit" class="btn-submit">
                    <?= $editMode ? 'Enregistrer les modifications' : 'Ajouter la carte' ?>
                </button>
                <?php if ($editMode): ?>
                    <a href="cartes.php" class="btn-hero btn-hero--outline">Annuler</a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- ============================================================
         LISTE DE TOUTES LES CARTES
         TP – Exigence : "Un affichage en liste (tableau HTML) avec les données de la BDD"
         ============================================================ -->
    <h3 class="section-title">Toutes les cartes (<?= count($cartes) ?>)</h3>

    <?php if (empty($cartes)): ?>
        <p class="empty-msg">Aucune carte dans le catalogue. Ajoutez-en une !</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Rareté</th>
                    <th>Prix</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cartes as $carte): ?>
                <tr>
                    <td><?= $carte['id'] ?></td>
                    <td><?= htmlspecialchars($carte['nom']) ?></td>
                    <td><?= htmlspecialchars($carte['type']) ?></td>
                    <td>
                        <span class="rarete-badge rarete-<?= htmlspecialchars($carte['rarete']) ?>">
                            <?= htmlspecialchars($carte['rarete']) ?>
                        </span>
                    </td>
                    <td><?= $carte['prix'] ?> kamas</td>
                    <td style="display:flex; gap:0.5rem">
                        <!-- Bouton modifier -->
                        <a href="cartes.php?action=edit&id=<?= $carte['id'] ?>" class="btn-small">
                            Modifier
                        </a>
                        <!-- Bouton supprimer avec confirmation JavaScript -->
                        <!-- TP – Exigence : "Suppression avec confirmation" -->
                        <a href="cartes.php?action=delete&id=<?= $carte['id'] ?>"
                           class="btn-small btn-danger"
                           onclick="return confirm('Supprimer la carte <?= htmlspecialchars($carte['nom'], ENT_QUOTES) ?> ? Cette action est irréversible.')">
                            Supprimer
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
