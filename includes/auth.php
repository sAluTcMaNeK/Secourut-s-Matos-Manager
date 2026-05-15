<?php
// includes/auth.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- ÉTAPE 1 : GESTION DU JETON CSRF (INDISPENSABLE) ---
// On s'assure que le jeton existe pour TOUTE la session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Timeout d'inactivité (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../config/db.php';

// --- ÉTAPE 2 : RÉCUPÉRATION DU RÔLE ET PERMISSIONS ---
$stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

if (!$user_data) {
    header("Location: logout.php");
    exit;
}

$role = $user_data['role'];
$_SESSION['role'] = $role;

// Définition des variables de permissions pour les pages
$est_admin = ($role === 'admin');
$peut_editer_matos = in_array($role, ['matos', 'admin']);
$peut_gerer_dps = in_array($role, ['operationnel', 'admin']);
$peut_verifier_sceller = in_array($role, ['matos', 'operationnel', 'admin']);

// Rétrocompatibilité pour les anciennes pages
$peut_editer = $peut_editer_matos;
?>