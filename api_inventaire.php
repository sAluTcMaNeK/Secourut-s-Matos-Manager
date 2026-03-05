<?php
// api_inventaire.php
// Ce fichier sert uniquement de "radar" pour le Javascript afin de lire la base de données en temps réel.
require_once 'includes/auth.php';
require_once 'config/db.php';

// On indique qu'on va renvoyer du texte formaté pour le Javascript (JSON)
header('Content-Type: application/json');

// 1. On cherche l'inventaire en cours
$stmt_actif = $pdo->query("SELECT id FROM inventaires WHERE statut = 'en_cours' ORDER BY id DESC LIMIT 1");
$inventaire_actif = $stmt_actif->fetch();

if ($inventaire_actif) {
    // 2. On récupère tous les ID des lieux qui ont été validés
    $stmt_faits = $pdo->prepare("SELECT lieu_id FROM inventaires_lieux WHERE inventaire_id = ?");
    $stmt_faits->execute([$inventaire_actif['id']]);
    $lieux_faits = $stmt_faits->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. On renvoie la liste au Javascript
    echo json_encode([
        'status' => 'success',
        'lieux_faits' => array_map('intval', $lieux_faits)
    ]);
} else {
    echo json_encode(['status' => 'aucun_inventaire']);
}