<?php
// install.php
require_once 'config/db.php';

$sql = "
-- 1. Table des Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom_utilisateur TEXT NOT NULL UNIQUE,
    mot_de_passe TEXT NOT NULL,
    role TEXT DEFAULT 'secouriste'
);

-- 2. Table des Catégories
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    couleur_fond TEXT DEFAULT '#2c3e50',
    couleur_texte TEXT DEFAULT '#ffffff'
);

-- 3. Table des Lieux de stockage
CREATE TABLE IF NOT EXISTS lieux_stockage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    type TEXT DEFAULT 'sac',
    icone TEXT DEFAULT '🎒',
    est_reserve INTEGER DEFAULT 0
);

-- 4. Table du Matériel
CREATE TABLE IF NOT EXISTS materiels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    categorie_id INTEGER NOT NULL,
    seuil_alerte INTEGER DEFAULT 0,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- 5. Table des Stocks
CREATE TABLE IF NOT EXISTS stocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materiel_id INTEGER NOT NULL,
    lieu_id INTEGER NOT NULL,
    quantite INTEGER NOT NULL DEFAULT 0,
    date_peremption TEXT NULL,
    FOREIGN KEY (materiel_id) REFERENCES materiels(id) ON DELETE CASCADE,
    FOREIGN KEY (lieu_id) REFERENCES lieux_stockage(id) ON DELETE CASCADE
);

-- 6. Tables de Paramétrages (Types & Icones)
CREATE TABLE IF NOT EXISTS types_lieux (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT);
CREATE TABLE IF NOT EXISTS icones_lieux (id INTEGER PRIMARY KEY AUTOINCREMENT, icone TEXT);

-- 7. Tables pour les Inventaires Globaux
CREATE TABLE IF NOT EXISTS inventaires (id INTEGER PRIMARY KEY AUTOINCREMENT, date_debut TEXT, date_fin TEXT, statut TEXT DEFAULT 'en_cours', lieux_total INTEGER DEFAULT 0, lieux_faits INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS inventaires_lieux (inventaire_id INTEGER, lieu_id INTEGER, PRIMARY KEY(inventaire_id, lieu_id));
CREATE TABLE IF NOT EXISTS historique_comptages (id INTEGER PRIMARY KEY AUTOINCREMENT, inventaire_id INTEGER, lieu_id INTEGER, materiel_id INTEGER, qte_avant INTEGER, qte_apres INTEGER, action_corrective TEXT);

-- 8. Tables pour les DPS (Événements)
CREATE TABLE IF NOT EXISTS evenements (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT, date_evenement TEXT, statut TEXT DEFAULT 'a_verifier', cree_le TEXT);
CREATE TABLE IF NOT EXISTS evenements_lieux (evenement_id INTEGER, lieu_id INTEGER, statut TEXT DEFAULT 'en_attente', PRIMARY KEY(evenement_id, lieu_id));

-- 9. Table Historique des actions
CREATE TABLE IF NOT EXISTS historique_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, nom_utilisateur TEXT, action TEXT, date_action TEXT);

-- Insertion des données de base obligatoires (ignorées si elles existent déjà)
INSERT OR IGNORE INTO categories (nom) VALUES ('Dégrisement'), ('Bobologie'), ('Trauma'), ('Hémorragie'), ('Bilan'), ('Oxygène'), ('Immobilisation / Brancardage');
INSERT OR IGNORE INTO types_lieux (nom) VALUES ('Sac d''intervention'), ('Sac logistique'), ('Réserve');
INSERT OR IGNORE INTO icones_lieux (icone) VALUES ('🎒'), ('💼'), ('🏢'), ('🧰'), ('🚑'), ('💊');
";

try {
    $pdo->exec($sql);
    echo "<div style='font-family: Arial; padding: 20px;'><h1 style='color: #2e7d32;'>✅ Base de données à jour !</h1><p>Toutes les tables sont créées.</p></div>";
} catch (PDOException $e) {
    echo "<div style='font-family: Arial; color: red; padding: 20px;'>Erreur DB : " . $e->getMessage() . "</div>";
}
?>