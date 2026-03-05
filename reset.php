<?php
// reset.php

// Le chemin vers ton fichier de base de données
$db_file = __DIR__ . '/data/gestion_matos.sqlite';

$message = '';

// Si le formulaire de confirmation a été envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmation']) && $_POST['confirmation'] === 'OUI_JE_VEUX_TOUT_EFFACER') {
    
    // On ferme la session au cas où quelqu'un serait connecté
    session_start();
    session_unset();
    session_destroy();

    // On supprime le fichier
    if (file_exists($db_file)) {
        if (unlink($db_file)) {
            $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; border-left: 5px solid #2e7d32;">
                            <h3>✅ Succès : La base de données a été supprimée !</h3>
                            <p>Ton application est maintenant totalement vide.</p>
                            <p><strong>Prochaines étapes :</strong></p>
                            <ol>
                                <li>Remets ton fichier <code>install.php</code> sur le serveur et <a href="install.php" style="color: #2e7d32; font-weight: bold;">clique ici pour le lancer</a>.</li>
                                <li>Remets ton fichier <code>create_admin.php</code> et lance-le pour recréer ton compte.</li>
                                <li>Supprime ce fichier <code>reset.php</code> de ton serveur !</li>
                            </ol>
                        </div>';
        } else {
            $message = '<div style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 4px;">❌ Erreur : Impossible de supprimer le fichier. Vérifie les droits du dossier "data".</div>';
        }
    } else {
        $message = '<div style="background-color: #fff3e0; color: #ef6c00; padding: 15px; border-radius: 4px;">⚠️ Le fichier de base de données n\'existe pas (il a peut-être déjà été supprimé).</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remise à zéro - Secourut's</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 50px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-top: 5px solid #d32f2f; }
        .btn-danger { background-color: #d32f2f; color: white; padding: 15px 20px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; text-transform: uppercase; }
        .btn-danger:hover { background-color: #b71c1c; }
    </style>
</head>
<body>

<div class="container">
    <h1 style="color: #d32f2f;">⚠️ ZONE DE DANGER ⚠️</h1>
    
    <?php if ($message): ?>
        <?php echo $message; ?>
    <?php else: ?>
        <p style="font-size: 18px;">Tu es sur le point de <strong>supprimer définitivement</strong> toute la base de données.</p>
        <ul style="text-align: left; background: #fff3e0; padding: 20px 40px; border-radius: 4px; color: #d84315;">
            <li>Tous les comptes utilisateurs seront supprimés.</li>
            <li>Tout le catalogue de matériel sera effacé.</li>
            <li>Tous les inventaires et stocks seront perdus.</li>
        </ul>
        <p>Es-tu absolument certain de vouloir faire ça ?</p>
        
        <form action="reset.php" method="POST" onsubmit="return confirm('Es-tu VRAIMENT sûr ? Il n\'y aura pas de retour en arrière possible.');">
            <input type="hidden" name="confirmation" value="OUI_JE_VEUX_TOUT_EFFACER">
            <button type="submit" class="btn-danger">💥 Oui, effacer toute la base de données</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>