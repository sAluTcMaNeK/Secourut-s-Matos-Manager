<?php
// ping.php - Script de maintien de session
session_start();

if (isset($_SESSION['user_id'])) {
    // On met à jour l'horloge d'activité pour repousser le timeout de 30 minutes
    $_SESSION['last_activity'] = time();
    echo "pong";
} else {
    // Si la session n'existe plus
    http_response_code(401);
    echo "expired";
}
?>