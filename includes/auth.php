<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Vérification de base : l'utilisateur est-il connecté ?
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Gestion de l'expiration de session (5 minutes = 300 secondes)
$timeout = 300; 

if (isset($_SESSION['last_activity'])) {
    $duree_inactivite = time() - $_SESSION['last_activity'];
    
    if ($duree_inactivite > $timeout) {
        // Trop tard ! On détruit la session et on renvoie au login
        session_unset();
        session_destroy();
        header('Location: login.php?reason=timeout');
        exit;
    }
}

// 3. On met à jour le marqueur de temps pour la prochaine vérification
$_SESSION['last_activity'] = time();
?>