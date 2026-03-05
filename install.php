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
    nom TEXT NOT NULL UNIQUE
);

-- 3. Table des Lieux de stockage
CREATE TABLE IF NOT EXISTS lieux_stockage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    type TEXT DEFAULT 'sac'
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

-- Insertion des catégories de base
INSERT OR IGNORE INTO categories (nom) VALUES ('Dégrisement'), ('Bobologie'), ('Trauma'), ('Hémorragie'), ('Bilan'), ('Oxygène'), ('Immobilisation / Brancardage');
";

try {
    $pdo->exec($sql);
    echo "<div style='font-family: Arial; padding: 20px;'>";
    echo "<h1 style='color: #2e7d32;'>✅ Succès !</h1>";
    echo "<p>La base de données SQLite et les tables ont été créées avec succès.</p>";
    echo "<p>⚠️ <strong>Très important :</strong> Supprime maintenant ce fichier <code>install.php</code> de ton serveur pour des raisons de sécurité.</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='font-family: Arial; color: red; padding: 20px;'>Erreur lors de la création des tables : " . $e->getMessage() . "</div>";
}
?>