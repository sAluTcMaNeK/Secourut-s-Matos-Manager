<?php
// install.php
require_once 'config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'secouriste'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE,
    couleur_fond VARCHAR(20) DEFAULT '#2c3e50',
    couleur_texte VARCHAR(20) DEFAULT '#ffffff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lieux_stockage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE,
    type VARCHAR(100) DEFAULT 'sac',
    icone VARCHAR(50) DEFAULT '🎒',
    est_reserve TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS materiels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    categorie_id INT NOT NULL,
    seuil_alerte INT DEFAULT 0,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materiel_id INT NOT NULL,
    lieu_id INT NOT NULL,
    quantite INT NOT NULL DEFAULT 0,
    date_peremption DATE NULL,
    FOREIGN KEY (materiel_id) REFERENCES materiels(id) ON DELETE CASCADE,
    FOREIGN KEY (lieu_id) REFERENCES lieux_stockage(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS types_lieux (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(255)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS icones_lieux (id INT AUTO_INCREMENT PRIMARY KEY, icone VARCHAR(50)) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventaires (id INT AUTO_INCREMENT PRIMARY KEY, date_debut DATETIME, date_fin DATETIME, statut VARCHAR(50) DEFAULT 'en_cours', lieux_total INT DEFAULT 0, lieux_faits INT DEFAULT 0) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS inventaires_lieux (inventaire_id INT, lieu_id INT, PRIMARY KEY(inventaire_id, lieu_id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS historique_comptages (id INT AUTO_INCREMENT PRIMARY KEY, inventaire_id INT, lieu_id INT, materiel_id INT, qte_avant INT, qte_apres INT, action_corrective TEXT) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evenements (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(255), date_evenement DATE, statut VARCHAR(50) DEFAULT 'a_verifier', cree_le DATETIME) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS evenements_lieux (evenement_id INT, lieu_id INT, statut VARCHAR(50) DEFAULT 'en_attente', PRIMARY KEY(evenement_id, lieu_id)) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historique_actions (id INT AUTO_INCREMENT PRIMARY KEY, nom_utilisateur VARCHAR(255), action TEXT, date_action DATETIME) ENGINE=InnoDB;

INSERT IGNORE INTO categories (nom) VALUES ('Dégrisement'), ('Bobologie'), ('Trauma'), ('Hémorragie'), ('Bilan'), ('Oxygène'), ('Immobilisation / Brancardage');
INSERT IGNORE INTO types_lieux (nom) VALUES ('Sac d\'intervention'), ('Sac logistique'), ('Réserve');
INSERT IGNORE INTO icones_lieux (icone) VALUES ('🎒'), ('💼'), ('🏢'), ('🧰'), ('🚑'), ('💊');
";

try {
    $pdo->exec($sql);
    echo "<div style='font-family: Arial; padding: 20px;'><h1 style='color: #2e7d32;'>✅ Base MySQL installée et prête !</h1></div>";
} catch (PDOException $e) {
    echo "<div style='font-family: Arial; color: red; padding: 20px;'>Erreur DB : " . $e->getMessage() . "</div>";
}
?>