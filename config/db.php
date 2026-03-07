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
// Tables pour la gestion des événements (DPS)
$pdo->exec("CREATE TABLE IF NOT EXISTS evenements (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT, date_evenement TEXT, statut TEXT DEFAULT 'a_verifier', cree_le TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS evenements_lieux (evenement_id INTEGER, lieu_id INTEGER, statut TEXT DEFAULT 'en_attente', PRIMARY KEY(evenement_id, lieu_id))");

// Création de la table des inventaires globaux si elle n'existe pas
$pdo->exec("CREATE TABLE IF NOT EXISTS inventaires (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    date_debut TEXT, 
    date_fin TEXT, 
    statut TEXT DEFAULT 'en_cours',
    lieux_total INTEGER DEFAULT 0,
    lieux_faits INTEGER DEFAULT 0
)");

// =======================================================
// FONCTION GLOBALE : DÉDUCTION AUTOMATIQUE DES RÉSERVES
// =======================================================
function deduireDeLaReserve($pdo, $materiel_id, $quantite_requise)
{
    if ($quantite_requise <= 0)
        return;

    // 1. On cherche les stocks dans les LIEUX cochés comme "réserve"
    $stmt = $pdo->prepare("
        SELECT s.id, s.quantite 
        FROM stocks s
        JOIN lieux_stockage l ON s.lieu_id = l.id
        WHERE s.materiel_id = ? AND l.est_reserve = 1 AND s.quantite > 0
        ORDER BY s.date_peremption ASC, s.id ASC
    ");
    $stmt->execute([$materiel_id]);
    $stocks_reserve = $stmt->fetchAll();

    // 2. On vide les lots un par un
    foreach ($stocks_reserve as $stock) {
        if ($quantite_requise <= 0)
            break;

        $qte_dispo = $stock['quantite'];
        $stock_id = $stock['id'];

        if ($qte_dispo <= $quantite_requise) {
            $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$stock_id]);
            $quantite_requise -= $qte_dispo;
        } else {
            $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$quantite_requise, $stock_id]);
            $quantite_requise = 0;
        }
    }
}
// ==========================================
// FONCTION GLOBALE : ENREGISTRER UNE ACTION
// ==========================================
function logAction($pdo, $action_texte) {
    $user = $_SESSION['username'] ?? 'Système';
    try {
        $stmt = $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))");
        $stmt->execute([$user, $action_texte]);
    } catch(PDOException $e) {}
}
?>