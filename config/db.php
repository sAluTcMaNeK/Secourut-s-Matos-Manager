<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// config/db.php

// --- FONCTION DE CHARGEMENT .ENV CORRIGÉE ---
// dirname(__DIR__) permet de remonter d'un cran (vers /intranet/)
$chemin_env = dirname(dirname(__DIR__)) . '/.env'; 

if (file_exists($chemin_env)) {
    $lignes = file($chemin_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lignes as $ligne) {
        if (strpos(trim($ligne), '#') === 0) continue; // Ignore les commentaires
        $parts = explode('=', $ligne, 2);
        if (count($parts) === 2) {
            $cle = trim($parts[0]);
            // On enlève les espaces ET les guillemets (" ou ') autour de la valeur
            $valeur = trim(trim($parts[1]), '"\''); 
            $_ENV[$cle] = $valeur;
        }
    }
}
// --------------------------------------------

// On récupère les variables (avec des vraies valeurs par défaut au cas où)
$host = $_ENV['SECOURUTS_DB_HOST'] ;
$dbname = $_ENV['SECOURUTS_DB_NAME'] ;
$username = $_ENV['SECOURUTS_DB_USER'] ;
$password = $_ENV['SECOURUTS_DB_PASSWORD'] ;

try {
    // 2. Connexion avec le driver "mysql:" au lieu de "sqlite:"
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Options de sécurité et de récupération des données
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Erreur de connexion MySQL : " . $e->getMessage());
}

// ==========================================
// FONCTION GLOBALE : ENREGISTRER UNE ACTION
// ==========================================
function logAction($pdo, $action_texte)
{
    $user = $_SESSION['username'] ?? 'Système';
    try {
        // En MySQL, on utilise NOW() au lieu de datetime('now', 'localtime')
        $stmt = $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())");
        $stmt->execute([$user, $action_texte]);
    } catch (PDOException $e) {
    }
}
?>