<?php
// register.php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }
    // ----------------------------------
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!empty($username) && !empty($password)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Correction ici : nom_utilisateur et mot_de_passe
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role, can_view, can_edit) VALUES (?, ?, 'user', 0, 0)");
            $stmt->execute([$username, $hash]);
            $success = "Compte créé ! Un administrateur doit maintenant l'activer.";
        } catch (PDOException $e) {
            $error = "Ce nom d'utilisateur est déjà pris.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Inscription - Matos Manager</title>
    <style>
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-box h2 {
            color: #d32f2f;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            font-size: 22px;
        }

        .login-box h3 {
            color: #555;
            margin: 0 0 30px 0;
            font-weight: normal;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }

        input:focus {
            border-color: #d32f2f;
            outline: none;
        }

        button {
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

        button:hover {
            background-color: #b71c1c;
        }

        .msg {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>Secourut's</h2>
        <h3>CRÉER UN COMPTE</h3>

        <?php if ($error): ?>
            <div class="msg" style="background: #ffebee; color: #c62828; border: 1px solid #ffcdd2;"><?php echo $error; ?>
            </div><?php endif; ?>
        <?php if ($success): ?>
            <div class="msg" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9;"><?php echo $success; ?>
                <br><br><a href="login.php" style="color:#2e7d32; font-weight: bold; text-decoration: none;">Aller à la
                    connexion</a></div><?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div style="text-align: left;"><label
                        style="font-weight: bold; color: #333; display: block; margin-bottom: 8px;">Nom
                        d'utilisateur</label>
                    <input type="text" name="username" required autocomplete="off">
                </div>

                <div style="text-align: left;"><label
                        style="font-weight: bold; color: #333; display: block; margin-bottom: 8px;">Mot de passe</label>
                    <input type="password" name="password" required>
                </div>

                <div style="text-align: left;"><label
                        style="font-weight: bold; color: #333; display: block; margin-bottom: 8px;">Confirmer le mot de
                        passe</label>
                    <input type="password" name="password_confirm" required>
                </div>

                <button type="submit">S'inscrire</button>
            </form>
            <p style="font-size: 13px; margin-top: 25px;"><a href="login.php"
                    style="color: #666; text-decoration: none;">J'ai déjà un compte</a></p>
        <?php endif; ?>
    </div>
</body>

</html>