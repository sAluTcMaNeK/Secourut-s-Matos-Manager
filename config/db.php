<?php
// config/db.php

// On définit le chemin du dossier et du fichier
$db_dir = __DIR__ . '/../data';
$db_file = $db_dir . '/gestion_matos.sqlite';

try {
    // NOUVEAU : On demande à PHP de créer le dossier "data" s'il n'existe pas
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0775, true); // 0775 donne les droits d'écriture au serveur
    }

    // Connexion à la base SQLite
    $pdo = new PDO("sqlite:" . $db_file);
    
    // Configuration des erreurs
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Activer le support des clés étrangères
    $pdo->exec('PRAGMA foreign_keys = ON;');
    
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données SQLite : " . $e->getMessage());
}
?>