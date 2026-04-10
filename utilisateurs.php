<?php
// utilisateurs.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// SÉCURITÉ : Seul un admin a le droit d'être ici !
if (!$est_admin) {
    die("<div class='alert alert-danger'>🛑 Accès refusé. Seuls les administrateurs peuvent gérer les utilisateurs.</div>");
}

// =========================================================================
// 1. TRAITEMENT DES FORMULAIRES (POST-REDIRECT-GET)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité.</div>");
    }

    $action = $_POST['action'] ?? '';

    // --- AJOUT D'UN UTILISATEUR "HORS CAS" ---
    if ($action === 'ajouter_utilisateur') {
        $nom = trim($_POST['nom_utilisateur']);
        $mdp = $_POST['mot_de_passe'];
        $role = $_POST['role'] ?? 'consultation';

        if (!empty($nom) && !empty($mdp)) {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            try {
                // On n'insère plus can_view et can_edit, seul le rôle compte
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $hash, $role]);
                $_SESSION['flash_success'] = "✅ L'utilisateur '$nom' a été créé avec succès.";

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
            $_SESSION['flash_error'] = "❌ Le nom et le mot de passe sont obligatoires.";
        }
        header('Location: utilisateurs.php');
        exit;
    }

    // --- MODIFICATION DES RÔLES ---
    elseif ($action === 'modifier_role') {
        $u_id = (int) $_POST['user_id'];
        $role = $_POST['role'];

        // On empêche un admin de se retirer ses propres droits par erreur
        if ($u_id === $_SESSION['user_id']) {
            $role = 'admin';
        }

        $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
        $stmt->execute([$role, $u_id]);
        $_SESSION['flash_success'] = "✅ Rôle mis à jour avec succès !";

        header('Location: utilisateurs.php');
        exit;
    }

    // --- SUPPRESSION D'UN UTILISATEUR ---
    elseif ($action === 'supprimer_utilisateur') {
        $u_id = (int) $_POST['user_id'];
        if ($u_id !== $_SESSION['user_id']) {
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
$utilisateurs = $pdo->query("SELECT id, nom_utilisateur, role FROM utilisateurs ORDER BY role ASC, nom_utilisateur ASC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="white-box">
    <h2 class="page-title border-bottom pb-10">👥 Gestion des Utilisateurs et Rôles</h2>
    <p class="text-muted mb-20">Attribuez ici les permissions aux différents membres de l'association.</p>

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
                <label class="font-bold text-dark display-block mb-5">Rôle attribué</label>
                <select name="role" class="input-field">
                    <option value="consultation">👀 Consultation (Défaut)</option>
                    <option value="matos">🎒 Équipe Matos</option>
                    <option value="operationnel">🚑 Opérationnel (DPS)</option>
                    <option value="admin">👑 Administrateur</option>
                </select>
            </div>

            <div style="margin-top: 25px;">
                <button type="submit" class="btn btn-success-dark">Créer le compte</button>
            </div>
        </form>
        <p class="text-sm text-muted mt-10 mb-0">
            ℹ️ <i>Tous les nouveaux comptes (créés ici ou via CAS) ont le rôle "Consultation" par défaut.</i>
        </p>
    </div>

    <div class="table-responsive">
        <table class="table-manager">
            <thead>
                <tr>
                    <th style="width: 40%;">Utilisateur</th>
                    <th class="text-center" style="width: 40%;">Niveau d'accès (Rôle)</th>
                    <th class="text-center" style="width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u):
                    $est_moi = ($u['id'] == $_SESSION['user_id']);
                    $form_id = "form_edit_" . $u['id'];
                    ?>
                    <tr>
                        <td class="font-bold text-dark">
                            <form id="<?php echo $form_id; ?>" method="POST" action="" class="mb-0">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="modifier_role">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            </form>

                            <?php echo htmlspecialchars($u['nom_utilisateur']); ?>
                            <?php if ($est_moi)
                                echo "<span class='text-muted text-sm ml-10'>(Vous)</span>"; ?>
                        </td>

                        <td class="text-center">
                            <select name="role" form="<?php echo $form_id; ?>" class="input-field" <?php echo $est_moi ? 'disabled' : ''; ?> style="padding: 8px; width: 80%; margin: 0 auto; font-weight: bold;">
                                <option value="consultation" <?php echo ($u['role'] === 'consultation') ? 'selected' : ''; ?>>
                                    👀 Consultation</option>
                                <option value="matos" <?php echo ($u['role'] === 'matos') ? 'selected' : ''; ?>>🎒 Équipe
                                    Matos</option>
                                <option value="operationnel" <?php echo ($u['role'] === 'operationnel') ? 'selected' : ''; ?>>
                                    🚑 Opérationnel (DPS)</option>
                                <option value="admin" <?php echo ($u['role'] === 'admin') ? 'selected' : ''; ?>>👑
                                    Administrateur</option>
                            </select>
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