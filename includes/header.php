<?php
// includes/header.php
$nom_utilisateur = htmlspecialchars($_SESSION['username'] ?? 'Utilisateur');
$role_utilisateur = htmlspecialchars($_SESSION['role'] ?? '');

function getCouleurCategorie($nom_categorie)
{
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secourut's Matos Manager</title>
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        // 1. Gestion de la déconnexion automatique
        let timeout;
        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(() => { window.location.href = 'logout.php'; }, 300000);
        }
        window.onload = resetTimer; window.onmousemove = resetTimer; window.onmousedown = resetTimer;
        window.ontouchstart = resetTimer; window.onclick = resetTimer; window.onkeypress = resetTimer;

        // 2. Gestion du Menu Mobile
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('ouvert');
            const overlay = document.getElementById('overlay');
            overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</head>

<body>

    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="sidebar" id="sidebar">
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
            <div style="display: flex; align-items: center;">
                <button class="btn-menu-mobile" onclick="toggleMenu()">☰</button>
                <span>Bienvenue <strong
                        style="display: none; display: inline-block;"><?php echo $nom_utilisateur; ?></strong></span>
            </div>

            <div style="display: flex; align-items: center; gap: 15px;">
                <span>👤 <?php echo $nom_utilisateur; ?></span>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </div>

        <div class="container">
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div
                    style="background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <?php echo $_SESSION['flash_success'];
                    unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div
                    style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #c62828; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <?php echo $_SESSION['flash_error'];
                    unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>