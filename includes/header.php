<?php
// includes/header.php
$nom_utilisateur = htmlspecialchars($_SESSION['username'] ?? 'Utilisateur');
$role_utilisateur = htmlspecialchars($_SESSION['role'] ?? '');

// Fonction pour attribuer les couleurs aux catégories
function getCouleurCategorie($nom_categorie)
{
    // On met en minuscules et on enlève les accents pour éviter les bugs
    $nom = strtolower(trim($nom_categorie));
    $nom = str_replace(['é', 'è', 'ê'], 'e', $nom);

    switch ($nom) {
        case 'bilan':
            return ['bg' => '#f1c40f', 'text' => '#333'];
        case 'bobologie':
            return ['bg' => '#8b0000', 'text' => 'white'];
        case 'trauma':
            return ['bg' => '#8e44ad', 'text' => 'white'];
        case 'hemorragie':
            return ['bg' => '#e74c3c', 'text' => 'white'];
        default:
            return ['bg' => '#2c3e50', 'text' => 'white'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secourut's Matos Management</title>

    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    
    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        let timeout;
        function resetTimer() {
            clearTimeout(timeout);
            // 300000 millisecondes = 5 minutes
            timeout = setTimeout(() => {
                window.location.href = 'logout.php';
            }, 300000);
        }

        // On réinitialise le compte à rebours à chaque mouvement
        window.onload = resetTimer;
        window.onmousemove = resetTimer;
        window.onmousedown = resetTimer;
        window.ontouchstart = resetTimer;
        window.onclick = resetTimer;
        window.onkeypress = resetTimer;  
    </script>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/favicon.png" alt="Logo Secourut's">
            MATOS MANAGER
        </div>

        <a href="index.php">📊 Tableau de bord</a>
        <a href="materiel.php">📦 Catalogue Matériel</a>
        <a href="lieux.php">🎒 Sacs & Réserves</a>
        <a href="remplissage.php">🔄 Remplissage</a>
        <a href="inventaire.php">📋 Faire l'inventaire</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="utilisateurs.php" class="admin-link">👥 Utilisateurs</a>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <div class="topbar">
            <span>Bienvenue <strong><?php echo $role_utilisateur; ?></strong></span>
            <div>
                <span style="margin-right: 15px;">👤 <?php echo $nom_utilisateur; ?></span>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </div>

        <div class="container">