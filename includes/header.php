<?php
// includes/header.php
$nom_utilisateur = htmlspecialchars($_SESSION['username'] ?? 'Utilisateur');
$role_utilisateur = htmlspecialchars($_SESSION['role'] ?? '');
// ... code existant ...
$role_utilisateur = htmlspecialchars($_SESSION['role'] ?? '');

// NOUVEAU : Fonction pour attribuer les couleurs aux catégories
function getCouleurCategorie($nom_categorie) {
    // On met en minuscules et on enlève les accents pour éviter les bugs
    $nom = strtolower(trim($nom_categorie));
    $nom = str_replace(['é', 'è', 'ê'], 'e', $nom);
    
    switch($nom) {      //switch case couleur par catégorie
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
    <script>
        let timeout;
        function resetTimer() {
            clearTimeout(timeout);
            // 300000 millisecondes = 5 minutes
            timeout = setTimeout(() => {
                window.location.href = 'logout.php';
            }, 300000);
        }

        // On réinitialise le compte à rebours à chaque mouvement de souris ou touche pressée
        window.onload = resetTimer;
        window.onmousemove = resetTimer;
        window.onmousedown = resetTimer; 
        window.ontouchstart = resetTimer;
        window.onclick = resetTimer;     
        window.onkeypress = resetTimer;  
    </script>
</head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secourut's Matos Management</title>
    
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            display: flex; 
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
        }
        
        /* NOUVEAU STYLE POUR LE LOGO DU MENU */
        .sidebar-logo {
            text-align: center;
            padding: 20px 10px;
            background-color: #1a252f;
            color: #d32f2f; /* Rouge Secourut's */
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            border-bottom: 2px solid #d32f2f;
        }
        .sidebar-logo img {
            width: 80px; /* Taille du logo */
            height: auto;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-bottom: 1px solid #34495e;
            transition: background 0.3s;
        }
        .sidebar a:hover {
            background-color: #34495e;
        }
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background-color: #d32f2f;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-logout {
            background-color: white;
            color: #d32f2f;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }
        .container {
            padding: 20px;
        }
        /* Animation globale pour les grosses cartes cliquables */
        .carte-animee {
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
        }
        .carte-animee:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 8px 15px rgba(0,0,0,0.15) !important;
            border-color: #d32f2f !important; /* Petit bonus : la bordure rougit légèrement */
        }
    </style>
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