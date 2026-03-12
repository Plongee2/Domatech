<?php
// ============================================================
// index.php — Page d'accueil
// Si connecté → redirige vers dashboard.php
// Sinon → présentation du jeu avec stats
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirige les utilisateurs déjà connectés vers leur tableau de bord
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// Statistiques globales pour la page d'accueil
$totalCartes  = count_query($pdo, "SELECT COUNT(*) FROM cards");
$totalJoueurs = count_query($pdo, "SELECT COUNT(*) FROM users");
$totalCombats = count_query($pdo, "SELECT COUNT(*) FROM combats");

$pageTitle = 'Accueil';
$rootPath  = '';
require_once 'includes/header.php';
?>

<!-- ============================================================
     SECTION HÉRO — Présentation principale du jeu
     ============================================================ -->
<section class="hero">
    <div class="hero-content">
        <h2 class="hero-title">PafAlan</h2>
        <p class="hero-sub">Jeu de Cartes à Collectionner</p>
        <p class="hero-sub hero-sub--gold">Univers Dofus & Wakfu</p>

        <div class="hero-visual">
            <p>
                Partez à l'aventure dans le monde des Douze.<br>
                Collectionnez des héros légendaires — Iop, Xelor, Sacrieur et bien d'autres —,
                constituez des decks redoutables et affrontez l'IA en combat épique.<br><br>
                <strong style="color:var(--or-eclat)">50 cartes uniques · 3 types · 6 niveaux de rareté</strong>
            </p>
        </div>

        <!-- Boutons d'appel à l'action -->
        <div class="hero-actions">
            <a href="register.php" class="btn-hero">S'inscrire gratuitement</a>
            <a href="login.php"    class="btn-hero">Se connecter</a>
            <a href="catalogue.php" class="btn-hero">Voir le catalogue</a>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION STATS — Chiffres clés du jeu
     ============================================================ -->
<section class="stats-section">
    <div class="stats-grid">

        <div class="stat-card">
            <span class="stat-number"><?= $totalCartes ?></span>
            <span class="stat-label">Cartes</span>
            <p class="stat-desc">Des communes aux mythiques, 50 cartes uniques à collectionner.</p>
        </div>

        <div class="stat-card">
            <span class="stat-number">6</span>
            <span class="stat-label">Raretés</span>
            <p class="stat-desc">Commune, Peu commune, Rare, Épique, Légendaire, Mythique.</p>
        </div>

        <div class="stat-card">
            <span class="stat-number"><?= $totalJoueurs ?></span>
            <span class="stat-label">Joueurs inscrits</span>
            <p class="stat-desc">Rejoignez la communauté et commencez à collectionner.</p>
        </div>

        <div class="stat-card">
            <span class="stat-number">500</span>
            <span class="stat-label">Points de départ</span>
            <p class="stat-desc">Chaque nouveau joueur reçoit 500 points et 25 cartes de départ.</p>
        </div>

    </div>
</section>

<!-- ============================================================
     SECTION NATIONS — Présentation des 3 nations principales
     ============================================================ -->
<section class="stats-section">

    <div class="hero-content" style="margin-bottom:0">
        <h2 class="hero-title" style="font-size:1.4rem">Les Nations</h2>
        <p class="hero-sub">Choisissez votre camp dans le monde des Douze</p>
    </div>

    <div class="stats-grid" style="margin-top:1rem">

        <div class="stat-card">
            <span class="stat-number" style="color:#c9a227">Bonta</span>
            <span class="stat-label">La Cité de la Lumière</span>
            <p class="stat-desc">
                Nation de la justice et de l'ordre. Ses héros se spécialisent dans la défense
                et les soins. Portés par la lumière sacrée.
            </p>
        </div>

        <div class="stat-card">
            <span class="stat-number" style="color:#cc2020">Brakmar</span>
            <span class="stat-label">La Cité des Ténèbres</span>
            <p class="stat-desc">
                Nation du chaos et de la corruption. Ses guerriers misent tout sur l'attaque
                brutale et les malédictions dévastatrices.
            </p>
        </div>

        <div class="stat-card">
            <span class="stat-number" style="color:#2ecc40">Amakna</span>
            <span class="stat-label">Le Royaume Verdoyant</span>
            <p class="stat-desc">
                Cœur du monde, terre de nature et d'équilibre. Ses champions sont
                polyvalents, puisant leur force dans la nature.
            </p>
        </div>

        <div class="stat-card">
            <span class="stat-number" style="color:#3a7aff">Frigost</span>
            <span class="stat-label">L'Île Maudite</span>
            <p class="stat-desc">
                Île de glace éternelle. Ses habitants ont développé des pouvoirs extrêmes
                pour survivre — rapidité et magie temporelle.
            </p>
        </div>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
