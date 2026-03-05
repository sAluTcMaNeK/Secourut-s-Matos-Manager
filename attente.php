<?php
// attente.php
require_once 'includes/auth.php';

// Si un admin vient de lui donner les droits, on le renvoie sur l'accueil !
if ($_SESSION['can_view'] === 1) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>En attente d'approbation - Secourut's</title>
    <style>
        body { background-color: #f4f7f6; font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border-top: 5px solid #d32f2f; }
        h1 { color: #d32f2f; margin-top: 0; }
        p { color: #555; font-size: 16px; line-height: 1.5; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #333; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Accès restreint</h1>
        <p><strong>Bonjour <?php echo htmlspecialchars($_SESSION['username']); ?> !</strong></p>
        <p>Votre compte a bien été créé. Cependant, vous n'avez <strong>aucune autorisation de consultation ou d'édition</strong> pour le moment.</p>
        <p>Veuillez patienter jusqu'à ce qu'un administrateur valide votre compte et vous attribue les droits nécessaires pour accéder au Matos Manager.</p>
        
        <a href="logout.php" class="btn">Se déconnecter</a>
    </div>
    
    <script>
        setTimeout(function() { window.location.reload(); }, 10000);
    </script>
</body>
</html>