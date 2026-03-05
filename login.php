<?php
// login.php
session_start();
require_once 'config/db.php';

// 1. Si l'utilisateur est déjà connecté, on le redirige directement vers l'index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';
$message_info = '';

// 2. Vérification si on arrive ici à cause d'un timeout (déconnexion automatique)
if (isset($_GET['reason']) && $_GET['reason'] === 'timeout') {
    $message_info = "Votre session a expiré après 5 minutes d'inactivité. Veuillez vous reconnecter.";
}

// 3. Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Recherche de l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE nom_utilisateur = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Initialisation de la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nom_utilisateur'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time(); // On initialise le marqueur de temps pour le timeout
            
            header('Location: index.php');
            exit;
        } else {
            $erreur = "Identifiants incorrects.";
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Secourut's Matos Manager</title>
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo-login {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
        }
        .login-container h1 { 
            color: #d32f2f; 
            font-size: 22px; 
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .login-container h2 { 
            color: #555; 
            font-size: 14px; 
            margin-bottom: 30px; 
            font-weight: normal;
        }
        
        /* Alertes */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .alert-timeout {
            background-color: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffe0b2;
        }

        .form-group { 
            margin-bottom: 20px; 
            text-align: left; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #d32f2f;
            outline: none;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-submit:hover { 
            background-color: #b71c1c; 
        }
        .footer-text {
            margin-top: 25px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>

<div class="login-container">
    <img src="assets/img/favicon.png" alt="Logo Secourut's" class="logo-login">
    <h1>Secourut's</h1>
    <h2>MATOS MANAGER</h2>
    
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erreur); ?></div>
    <?php endif; ?>

    <?php if (!empty($message_info)): ?>
        <div class="alert alert-timeout"><?php echo htmlspecialchars($message_info); ?></div>
    <?php endif; ?>
    
    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        
        <button type="submit" class="btn-submit">Se connecter</button>
        <p style="font-size: 13px; margin-top: 20px;"><a href="register.php" style="color: #666;">Créer un compte</a></p>
    </form>

    <div class="footer-text">
        &copy; <?php echo date('Y'); ?> Secourut's - Gestion Interne
    </div>
</div>

</body>
</html>