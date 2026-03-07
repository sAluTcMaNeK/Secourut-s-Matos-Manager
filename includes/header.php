<?php
// includes/header.php
$nom_utilisateur = htmlspecialchars($_SESSION['username'] ?? 'Utilisateur');
$role_utilisateur = htmlspecialchars($_SESSION['role'] ?? '');
$est_admin = ($role_utilisateur === 'admin');

// --- CHARGEMENT DYNAMIQUE DES COULEURS ---
// On met en cache les couleurs des catégories pour éviter de faire 50 requêtes SQL
global $pdo;
$db_categories = [];
if (isset($pdo)) {
    try {
        $stmt_cats = $pdo->query("SELECT nom, couleur_fond, couleur_texte FROM categories");
        while ($row = $stmt_cats->fetch()) {
            $db_categories[strtolower(trim($row['nom']))] = [
                'bg' => $row['couleur_fond'] ?? '#2c3e50',
                'text' => $row['couleur_texte'] ?? 'white'
            ];
        }
    } catch (Exception $e) { }
}

function getCouleurCategorie($nom_categorie) {
    global $db_categories;
    $nom = strtolower(trim($nom_categorie));
    
    // Si la catégorie existe en base avec une couleur, on la prend
    if (isset($db_categories[$nom]) && !empty($db_categories[$nom]['bg'])) {
        return ['bg' => $db_categories[$nom]['bg'], 'text' => $db_categories[$nom]['text']];
    }
    
    // Sinon, couleur par défaut
    return ['bg' => '#2c3e50', 'text' => 'white'];
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
        <a href="remplissage.php">🚑 Vérification DPS</a>
        <a href="inventaire.php">📋 Faire l'inventaire</a>
        
        <?php if ($est_admin): ?>
            <a href="parametres.php" style="margin-top: 15px;" class="admin-link">⚙️ Paramètres</a>
            <a href="utilisateurs.php" class="admin-link">👥 Utilisateurs</a>
            <a href="historique.php" class="admin-link">📜 Historique</a>
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
<script src="assets/js/script.js"></script>