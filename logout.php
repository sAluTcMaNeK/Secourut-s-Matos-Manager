<?php
// logout.php
session_start();

// 1. On vide toutes les variables de session actuelles
$_SESSION = array();

// 2. On détruit la session locale (Secourut's Matos Manager)
session_destroy();

// 3. On redémarre une nouvelle session vierge uniquement pour stocker le message flash
session_start();
$_SESSION['flash_success'] = "Vous avez été déconnecté avec succès.";

// 4. Redirection vers la déconnexion globale du portail des assos UTC 
// (Sans paramètre de retour pour éviter de faire planter leur serveur)
header("Location: https://assos.utc.fr/auth/logout");
exit;
?>