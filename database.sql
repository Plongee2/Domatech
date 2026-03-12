-- ============================================================
-- database.sql — Script d'initialisation de la base PafAlan
-- Jeu de cartes thématique Dofus/Wakfu
--
-- UTILISATION :
--   1. Ouvrir phpMyAdmin
--   2. Aller dans l'onglet "SQL"
--   3. Coller ce fichier et cliquer "Exécuter"
--
-- OU via la ligne de commande :
--   mysql -u root pafalan < database.sql
-- ============================================================

-- Création de la base si elle n'existe pas encore
CREATE DATABASE IF NOT EXISTS pafalan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pafalan;

-- Supprime les tables existantes (ordre inversé pour les clés étrangères)
DROP TABLE IF EXISTS friends;
DROP TABLE IF EXISTS combats;
DROP TABLE IF EXISTS deck_cards;
DROP TABLE IF EXISTS decks;
DROP TABLE IF EXISTS user_cards;
DROP TABLE IF EXISTS cards;
DROP TABLE IF EXISTS users;


-- ============================================================
-- TABLE users — Comptes utilisateurs
-- ============================================================
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- 3 rôles : admin (tout gérer), joueur (collectionner), visiteur (consulter)
    role          ENUM('admin','joueur','visiteur') NOT NULL DEFAULT 'visiteur',
    -- Points = monnaie du jeu pour acheter des cartes
    points        INT NOT NULL DEFAULT 500,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE cards — Catalogue des 50 cartes
