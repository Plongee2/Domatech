/* ============================================================
   game.js – Données et état global partagés entre toutes les pages
   Stockage : localStorage (persiste entre les sessions et les pages)
   ============================================================ */

const Game = {

    /* ---- Données des 50 cartes (source de vérité unique) ---- */
    CARDS: [
        { name: 'Carte',  rarity: 'commune',     type: 'personnage',  nation: 'bonta'   }, // 0
        { name: 'Carte',  rarity: 'peu-commune',  type: 'equipement',  nation: 'brakmar' }, // 1
        { name: 'Carte',  rarity: 'rare',          type: 'ville',       nation: 'astrub'  }, // 2
        { name: 'Carte',  rarity: 'epique',        type: 'personnage',  nation: 'amakna'  }, // 3
        { name: 'Carte',  rarity: 'legendaire',    type: 'equipement',  nation: 'frigost' }, // 4
        { name: 'Carte',  rarity: 'mythique',      type: 'ville',       nation: 'bonta'   }, // 5
        { name: 'Carte',  rarity: 'commune',       type: 'personnage',  nation: 'brakmar' }, // 6
        { name: 'Carte',  rarity: 'peu-commune',   type: 'ville',       nation: 'astrub'  }, // 7
        { name: 'Carte',  rarity: 'commune',       type: 'equipement',  nation: 'amakna'  }, // 8
        { name: 'Carte',  rarity: 'rare',          type: 'personnage',  nation: 'frigost' }, // 9
        { name: 'Carte',  rarity: 'peu-commune',   type: 'personnage',  nation: 'bonta'   }, // 10
        { name: 'Carte',  rarity: 'epique',        type: 'equipement',  nation: 'brakmar' }, // 11
        { name: 'Carte',  rarity: 'commune',       type: 'ville',       nation: 'astrub'  }, // 12
        { name: 'Carte',  rarity: 'legendaire',    type: 'personnage',  nation: 'amakna'  }, // 13
        { name: 'Carte',  rarity: 'rare',          type: 'equipement',  nation: 'frigost' }, // 14
        { name: 'Carte',  rarity: 'commune',       type: 'personnage',  nation: 'bonta'   }, // 15
        { name: 'Carte',  rarity: 'mythique',      type: 'personnage',  nation: 'brakmar' }, // 16
        { name: 'Carte',  rarity: 'peu-commune',   type: 'equipement',  nation: 'astrub'  }, // 17
        { name: 'Carte',  rarity: 'epique',        type: 'ville',       nation: 'amakna'  }, // 18
        { name: 'Carte',  rarity: 'commune',       type: 'equipement',  nation: 'frigost' }, // 19
        { name: 'Carte',  rarity: 'rare',          type: 'ville',       nation: 'bonta'   }, // 20
        { name: 'Carte',  rarity: 'peu-commune',   type: 'personnage',  nation: 'brakmar' }, // 21
        { name: 'Carte',  rarity: 'legendaire',    type: 'ville',       nation: 'astrub'  }, // 22
        { name: 'Carte',  rarity: 'commune',       type: 'ville',       nation: 'amakna'  }, // 23
        { name: 'Carte',  rarity: 'epique',        type: 'personnage',  nation: 'frigost' }, // 24
        { name: 'Carte',  rarity: 'rare',          type: 'personnage',  nation: 'bonta'   }, // 25
        { name: 'Carte',  rarity: 'commune',       type: 'personnage',  nation: 'brakmar' }, // 26
        { name: 'Carte',  rarity: 'peu-commune',   type: 'ville',       nation: 'astrub'  }, // 27
        { name: 'Carte',  rarity: 'epique',        type: 'equipement',  nation: 'amakna'  }, // 28
        { name: 'Carte',  rarity: 'commune',       type: 'equipement',  nation: 'frigost' }, // 29
        { name: 'Carte',  rarity: 'legendaire',    type: 'equipement',  nation: 'bonta'   }, // 30
        { name: 'Carte',  rarity: 'rare',          type: 'equipement',  nation: 'brakmar' }, // 31
        { name: 'Carte',  rarity: 'commune',       type: 'ville',       nation: 'astrub'  }, // 32
        { name: 'Carte',  rarity: 'peu-commune',   type: 'equipement',  nation: 'amakna'  }, // 33
        { name: 'Carte',  rarity: 'mythique',      type: 'equipement',  nation: 'frigost' }, // 34
        { name: 'Carte',  rarity: 'commune',       type: 'personnage',  nation: 'bonta'   }, // 35
        { name: 'Carte',  rarity: 'epique',        type: 'ville',       nation: 'brakmar' }, // 36
        { name: 'Carte',  rarity: 'peu-commune',   type: 'personnage',  nation: 'astrub'  }, // 37
        { name: 'Carte',  rarity: 'rare',          type: 'ville',       nation: 'amakna'  }, // 38
        { name: 'Carte',  rarity: 'commune',       type: 'equipement',  nation: 'frigost' }, // 39
        { name: 'Carte',  rarity: 'legendaire',    type: 'personnage',  nation: 'bonta'   }, // 40
        { name: 'Carte',  rarity: 'commune',       type: 'ville',       nation: 'brakmar' }, // 41
        { name: 'Carte',  rarity: 'rare',          type: 'personnage',  nation: 'astrub'  }, // 42
        { name: 'Carte',  rarity: 'peu-commune',   type: 'ville',       nation: 'amakna'  }, // 43
        { name: 'Carte',  rarity: 'epique',        type: 'personnage',  nation: 'frigost' }, // 44
        { name: 'Carte',  rarity: 'commune',       type: 'personnage',  nation: 'bonta'   }, // 45
        { name: 'Carte',  rarity: 'mythique',      type: 'ville',       nation: 'brakmar' }, // 46
        { name: 'Carte',  rarity: 'rare',          type: 'equipement',  nation: 'astrub'  }, // 47
        { name: 'Carte',  rarity: 'peu-commune',   type: 'personnage',  nation: 'amakna'  }, // 48
        { name: 'Carte',  rarity: 'legendaire',    type: 'ville',       nation: 'frigost' }, // 49
    ],

    /* ---- Prix de base par rareté (en points) ---- */
    PRICES: {
        'commune':      10,
        'peu-commune':  30,
        'rare':        100,
        'epique':      300,
        'legendaire':  800,
        'mythique':   2500
    },

    /* ---- Multiplicateur de prix selon le type ---- */
    TYPE_MULT: { 'personnage': 1.0, 'equipement': 1.2, 'ville': 1.1 },

    /* ---- Tri du plus rare au moins rare ---- */
    RARITY_ORDER: ['mythique', 'legendaire', 'epique', 'rare', 'peu-commune', 'commune'],

    /* ---- 25 cartes offertes à l'inscription :
           14 communes + 10 peu-communes + 1 rare (index 2) ---- */
    STARTER_INDICES: [0,1,2,6,7,8,10,12,15,17,19,21,23,26,27,29,32,33,35,37,39,41,43,45,48],

    /* ---- Points ---- */
    getPoints()  { return parseInt(localStorage.getItem('pafalan_points') ?? 500); },
    setPoints(n) { localStorage.setItem('pafalan_points', Math.max(0, n)); },

    /* ---- Collection ---- */
    getCollection() {
        const raw = localStorage.getItem('pafalan_collection');
        return new Set(raw ? JSON.parse(raw) : []);
    },
    setCollection(set) {
        localStorage.setItem('pafalan_collection', JSON.stringify([...set]));
    },
    owns(i) { return this.getCollection().has(Number(i)); },

    /* Achat d'une carte : vérifie les points et la non-possession, renvoie true si succès */
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

    /* ---- Decks ---- */
    getDecks() {
        const raw = localStorage.getItem('pafalan_decks');
        return raw ? JSON.parse(raw) : [{ name: 'Deck 1', cards: [] }];
    },
    setDecks(decks) { localStorage.setItem('pafalan_decks', JSON.stringify(decks)); },
    getActiveDeckIdx() { return parseInt(localStorage.getItem('pafalan_deck_active') ?? 0); },
    setActiveDeckIdx(i) { localStorage.setItem('pafalan_deck_active', i); },

    /* ---- Calcul du prix d'une carte ---- */
    getPrice(rarity, type) {
        return Math.round((this.PRICES[rarity] ?? 0) * (this.TYPE_MULT[type] ?? 1));
    },

    /* ---- Initialisation au tout premier lancement ---- */
    init() {
        if (localStorage.getItem('pafalan_init')) return;
        this.setCollection(new Set(this.STARTER_INDICES));
        this.setPoints(500);
        this.setDecks([{ name: 'Deck 1', cards: [] }]);
        localStorage.setItem('pafalan_init', '1');
    }
};

// Lancé dès le chargement du script
Game.init();
