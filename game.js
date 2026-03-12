/* ============================================================
   game.js – Données et état global PafAlan
   ============================================================ */
const Game = {

    CARDS: [
        // Personnages – Bonta
        { name: 'Alibert',       rarity: 'commune',    type: 'personnage', nation: 'bonta',   atk: 2, def: 3, hp: 8,  lore: 'Aubergiste et père adoptif du héros légendaire.' },
        { name: 'Evangelyne',   rarity: 'rare',       type: 'personnage', nation: 'bonta',   atk: 5, def: 4, hp: 12, lore: 'Crâ aux flèches infaillibles, gardienne du Brâkmarienne.' },
        { name: 'Amalia',       rarity: 'epique',     type: 'personnage', nation: 'bonta',   atk: 4, def: 5, hp: 14, lore: 'Princesse Sadida, en communion avec la nature.' },
        { name: 'Yugo',         rarity: 'legendaire', type: 'personnage', nation: 'bonta',   atk: 7, def: 5, hp: 18, lore: 'Le Roi des Eliotropes, maître des portails Xélor.' },
        { name: 'Ruel Stroud',  rarity: 'peu-commune',type: 'personnage', nation: 'bonta',   atk: 3, def: 2, hp: 9,  lore: 'Iop avare mais courageux, ami fidèle de Yugo.' },
        { name: 'Nox',          rarity: 'mythique',   type: 'personnage', nation: 'bonta',   atk: 9, def: 7, hp: 25, lore: 'Xélor déchu cherchant à remonter le temps.' },

        // Personnages – Brakmar
        { name: 'Remington',    rarity: 'rare',       type: 'personnage', nation: 'brakmar', atk: 6, def: 3, hp: 11, lore: 'Sram pistolero, chasseur de primes redouté.' },
        { name: 'Grufon',       rarity: 'commune',    type: 'personnage', nation: 'brakmar', atk: 2, def: 2, hp: 7,  lore: 'Sbire de Brakmar, soldat de l\'ombre.' },
        { name: 'Qilby',        rarity: 'mythique',   type: 'personnage', nation: 'brakmar', atk: 8, def: 8, hp: 22, lore: 'Eliatrope traître, gardien de la mémoire corrompue.' },
        { name: 'Kriss Krass',  rarity: 'epique',     type: 'personnage', nation: 'brakmar', atk: 7, def: 4, hp: 16, lore: 'Champion de l\'arène, Iop brutal et fier.' },
        { name: 'Bwork Mage',   rarity: 'peu-commune',type: 'personnage', nation: 'brakmar', atk: 4, def: 2, hp: 8,  lore: 'Petit sorcier Bwork, explosif malgré sa taille.' },
        { name: 'Vampyro',      rarity: 'legendaire', type: 'personnage', nation: 'brakmar', atk: 6, def: 6, hp: 20, lore: 'Seigneur des ombres, drainant l\'essence vitale.' },

        // Personnages – Astrub
        { name: 'Kerubim',      rarity: 'rare',       type: 'personnage', nation: 'astrub',  atk: 4, def: 6, hp: 13, lore: 'Antiquaire osamodas aux objets légendaires.' },
        { name: 'Joris',        rarity: 'epique',     type: 'personnage', nation: 'astrub',  atk: 5, def: 5, hp: 15, lore: 'Fils adoptif de Kerubim, aventurier prodige.' },
        { name: 'Atcham',       rarity: 'commune',    type: 'personnage', nation: 'astrub',  atk: 2, def: 1, hp: 6,  lore: 'Marchand douteux des ruelles d\'Astrub.' },
        { name: 'Simone',       rarity: 'peu-commune',type: 'personnage', nation: 'astrub',  atk: 1, def: 4, hp: 9,  lore: 'Gardienne silencieuse du marché.' },

        // Personnages – Amakna
        { name: 'Goultard',     rarity: 'mythique',   type: 'personnage', nation: 'amakna',  atk: 10,def: 6, hp: 28, lore: 'Le plus grand guerrier Iop, force brutale légendaire.' },
        { name: 'Tot',          rarity: 'legendaire', type: 'personnage', nation: 'amakna',  atk: 5, def: 8, hp: 19, lore: 'Créateur des Dofus, architecte du monde.' },
        { name: 'Lenaelle',     rarity: 'commune',    type: 'personnage', nation: 'amakna',  atk: 2, def: 3, hp: 8,  lore: 'Fermière tenace des plaines verdoyantes.' },
        { name: 'Crâ Archer',   rarity: 'peu-commune',type: 'personnage', nation: 'amakna',  atk: 4, def: 1, hp: 7,  lore: 'Archer nomade de la savane d\'Amakna.' },

        // Personnages – Frigost
        { name: 'Sylargh',      rarity: 'legendaire', type: 'personnage', nation: 'frigost', atk: 6, def: 7, hp: 21, lore: 'Capitaine pirate glacé dans les glaces éternelles.' },
        { name: 'Nileza',       rarity: 'epique',     type: 'personnage', nation: 'frigost', atk: 5, def: 6, hp: 16, lore: 'Elfe des glaces, maîtresse des blizzards.' },
        { name: 'Fraktale',     rarity: 'rare',       type: 'personnage', nation: 'frigost', atk: 5, def: 3, hp: 11, lore: 'Fantôme cristallin né du grand froid.' },
        { name: 'Ours Polaire', rarity: 'commune',    type: 'personnage', nation: 'frigost', atk: 3, def: 4, hp: 10, lore: 'Gardien sauvage des toundras gelées.' },

        // Équipements – Bonta
        { name: 'Épée de Bonta',      rarity: 'rare',       type: 'equipement', nation: 'bonta',   atk: 4, def: 0, hp: 0, lore: 'Forgée par les maîtres artisans de la lumière.' },
        { name: 'Bouclier d\'Or',      rarity: 'epique',     type: 'equipement', nation: 'bonta',   atk: 0, def: 6, hp: 0, lore: 'Impénétrable, symbole de la justice divine.' },
        { name: 'Amulette Sacrée',    rarity: 'legendaire', type: 'equipement', nation: 'bonta',   atk: 2, def: 3, hp: 5, lore: 'Bénie par Bontario lui-même, protège corps et âme.' },
        { name: 'Arc de Lumière',     rarity: 'mythique',   type: 'equipement', nation: 'bonta',   atk: 8, def: 1, hp: 0, lore: 'Tire des flèches de pure énergie divine.' },
        { name: 'Cape du Gardien',    rarity: 'peu-commune',type: 'equipement', nation: 'bonta',   atk: 0, def: 2, hp: 3, lore: 'Tissée de fils de lumière par les Féca de Bonta.' },

        // Équipements – Brakmar
        { name: 'Dague Empoisonnée',  rarity: 'rare',       type: 'equipement', nation: 'brakmar', atk: 5, def: 0, hp: 0, lore: 'La lame distille un venin des marais noirs.' },
        { name: 'Armure Maudite',     rarity: 'epique',     type: 'equipement', nation: 'brakmar', atk: 1, def: 5, hp: 0, lore: 'Forgée dans les flammes du Brakmar infernal.' },
        { name: 'Bâton des Ombres',   rarity: 'legendaire', type: 'equipement', nation: 'brakmar', atk: 6, def: 2, hp: 0, lore: 'Canalise la puissance des morts.' },
        { name: 'Masque de Sram',     rarity: 'peu-commune',type: 'equipement', nation: 'brakmar', atk: 0, def: 1, hp: 0, lore: 'Rend invisible aux yeux des ennemis.' },

        // Équipements – Astrub / Amakna / Frigost
        { name: 'Trident du Marchand',rarity: 'commune',    type: 'equipement', nation: 'astrub',  atk: 2, def: 1, hp: 0, lore: 'Outil polyvalent des commerçants de l\'île.' },
        { name: 'Bottes Rapides',     rarity: 'peu-commune',type: 'equipement', nation: 'amakna',  atk: 0, def: 0, hp: 2, lore: 'Permettent de courir plus vite que le vent.' },
        { name: 'Hache de Glace',     rarity: 'rare',       type: 'equipement', nation: 'frigost', atk: 5, def: 1, hp: 0, lore: 'Taillée dans le glacier éternel de Frigost.' },
        { name: 'Manteau Hivernal',   rarity: 'epique',     type: 'equipement', nation: 'frigost', atk: 0, def: 7, hp: 0, lore: 'Résiste aux tempêtes les plus violentes du nord.' },
        { name: 'Cristal Élémentaire',rarity: 'mythique',   type: 'equipement', nation: 'frigost', atk: 6, def: 6, hp: 6, lore: 'Fragment du cœur gelé du dieu Enutrof.' },

        // Villes – Bonta
        { name: 'Temple de Bonta',    rarity: 'rare',       type: 'ville', nation: 'bonta',   atk: 0, def: 5, hp: 15, lore: 'Cœur spirituel de la cité de la lumière.' },
        { name: 'Place de l\'Ordre',  rarity: 'legendaire', type: 'ville', nation: 'bonta',   atk: 0, def: 8, hp: 20, lore: 'Là où les champions jurent allégeance.' },
        { name: 'Forge des Héros',    rarity: 'epique',     type: 'ville', nation: 'bonta',   atk: 3, def: 4, hp: 12, lore: 'Armes légendaires forgées au feu sacré.' },

        // Villes – Brakmar
        { name: 'Citadelle Noire',    rarity: 'legendaire', type: 'ville', nation: 'brakmar', atk: 4, def: 9, hp: 22, lore: 'Forteresse impénétrable dressée sur les cendres.' },
        { name: 'Marché des Ombres',  rarity: 'rare',       type: 'ville', nation: 'brakmar', atk: 2, def: 3, hp: 10, lore: 'Commerce illicite sous l\'œil complaisant de Brâkmor.' },
        { name: 'Arènes de Sang',     rarity: 'epique',     type: 'ville', nation: 'brakmar', atk: 5, def: 3, hp: 14, lore: 'Combats à mort pour divertir les seigneurs.' },

        // Villes – Astrub
        { name: 'Port d\'Astrub',     rarity: 'commune',    type: 'ville', nation: 'astrub',  atk: 1, def: 2, hp: 8,  lore: 'Porte d\'entrée des aventuriers vers le monde.' },
        { name: 'Académie d\'Astrub', rarity: 'peu-commune',type: 'ville', nation: 'astrub',  atk: 0, def: 3, hp: 10, lore: 'Première école des apprentis magiciens.' },

        // Villes – Amakna / Frigost
        { name: 'Village d\'Amakna',  rarity: 'commune',    type: 'ville', nation: 'amakna',  atk: 0, def: 2, hp: 9,  lore: 'Paisible village au cœur des terres fertiles.' },
        { name: 'Tour de Frigost',    rarity: 'mythique',   type: 'ville', nation: 'frigost', atk: 3, def: 10,hp: 25, lore: 'Observatoire mystique suspendu dans les aurores boréales.' },
        { name: 'Havre de Glace',     rarity: 'rare',       type: 'ville', nation: 'frigost', atk: 1, def: 6, hp: 14, lore: 'Refuge des survivants du grand hiver éternel.' },
    ],

    PRICES: { 'commune': 10, 'peu-commune': 30, 'rare': 100, 'epique': 300, 'legendaire': 800, 'mythique': 2500 },
    TYPE_MULT: { 'personnage': 1.0, 'equipement': 1.2, 'ville': 1.1 },
    RARITY_ORDER: ['mythique', 'legendaire', 'epique', 'rare', 'peu-commune', 'commune'],
    RARITY_LABELS: { 'commune': 'Commune', 'peu-commune': 'Peu commune', 'rare': 'Rare', 'epique': 'Épique', 'legendaire': 'Légendaire', 'mythique': 'Mythique' },
    NATION_COLORS: { 'bonta': '#4a90d9', 'brakmar': '#c0392b', 'astrub': '#e67e22', 'amakna': '#27ae60', 'frigost': '#8ab4d4' },

    STARTER_INDICES: [0,4,7,10,14,18,19,23,24,28,31,32,33,35,38,40,41,42,43,44,45,46,47,48,49],

    getPoints()  { return parseInt(localStorage.getItem('pafalan_points') ?? 500); },
    setPoints(n) { localStorage.setItem('pafalan_points', Math.max(0, n)); },

    getCollection() {
        const raw = localStorage.getItem('pafalan_collection');
        return new Set(raw ? JSON.parse(raw) : []);
    },
    setCollection(set) { localStorage.setItem('pafalan_collection', JSON.stringify([...set])); },
    owns(i) { return this.getCollection().has(Number(i)); },

    buyCard(i) {
        i = Number(i);
        const price = this.getPrice(this.CARDS[i].rarity, this.CARDS[i].type);
        if (this.getPoints() < price || this.owns(i)) return false;
        this.setPoints(this.getPoints() - price);
        const col = this.getCollection();
        col.add(i);
        this.setCollection(col);
        return true;
    },

    getDecks() {
        const raw = localStorage.getItem('pafalan_decks');
        return raw ? JSON.parse(raw) : [{ name: 'Deck des Éléments', cards: [] }];
    },
    setDecks(decks) { localStorage.setItem('pafalan_decks', JSON.stringify(decks)); },
    getActiveDeckIdx() { return parseInt(localStorage.getItem('pafalan_deck_active') ?? 0); },
    setActiveDeckIdx(i) { localStorage.setItem('pafalan_deck_active', i); },

    getPrice(rarity, type) {
        return Math.round((this.PRICES[rarity] ?? 0) * (this.TYPE_MULT[type] ?? 1));
    },

    init() {
        if (localStorage.getItem('pafalan_init')) return;
        this.setCollection(new Set(this.STARTER_INDICES));
        this.setPoints(500);
        this.setDecks([{ name: 'Deck des Éléments', cards: [] }]);
        localStorage.setItem('pafalan_init', '1');
    }
};

Game.init();
