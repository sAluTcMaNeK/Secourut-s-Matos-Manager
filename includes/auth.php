<?php
// includes/auth.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Timeout d'inactivité (ex: 30 minutes = 1800 secondes)
$timeout_duration = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// --- VÉRIFICATION DES PERMISSIONS EN TEMPS RÉEL ---
require_once __DIR__ . '/../config/db.php';

// Mise à jour de la table utilisateurs silencieuse (si ce n'est pas déjà fait)
try {
    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN can_view INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN can_edit INTEGER DEFAULT 0");
} catch (PDOException $e) {}

$stmt = $pdo->prepare("SELECT role, can_view, can_edit FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

// Si l'utilisateur n'existe plus dans la base
if (!$user_data) {
    header("Location: logout.php");
    exit;
}

// On rafraîchit la session
$_SESSION['role'] = $user_data['role'];
$_SESSION['can_view'] = (int)$user_data['can_view'];
$_SESSION['can_edit'] = (int)$user_data['can_edit'];

// Les admins ont toujours tous les droits
if ($_SESSION['role'] === 'admin') {
    $_SESSION['can_view'] = 1;
    $_SESSION['can_edit'] = 1;
}

// Si c'est un nouvel utilisateur sans droit de vue
$page_actuelle = basename($_SERVER['PHP_SELF']);
$pages_autorisees = ['attente.php', 'logout.php'];

if ($_SESSION['can_view'] === 0 && !in_array($page_actuelle, $pages_autorisees)) {
    header("Location: attente.php");
    exit;
}
// Sécurité : Si la permission n'est pas définie (vieux comptes ou mode lecture), on la force à 0
if (!isset($_SESSION['can_edit'])) {
    $_SESSION['can_edit'] = 0;
}

// On crée une variable GLOBALE pour toutes les pages
$peut_editer = ($_SESSION['can_edit'] == 1 || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'));
?>