-- ============================================================
CREATE TABLE cards (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    rarity      ENUM('commune','peu-commune','rare','epique','legendaire','mythique') NOT NULL,
    type        ENUM('personnage','equipement','ville') NOT NULL,
    nation      ENUM('bonta','brakmar','astrub','amakna','frigost') NOT NULL,
    -- Stats de combat
    hp          INT NOT NULL DEFAULT 0,   -- Points de vie
    attack      INT NOT NULL DEFAULT 0,   -- Attaque
    defense     INT NOT NULL DEFAULT 0,   -- Défense
    speed       INT NOT NULL DEFAULT 0,   -- Vitesse (détermine qui frappe en premier)
    -- Pouvoir spécial de la carte
    special     VARCHAR(200) NOT NULL DEFAULT '',
    description TEXT,
    -- Prix en points pour acheter la carte
    price       INT NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE user_cards — Collection de cartes de chaque joueur
-- Une ligne = un joueur possède une carte
-- ============================================================
CREATE TABLE user_cards (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    card_id     INT NOT NULL,
    acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE decks — Decks créés par les joueurs (max 25 cartes)
-- ============================================================
CREATE TABLE decks (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    name       VARCHAR(50) NOT NULL DEFAULT 'Mon Deck',
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE deck_cards — Cartes contenues dans un deck
-- ============================================================
CREATE TABLE deck_cards (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    deck_id INT NOT NULL,
    card_id INT NOT NULL,
    FOREIGN KEY (deck_id) REFERENCES decks(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE combats — Historique des combats PvE contre l'IA
-- ============================================================
CREATE TABLE combats (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    player_id      INT         NOT NULL,
    opponent_name  VARCHAR(50) NOT NULL DEFAULT 'IA Gardienne',
    player_score   INT         NOT NULL DEFAULT 0,  -- HP restants côté joueur
    opponent_score INT         NOT NULL DEFAULT 0,  -- HP restants côté IA
    winner         ENUM('player','opponent','draw') NOT NULL DEFAULT 'draw',
    rounds_json    TEXT,           -- Détail des rounds en JSON (pour replay)
    played_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE friends — Système d'amis entre joueurs
-- ============================================================
CREATE TABLE friends (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,    -- Celui qui envoie la demande
    friend_id  INT NOT NULL,    -- Celui qui reçoit la demande
    status     ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- DONNÉES : LES 50 CARTES DU JEU
-- ============================================================

-- ---- PERSONNAGES (cartes 1 à 20) ----
-- Les personnages sont les unités de combat principale
INSERT INTO cards (id, name, rarity, type, nation, hp, attack, defense, speed, special, description, price) VALUES

(1,  'Iop Guerrier',
     'epique', 'personnage', 'amakna',
     80, 45, 10, 7,
     'Coup de Jument : double les dégâts une fois par combat',
     'Guerrier fougueux d\'Amakna, maître de la force brute. Son énergie au combat est légendaire.',
     360),

(2,  'Sacrieur Maudit',
     'rare', 'personnage', 'brakmar',
     90, 35, 15, 5,
     'Punition : gagne ATK quand il perd des PV',
     'Plus il souffre, plus il devient dangereux. Ce guerrier de Brakmar transforme sa douleur en puissance.',
     120),

(3,  'Crâ Tireur d\'Élite',
     'rare', 'personnage', 'bonta',
     65, 40, 8, 8,
     'Flèche Explosive : attaque toutes les cartes ennemies',
     'Archer de précision formé dans les forêts de Bonta. Sa flèche explosive dévaste les lignes ennemies.',
     120),

(4,  'Eniripsa Guérisseuse',
     'peu-commune', 'personnage', 'bonta',
     75, 20, 20, 6,
     'Soins Divins : restaure 15 PV à une carte alliée',
     'Guérisseuse dévouée, gardienne de la lumière de Bonta. Elle maintient ses alliés en vie.',
     36),

(5,  'Roublard Artificier',
     'epique', 'personnage', 'astrub',
     55, 50, 5, 9,
     'Mise à Feu : ignore la défense adverse',
     'Explosif et imprévisible, roi des bombes d\'Astrub. Ses attaques percent les armures les plus solides.',
     360),

(6,  'Osamodas Invocateur',
     'legendaire', 'personnage', 'amakna',
     85, 40, 25, 6,
     'Invocation : convoque un dragon pour 1 round',
     'Maître des créatures, il commande même les dragons. Son dragon invoqué dévaste tout sur son passage.',
     960),

(7,  'Féca Gardien',
     'commune', 'personnage', 'bonta',
     70, 15, 35, 4,
     'Bouclier Glyphique : absorbe les 10 premiers dégâts',
     'Protecteur stoïque de Bonta, il veille sur ses alliés. Son bouclier glyphique est presque impénétrable.',
     10),

(8,  'Xelor Chronomage',
     'mythique', 'personnage', 'frigost',
     75, 55, 20, 10,
     'Retour dans le Temps : annule l\'attaque adverse',
     'Maître du temps, capable de réécrire l\'issue d\'un round. Nul ne peut prévoir ses manipulations temporelles.',
     3000),

(9,  'Sram Ombre',
     'rare', 'personnage', 'brakmar',
     60, 42, 8, 9,
     'Double Sram : crée un clone qui attaque aussi',
     'Assassin tapi dans l\'ombre de Brakmar. Son clone duplique chaque attaque, semant la confusion.',
     120),

(10, 'Ecaflip Chanceux',
     'legendaire', 'personnage', 'brakmar',
     72, 45, 12, 8,
     'Pile ou Face : 50% de chance de doubler les dégâts',
     'La chance est sa seule loi. Parfois il frappe double, parfois il rate — mais il sourit toujours.',
     960),

(11, 'Sadida Sylvestre',
     'rare', 'personnage', 'amakna',
     80, 30, 18, 5,
     'Poupées de Cire : invoque des poupées défensives',
     'Druide des bois d\'Amakna, il parle aux plantes et aux bêtes. Ses poupées protègent l\'équipe.',
     120),

(12, 'Pandawa Ivre',
     'commune', 'personnage', 'amakna',
     85, 25, 20, 4,
     'Lancer de Tonneau : étourdit l\'ennemi 1 round',
     'Toujours entre deux verres, mais redoutable au combat. Son tonneau lancé étourdit les adversaires.',
     10),

(13, 'Zobal des Masques',
     'epique', 'personnage', 'astrub',
     78, 38, 22, 7,
     'Masque de la Mort : copie les stats de l\'ennemi',
     'Derrière chaque masque se cache une autre identité. Il adopte les forces de son adversaire.',
     360),

(14, 'Steamer Mécanicien',
     'rare', 'personnage', 'frigost',
     70, 35, 25, 6,
     'Tourelle Auto : inflige 10 dégâts fixes chaque round',
     'Ingénieur de génie de Frigost, ses machines font le travail. Sa tourelle automatique harcèle sans relâche.',
     120),

(15, 'Huppermage Arcane',
     'mythique', 'personnage', 'bonta',
     70, 60, 15, 9,
     'Magie Runique : +10 ATK par rune active',
     'Le plus puissant des mages, maître des 4 éléments. Ses runes amplifient sa puissance à chaque round.',
     3000),

(16, 'Ouginak Chasseur',
     'peu-commune', 'personnage', 'amakna',
     65, 30, 10, 8,
     'Meute : +5 ATK pour chaque carte personnage alliée',
     'Chasseur tenace d\'Amakna, il traque sa proie sans relâche. Sa meute le rend plus fort.',
     36),

(17, 'Eliotrope Portail',
     'epique', 'personnage', 'brakmar',
     70, 42, 15, 8,
     'Renvoi : retourne l\'attaque adverse sur l\'ennemi',
     'Maître des portails de Brakmar, il retourne les attaques sur leurs auteurs.',
     360),

(18, 'Forgelance Paladin',
     'commune', 'personnage', 'brakmar',
     80, 20, 30, 3,
     'Aura Sacrée : réduit de 5 tous les dégâts reçus',
     'Paladin incorruptible malgré ses origines de Brakmar. Son aura réduit tous les dégâts reçus.',
     10),

(19, 'Rogue Artificier',
     'peu-commune', 'personnage', 'astrub',
     60, 35, 8, 7,
     'Bombe à Retardement : explose après 2 rounds',
     'Virtuose des explosifs d\'Astrub, il joue avec le feu. Sa bombe à retardement surprend toujours.',
     36),

(20, 'Masqueraider Danseur',
     'commune', 'personnage', 'astrub',
     70, 22, 18, 7,
     'Danse des Masques : change aléatoirement ses stats',
     'Danseur insaisissable d\'Astrub, ses mouvements déroutent l\'adversaire à chaque round.',
     10);

-- ---- ÉQUIPEMENTS (cartes 21 à 35) ----
-- Les équipements ajoutent des bonus aux stats de combat
INSERT INTO cards (id, name, rarity, type, nation, hp, attack, defense, speed, special, description, price) VALUES

(21, 'Épée d\'Iop',
     'rare', 'equipement', 'amakna',
     0, 20, 0, 0,
     '+20 ATK à toute l\'équipe',
     'Lame légendaire forgée pour les guerriers d\'Amakna. Elle renforce toute l\'équipe.',
     100),

(22, 'Arc de Crâ',
     'peu-commune', 'equipement', 'bonta',
     0, 15, 0, 2,
     '+15 ATK, +2 SPD',
     'Arc elfique d\'une précision redoutable. Il améliore l\'attaque et la rapidité.',
     30),

(23, 'Bâton d\'Eniripsa',
     'commune', 'equipement', 'bonta',
     10, 5, 5, 0,
     '+10 PV, +5 ATK, +5 DEF',
     'Bâton guérisseur béni par les prêtres de Bonta. Améliore équitablement toutes les stats.',
     10),

(24, 'Dagues du Sram',
     'epique', 'equipement', 'brakmar',
     0, 25, 0, 3,
     '+25 ATK, +3 SPD, ignore 5 DEF',
     'Lames empoisonnées forgées dans les forges de Brakmar. Très offensives.',
     300),

(25, 'Masques de Zobal',
     'rare', 'equipement', 'astrub',
     5, 10, 10, 0,
     '+5 PV, +10 ATK, +10 DEF',
     'Collection de masques aux pouvoirs variés. Équilibre attaque et défense.',
     100),

(26, 'Hache du Sacrieur',
     'commune', 'equipement', 'brakmar',
     0, 12, 0, 0,
     '+12 ATK',
     'Hache lourde imprégnée de la douleur du Sacrieur. Simple mais efficace.',
     10),

(27, 'Sablier de Xelor',
     'legendaire', 'equipement', 'frigost',
     0, 15, 5, 5,
     '+15 ATK, +5 DEF, +5 SPD',
     'Sablier mystique qui manipule le flux du temps. Améliore attaque, défense et vitesse.',
     800),

(28, 'Bombes du Roublard',
     'peu-commune', 'equipement', 'astrub',
     0, 18, 0, 0,
     '+18 ATK à l\'ouverture',
     'Explosifs artisanaux d\'une puissance surprenante. Dévastateurs au premier round.',
     30),

(29, 'Livre du Sadida',
     'commune', 'equipement', 'amakna',
     15, 0, 8, 0,
     '+15 PV, +8 DEF',
     'Grimoire druidique plein de sagesse végétale. Renforce la résistance.',
     10),

(30, 'Cartes de l\'Ecaflip',
     'mythique', 'equipement', 'brakmar',
     10, 30, 0, 2,
     '+30 ATK, 50% chance de critiquer',
     'Jeu de cartes enchanté aux pouvoirs imprévisibles. Puissance offensive maximale.',
     2500),

(31, 'Bambou du Pandawa',
     'commune', 'equipement', 'amakna',
     20, 0, 5, 0,
     '+20 PV, +5 DEF',
     'Bâton en bambou solide comme l\'acier. Idéal pour encaisser les coups.',
     10),

(32, 'Engrenages du Steamer',
     'rare', 'equipement', 'frigost',
     0, 10, 15, 0,
     '+10 ATK, +15 DEF mécanique',
     'Pièces mécaniques qui renforcent les défenses. Solides comme des machines de Frigost.',
     100),

(33, 'Bâton de Féca',
     'peu-commune', 'equipement', 'bonta',
     0, 0, 20, 0,
     '+20 DEF globale',
     'Sceptre protecteur des gardiens de Bonta. Maximum de défense.',
     30),

(34, 'Griffes de l\'Ouginak',
     'rare', 'equipement', 'amakna',
     0, 22, 0, 1,
     '+22 ATK, +1 SPD',
     'Griffes acérées taillées dans l\'os de grand gibier. Offensives et rapides.',
     100),

(35, 'Portail de l\'Eliotrope',
     'epique', 'equipement', 'brakmar',
     0, 18, 10, 4,
     '+18 ATK, +10 DEF, +4 SPD',
     'Portail dimensionnel qui désoriente les ennemis. Améliore attaque, défense et vitesse.',
     300);

-- ---- VILLES (cartes 36 à 50) ----
-- Les villes apportent des bonus de terrain à l'équipe
INSERT INTO cards (id, name, rarity, type, nation, hp, attack, defense, speed, special, description, price) VALUES

(36, 'Bonta la Lumineuse',
     'mythique', 'ville', 'bonta',
     100, 30, 40, 2,
     'Terrain Sacré : +10 DEF à toute l\'équipe de Bonta',
     'Cité de la lumière et de la justice. Son terrain sacré protège tous les défenseurs de Bonta.',
     2500),

(37, 'Brakmar la Maudite',
     'legendaire', 'ville', 'brakmar',
     90, 40, 30, 3,
     'Terrain Maudit : -5 DEF aux ennemis',
     'Ville des ténèbres et de la corruption. Sa malédiction affaiblit tous les adversaires.',
     800),

(38, 'Astrub la Marchande',
     'rare', 'ville', 'astrub',
     70, 20, 30, 5,
     'Marché : gagne 50 pts supplémentaires si victoire',
     'Carrefour commercial du monde de Dofus. La victoire ici rapporte des ressources supplémentaires.',
     100),

(39, 'Amakna la Verdoyante',
     'legendaire', 'ville', 'amakna',
     85, 25, 35, 3,
     'Nature : régénère 5 PV par round',
     'Royaume verdoyant au cœur du monde. La nature régénère les combattants à chaque round.',
     800),

(40, 'Frigost la Gelée',
     'epique', 'ville', 'frigost',
     80, 35, 25, 4,
     'Blizzard : -2 SPD à tous les ennemis',
     'Île maudite figée dans l\'éternel hiver. Le blizzard permanent ralentit tous les adversaires.',
     300),

(41, 'Incarnam l\'Initiale',
     'commune', 'ville', 'amakna',
     50, 10, 15, 5,
     'Tutoriel : +10 PV aux nouvelles cartes',
     'Village des nouveaux héros, premier pas vers l\'aventure. Idéal pour débuter sa collection.',
     10),

(42, 'Temple des Crâs',
     'peu-commune', 'ville', 'bonta',
     55, 15, 20, 4,
     '+5 ATK aux archers (Crâ)',
     'Temple sacré où les archers parfaisent leur art. Bonus aux cartes Crâ dans le deck.',
     30),

(43, 'Mines de Brakmar',
     'commune', 'ville', 'brakmar',
     60, 20, 10, 3,
     'Extraction : +10 ATK aux équipements',
     'Mines profondes regorgeant de minerais maudits. Amplifie la puissance des équipements.',
     10),

(44, 'Port d\'Astrub',
     'peu-commune', 'ville', 'astrub',
     65, 12, 18, 6,
     'Commerce : réduit le coût des équipements de 10%',
     'Port animé, point de départ de nombreuses aventures. Facilite l\'accès aux équipements.',
     30),

(45, 'Forêt de Sadida',
     'rare', 'ville', 'amakna',
     75, 15, 30, 2,
     'Havre Vert : +15 PV aux personnages nature',
     'Forêt enchantée gardée par les Sadidas. Régénère les personnages proches de la nature.',
     100),

(46, 'Tour de Xelor',
     'epique', 'ville', 'frigost',
     70, 28, 22, 5,
     'Distorsion : inverse l\'ordre d\'initiative',
     'Tour mystérieuse où le temps s\'écoule différemment. Perturbe l\'ordre d\'attaque ennemi.',
     300),

(47, 'Cathédrale de Bonta',
     'rare', 'ville', 'bonta',
     80, 10, 38, 2,
     '+20 DEF à tous les alliés de Bonta',
     'Édifice sacré au cœur de la cité de la lumière. Renforce massivement la défense des alliés.',
     100),

(48, 'Arène d\'Astrub',
     'commune', 'ville', 'astrub',
     50, 25, 15, 5,
     'Combat à mort : +15 ATK en phase finale',
     'Arène où les aventuriers prouvent leur valeur. L\'atmosphère de combat booste les attaquants.',
     10),

(49, 'Toundra de Frigost',
     'peu-commune', 'ville', 'frigost',
     60, 18, 22, 4,
     'Gel : réduit l\'ATK ennemie de 5',
     'Plaines glacées où peu survivent. Le gel permanent réduit l\'offensive des adversaires.',
     30),

(50, 'Taverne du Pandawa',
     'commune', 'ville', 'amakna',
     55, 15, 15, 6,
     'Festin : restaure 10 PV entre les rounds',
     'Taverne chaleureuse où les héros se ressourcent. Le festin perpétuel restaure les combattants.',
     10);


-- ============================================================
-- DONNÉES : UTILISATEURS DE TEST
--
-- Mot de passe pour tous les comptes : "motdepasse123"
-- Hash généré avec : password_hash("motdepasse123", PASSWORD_BCRYPT)
-- Ce hash est valide en PHP avec password_verify()
-- ============================================================
INSERT INTO users (id, nom, email, password_hash, role, points, created_at) VALUES
(1, 'Administrateur', 'admin@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin', 9999, NOW()),

(2, 'JoueurTest', 'user@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'joueur', 1500, NOW()),

(3, 'Visiteur', 'visiteur@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'visiteur', 0, NOW());


-- ============================================================
-- COLLECTION DE DÉPART : JoueurTest (id=2) possède 25 cartes
-- Ce sont les cartes du "Starter Pack" données à l'inscription
-- Cartes IDs : 1,2,3,6,7,8,10,12,15,17,19,21,23,26,27,29,32,33,35,37,39,41,43,45,48
-- ============================================================
INSERT INTO user_cards (user_id, card_id) VALUES
(2, 1),   -- Iop Guerrier (épique)
(2, 2),   -- Sacrieur Maudit (rare)
(2, 3),   -- Crâ Tireur d'Élite (rare)
(2, 6),   -- Osamodas Invocateur (légendaire)
(2, 7),   -- Féca Gardien (commune)
(2, 8),   -- Xelor Chronomage (mythique)
(2, 10),  -- Ecaflip Chanceux (légendaire)
(2, 12),  -- Pandawa Ivre (commune)
(2, 15),  -- Huppermage Arcane (mythique)
(2, 17),  -- Eliotrope Portail (épique)
(2, 19),  -- Rogue Artificier (peu-commune)
(2, 21),  -- Épée d'Iop (rare équipement)
(2, 23),  -- Bâton d'Eniripsa (commune équipement)
(2, 26),  -- Hache du Sacrieur (commune équipement)
(2, 27),  -- Sablier de Xelor (légendaire équipement)
(2, 29),  -- Livre du Sadida (commune équipement)
(2, 32),  -- Engrenages du Steamer (rare équipement)
(2, 33),  -- Bâton de Féca (peu-commune équipement)
(2, 35),  -- Portail de l'Eliotrope (épique équipement)
(2, 37),  -- Brakmar la Maudite (légendaire ville)
(2, 39),  -- Amakna la Verdoyante (légendaire ville)
(2, 41),  -- Incarnam l'Initiale (commune ville)
(2, 43),  -- Mines de Brakmar (commune ville)
(2, 45),  -- Forêt de Sadida (rare ville)
(2, 48);  -- Arène d'Astrub (commune ville)
