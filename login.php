<?php
// login.php
session_start();

require_once 'vendor/autoload.php';
require_once 'config/db.php';

// --- GÉNÉRATION DU JETON CSRF ---
// Nécessaire car auth.php n'est pas chargé sur cette page
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Si déjà connecté, on redirige vers l'accueil
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Récupération du message de déconnexion
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// =========================================================
// 1. TRAITEMENT DU FORMULAIRE CLASSIQUE (Comptes Locaux/Admin)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }
    // ----------------------------------

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE nom_utilisateur = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['mot_de_passe'] !== 'OAUTH_UTC') {
            if (password_verify($password, $user['mot_de_passe'])) {

                // On connecte l'utilisateur (le rôle sera affiné par auth.php sur les autres pages)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nom_utilisateur'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                header("Location: index.php");
                exit;
            } else {
                $error = "Identifiant ou mot de passe incorrect.";
            }
        } else {
            $error = "Identifiant introuvable ou compte géré par le portail UTC. Veuillez utiliser le bouton de connexion UTC.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

// =========================================================
// 2. CONFIGURATION DU FOURNISSEUR OAUTH2
// =========================================================
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId' => $_ENV['SECOURUTS_OAUTH_CLIENT_ID'],
    'clientSecret' => $_ENV['SECOURUTS_OAUTH_CLIENT_SECRET'],
    'redirectUri' => 'https://assos.utc.fr/secouruts/intranet/login.php', // A CHANGER PAR TON VRAI LIEN EXACT
    'urlAuthorize' => 'https://auth.assos.utc.fr/oauth/authorize',
    'urlAccessToken' => 'https://auth.assos.utc.fr/oauth/token',
    'urlResourceOwnerDetails' => 'https://auth.assos.utc.fr/api/user',
    'scopes' => 'users-infos read-assos read-assos-history read-memberships'
]);

// =========================================================
// 3. DÉPART VERS LE PORTAIL DE CONNEXION (OAuth)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $authorizationUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authorizationUrl);
    exit;
}

// =========================================================
// 4. RETOUR DU PORTAIL APRÈS CONNEXION (Callback OAuth)
// =========================================================
elseif (isset($_GET['code'])) {

    if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
        if (isset($_SESSION['oauth2state']))
            unset($_SESSION['oauth2state']);
        $error = "Erreur de sécurité (Invalid State). Veuillez réessayer.";
    } else {
        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            $resourceOwner = $provider->getResourceOwner($accessToken);
            $userDetails = $resourceOwner->toArray();

            $requestAssos = $provider->getAuthenticatedRequest(
                'GET',
                'https://auth.assos.utc.fr/api/user/associations/current',
                $accessToken
            );
            $assosResponse = $provider->getParsedResponse($requestAssos);

            if (isset($userDetails['deleted_at']) && $userDetails['deleted_at'] !== null) {
                throw new Exception('Ce compte étudiant a été désactivé par l\'école.');
            }
            if (isset($userDetails['provider']) && $userDetails['provider'] !== 'cas') {
                throw new Exception('Fournisseur de connexion non autorisé.');
            }

            $prenom = $userDetails['firstName'] ?? '';
            $nom_famille = $userDetails['lastName'] ?? '';
            $nom_complet = trim($prenom . ' ' . $nom_famille);

            if (empty($nom_complet)) {
                $nom_complet = $userDetails['provider_data']['username'] ?? 'Utilisateur UTC';
            }

            $est_dans_secouruts = false;
            $liste_assos = $assosResponse['data'] ?? $assosResponse;

            if (is_array($liste_assos)) {
                foreach ($liste_assos as $asso) {
                    if (isset($asso['login']) && strtolower($asso['login']) === 'secouruts') {
                        $est_dans_secouruts = true;
                        break;
                    }
                }
            }

            if ($est_dans_secouruts) {
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE nom_utilisateur = ?");
                $stmt->execute([$nom_complet]);
                $user_local = $stmt->fetch();

                if (!$user_local) {
                    // NOUVEAU : On insère directement le rôle 'consultation'
                    $stmt_insert = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES (?, 'OAUTH_UTC', 'consultation')");
                    $stmt_insert->execute([$nom_complet]);
                    $user_id = $pdo->lastInsertId();
                    $role = 'consultation';
                } else {
                    $user_id = $user_local['id'];
                    $role = $user_local['role'];
                }

                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $nom_complet;
                $_SESSION['role'] = $role;
                $_SESSION['last_activity'] = time();

                header("Location: index.php");
                exit;

            } else {
                $error = "Accès refusé. Vous n'êtes pas reconnu comme membre de l'association Secourut's sur le portail étudiant.";
            }

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            $error = 'Échec de l\'authentification : ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Secourut's Matos</title>
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>

<body class="login-page">
    <div class="login-box">
        <img src="assets/img/favicon.png" alt="Logo" style="width: 80px; margin-bottom: 15px;">
        <h2>Secourut's</h2>
        <h3>MATOS MANAGER</h3>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="info-msg"><?php echo $success; ?></div>
        <?php endif; ?>

        <a href="login.php?action=login" class="btn-oauth">Se connecter avec le CAS-UTC</a>

        <div class="separator">Ou connexion locale</div>

        <form method="POST" action="login.php" style="text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label
                style="font-weight: bold; color: #333; display: block; margin-bottom: 5px; font-size: 13px;">Identifiant
                local</label>
            <input type="text" name="username" class="input-field" required autocomplete="off">

            <label style="font-weight: bold; color: #333; display: block; margin-bottom: 5px; font-size: 13px;">Mot de
                passe</label>
            <input type="password" name="password" class="input-field" required>

            <button type="submit" class="btn-local">Connexion Admin</button>
        </form>
    </div>
</body>

</html>