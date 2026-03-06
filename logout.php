<?php
// logout.php
session_start();

// 1. On efface toutes les variables de la session (déconnexion de l'utilisateur)
session_unset();

// 2. On enregistre le message de succès dans cette session propre
$_SESSION['flash_success'] = "Vous avez été déconnecté de Secourut's Matos Manager. <br><br><i>Note : Vous êtes peut-être encore connecté au CAS. <a href='https://auth.assos.utc.fr/logout' target='_blank'>Cliquez ici pour vous déconnecter complètement du CAS-UTC.</a></i>";

// 3. On redirige vers la page login
header("Location: login.php");
exit;