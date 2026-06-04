<?php
// register.php
session_start();
require_once 'config/db.php';

// Initialisation du token CSRF (bug corrigé — absent dans la version originale)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding:20px;background:#ffebee;color:#c62828;font-weight:bold;border-radius:5px;margin:20px;'>🛑 Action bloquée : Jeton CSRF invalide ou expiré. Veuillez recharger la page.</div>");
    }

    $username         = trim($_POST['username']);
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!empty($username) && !empty($password)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role, can_view, can_edit) VALUES (?, ?, 'user', 0, 0)")
                ->execute([$username, $hash]);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Matos Manager</title>
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body class="login-page">

    <div class="login-box">
        <h2>Secourut's</h2>
        <h3>CRÉER UN COMPTE</h3>

        <?php if ($error): ?>
            <div class="register-msg register-msg--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="register-msg register-msg--success">
                <?php echo htmlspecialchars($success); ?><br><br>
                <a href="login.php">Aller à la connexion</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" style="text-align:left;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label class="form-label">Nom d'utilisateur</label>
            <input type="text" name="username" class="input-field" required autocomplete="off">

            <label class="form-label mt-10">Mot de passe</label>
            <input type="password" name="password" class="input-field" required>

            <label class="form-label mt-10">Confirmer le mot de passe</label>
            <input type="password" name="password_confirm" class="input-field mb-20" required>

            <button type="submit" class="btn btn-danger-dark btn-full">S'inscrire</button>
        </form>

        <p class="register-back-link"><a href="login.php">J'ai déjà un compte</a></p>
        <?php endif; ?>
    </div>

</body>
</html>
