<?php
// logout.php
session_start(); // On récupère la session en cours
session_unset(); // On vide toutes les variables de session
session_destroy(); // On détruit la session

// On redirige vers la page de connexion
header('Location: login.php');
exit;
?>