<?php
// utilisateurs.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// SÉCURITÉ : Seul un admin a le droit d'être ici !
if ($_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-danger'>🛑 Accès refusé. Seuls les administrateurs peuvent gérer les utilisateurs.</div>");
}

// =========================================================================
// 1. TRAITEMENT DES FORMULAIRES (POST-REDIRECT-GET)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }
    // ----------------------------------

    $action = $_POST['action'] ?? '';

    // --- AJOUT D'UN UTILISATEUR "HORS CAS" ---
    if ($action === 'ajouter_utilisateur') {
        $nom = trim($_POST['nom_utilisateur']);
        $mdp = $_POST['mot_de_passe'];
        $role = $_POST['role'] ?? 'secouriste';

        if (!empty($nom) && !empty($mdp)) {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $hash, $role]);
                $_SESSION['flash_success'] = "✅ L'utilisateur '$nom' a été créé avec succès.";
                // Si la fonction logAction existe dans auth.php ou db.php, on l'appelle
                if (function_exists('logAction'))
                    logAction($pdo, "Création du compte local : $nom (Rôle: $role)");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "❌ Ce nom d'utilisateur existe déjà.";
                } else {
                    $_SESSION['flash_error'] = "❌ Erreur BDD : " . $e->getMessage();
                }
            }
        } else {
            $_SESSION['flash_error'] = "❌ Le nom et le mot de passe sont obligatoires pour un compte local.";
        }
        header('Location: utilisateurs.php');
        exit;
    }

    // --- MODIFICATION DES DROITS ---
    elseif ($action === 'modifier_droits') {
        $u_id = (int) $_POST['user_id'];
        $role = $_POST['role'];
        $can_view = isset($_POST['can_view']) ? 1 : 0;
        $can_edit = isset($_POST['can_edit']) ? 1 : 0;

        // On empêche un admin de se retirer ses propres droits par erreur
        if ($u_id === $_SESSION['user_id']) {
            $role = 'admin';
            $can_view = 1;
            $can_edit = 1;
        }

        $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ?, can_view = ?, can_edit = ? WHERE id = ?");
        $stmt->execute([$role, $can_view, $can_edit, $u_id]);
        $_SESSION['flash_success'] = "✅ Droits mis à jour avec succès !";

        header('Location: utilisateurs.php');
        exit;
    }

    // --- SUPPRESSION D'UN UTILISATEUR ---
    elseif ($action === 'supprimer_utilisateur') {
        $u_id = (int) $_POST['user_id'];
        if ($u_id !== $_SESSION['user_id']) { // On ne se supprime pas soi-même
            $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$u_id]);
            $_SESSION['flash_success'] = "🗑️ Utilisateur supprimé.";
        } else {
            $_SESSION['flash_error'] = "❌ Vous ne pouvez pas vous supprimer vous-même.";
        }
        header('Location: utilisateurs.php');
        exit;
    }
}

// =========================================================================
// 2. RÉCUPÉRATION DES DONNÉES
// =========================================================================
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY role ASC, nom_utilisateur ASC")->fetchAll();

// =========================================================================
// 3. AFFICHAGE HTML
// =========================================================================
require_once 'includes/header.php';
?>

<div class="white-box">
    <h2 class="page-title border-bottom pb-10">👥 Gestion des Accréditations</h2>
    <p class="text-muted mb-20">Gérez ici qui peut consulter et qui peut modifier le stock de l'association.</p>

    <div class="form-container mb-30">
        <h3 class="section-title">➕ Créer un compte local (Hors CAS)</h3>

        <form method="POST" action="" class="flex-row align-center">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="ajouter_utilisateur">

            <div class="flex-1 min-w-200">
                <label class="font-bold text-dark display-block mb-5">Nom d'utilisateur</label>
                <input type="text" name="nom_utilisateur" required placeholder="Ex: medecin_urgences"
                    class="input-field">
            </div>

            <div class="flex-1 min-w-200">
                <label class="font-bold text-dark display-block mb-5">Mot de passe</label>
                <input type="password" name="mot_de_passe" required placeholder="Mot de passe sécurisé"
                    class="input-field">
            </div>

            <div class="flex-1 min-w-150">
                <label class="font-bold text-dark display-block mb-5">Rôle</label>
                <select name="role" class="input-field">
                    <option value="secouriste">Secouriste</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>

            <div style="margin-top: 25px;">
                <button type="submit" class="btn btn-success-dark">Créer le compte</button>
            </div>
        </form>
        <p class="text-sm text-muted mt-10 mb-0">
            ℹ️ <i>Ces utilisateurs devront se connecter via le formulaire classique, sans utiliser le bouton CAS de
                l'UTC.</i>
        </p>
    </div>

    <div class="table-responsive">
        <table class="table-manager">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th class="text-center">Rôle</th>
                    <th class="text-center">Peut Voir (Consultation)</th>
                    <th class="text-center">Peut Éditer (Action)</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u):
                    $est_moi = ($u['id'] == $_SESSION['user_id']);
                    $est_nouveau = ($u['can_view'] == 0 && $u['role'] !== 'admin');
                    // On crée un ID unique pour lier les inputs au formulaire de cette ligne (Astuce HTML5)
                    $form_id = "form_edit_" . $u['id'];
                    ?>
                    <tr>
                        <td class="font-bold text-dark">
                            <form id="<?php echo $form_id; ?>" method="POST" action="" class="mb-0">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="modifier_droits">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            </form>

                            <?php echo htmlspecialchars($u['nom_utilisateur']); ?>
                            <?php if ($est_moi)
                                echo "<span class='text-muted text-sm ml-10'>(Vous)</span>"; ?>
                            <?php if ($est_nouveau)
                                echo "<span class='badge badge-warning ml-10'>EN ATTENTE</span>"; ?>
                        </td>

                        <td class="text-center">
                            <select name="role" form="<?php echo $form_id; ?>" class="input-field" <?php echo $est_moi ? 'disabled' : ''; ?> style="padding: 5px; width: auto; margin: 0 auto;">
                                <option value="user" <?php echo ($u['role'] === 'user') ? 'selected' : ''; ?>>Secouriste
                                </option>
                                <option value="admin" <?php echo ($u['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur
                                </option>
                            </select>
                        </td>

                        <td class="text-center">
                            <input type="checkbox" name="can_view" value="1" form="<?php echo $form_id; ?>" <?php echo ($u['can_view'] || $u['role'] === 'admin') ? 'checked' : ''; ?>     <?php echo $est_moi ? 'disabled' : ''; ?> style="transform: scale(1.3);">
                        </td>

                        <td class="text-center">
                            <input type="checkbox" name="can_edit" value="1" form="<?php echo $form_id; ?>" <?php echo ($u['can_edit'] || $u['role'] === 'admin') ? 'checked' : ''; ?>     <?php echo $est_moi ? 'disabled' : ''; ?> style="transform: scale(1.3);">
                        </td>

                        <td class="text-center">
                            <div class="flex-center" style="justify-content: center;">
                                <?php if (!$est_moi): ?>
                                    <button type="submit" form="<?php echo $form_id; ?>" class="btn btn-sm btn-success">💾
                                        Valider</button>
                                <?php else: ?>
                                    <span class="text-muted font-italic text-sm">Protégé</span>
                                <?php endif; ?>

                                <?php if (!$est_moi): ?>
                                    <form method="POST" action=""
                                        onsubmit="return confirm('Supprimer définitivement ce compte ?');" class="mb-0 ml-10">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="supprimer_utilisateur">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>