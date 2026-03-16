<?php
// utilisateurs.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// SÉCURITÉ : Seul un admin a le droit d'être ici !
if ($_SESSION['role'] !== 'admin') {
    die("<div style='padding: 20px; color: red;'>🛑 Accès refusé. Seuls les administrateurs peuvent gérer les utilisateurs.</div>");
}

// --- GESTION DES UTILISATEURS (AJOUT / MODIFICATION / SUPPRESSION) ---
$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. AJOUTER UN UTILISATEUR "HORS CAS"
    if ($action === 'ajouter_utilisateur') {
        $nom = trim($_POST['nom_utilisateur']);
        $mdp = $_POST['mot_de_passe'];
        $role = $_POST['role'] ?? 'secouriste';

        if (!empty($nom) && !empty($mdp)) {
            // Sécurité : On hache le mot de passe avant de le stocker
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $hash, $role]);
                $message = "✅ L'utilisateur '$nom' a été créé avec succès.";
                logAction($pdo, "Création du compte local : $nom (Rôle: $role)");
            } catch (PDOException $e) {
                // Si l'erreur est un doublon (le pseudo existe déjà)
                if ($e->getCode() == 23000) {
                    $erreur = "❌ Ce nom d'utilisateur existe déjà.";
                } else {
                    $erreur = "❌ Erreur BDD : " . $e->getMessage();
                }
            }
        } else {
            $erreur = "❌ Le nom et le mot de passe sont obligatoires pour un compte local.";
        }
    }

    // 2. MODIFIER UN UTILISATEUR
    elseif ($action === 'modifier_utilisateur') {
        $id_modif = $_POST['id_utilisateur'];
        $nouveau_role = $_POST['role'];
        $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';

        try {
            if (!empty($nouveau_mdp)) {
                // Si l'admin a tapé un nouveau mot de passe, on le met à jour
                $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ?, mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nouveau_role, $hash, $id_modif]);
                $message = "✅ Rôle et mot de passe mis à jour.";
            } else {
                // Sinon, on ne met à jour que le rôle
                $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
                $stmt->execute([$nouveau_role, $id_modif]);
                $message = "✅ Rôle mis à jour avec succès.";
            }
            logAction($pdo, "Modification du profil ID $id_modif");
        } catch (PDOException $e) {
            $erreur = "❌ Erreur lors de la modification.";
        }
    }
}

// --- RÉCUPÉRATION DE LA LISTE POUR L'AFFICHAGE ---
$stmt_users = $pdo->query("SELECT * FROM utilisateurs ORDER BY role ASC, nom_utilisateur ASC");
$utilisateurs = $stmt_users->fetchAll();

$message = '';

// Traitement des modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'modifier_droits') {
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
        $message = "<div style='background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>✅ Droits mis à jour avec succès !</div>";
    }

    if ($_POST['action'] === 'supprimer_utilisateur') {
        $u_id = (int) $_POST['user_id'];
        if ($u_id !== $_SESSION['user_id']) { // On ne se supprime pas soi-même
            $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$u_id]);
            $message = "<div style='background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>🗑️ Utilisateur supprimé.</div>";
        }
    }
}

// Récupérer tous les utilisateurs (Correction ici : nom_utilisateur au lieu de username)
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY role, nom_utilisateur")->fetchAll();

require_once 'includes/header.php';
?>

<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
    <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #d32f2f; padding-bottom: 10px;">👥 Gestion des
        Accréditations</h2>
    <p style="color: #666; margin-bottom: 20px;">Gérez ici qui peut consulter et qui peut modifier le stock de
        l'association.</p>

    <?php echo $message; ?>

    <?php if (!empty($message)): ?>
        <div style="background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($erreur)): ?>
        <div style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $erreur; ?>
        </div>
    <?php endif; ?>

    <div class="white-box" style="margin-bottom: 30px;">
        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
            ➕ Créer un compte local (Hors CAS)
        </h3>
        <form method="POST" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="ajouter_utilisateur">

            <div style="flex: 1; min-width: 200px;">
                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px;">Nom
                    d'utilisateur</label>
                <input type="text" name="nom_utilisateur" required placeholder="Ex: medecin_urgences"
                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="flex: 1; min-width: 200px;">
                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px;">Mot de passe</label>
                <input type="password" name="mot_de_passe" required placeholder="Mot de passe sécurisé"
                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px;">Rôle</label>
                <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="secouriste">Secouriste</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>

            <button type="submit" class="btn-valider"
                style="background-color: #2e7d32; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; height: 40px;">
                Créer le compte
            </button>
        </form>
        <p style="font-size: 12px; color: #888; margin-top: 10px;">
            ℹ️ <i>Ces utilisateurs devront se connecter via le formulaire classique, sans utiliser le bouton CAS de
                l'UTC.</i>
        </p>
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f8f9fa; font-size: 12px; color: #666; text-transform: uppercase;">
                <th style="padding: 12px; border-bottom: 2px solid #ddd; text-align: left;">Utilisateur</th>
                <th style="padding: 12px; border-bottom: 2px solid #ddd; text-align: center;">Rôle</th>
                <th style="padding: 12px; border-bottom: 2px solid #ddd; text-align: center;">Peut Voir (Consultation)
                </th>
                <th style="padding: 12px; border-bottom: 2px solid #ddd; text-align: center;">Peut Éditer (Action)</th>
                <th style="padding: 12px; border-bottom: 2px solid #ddd; text-align: center;">Sauvegarder</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($utilisateurs as $u):
                $est_moi = ($u['id'] == $_SESSION['user_id']);
                $est_nouveau = ($u['can_view'] == 0 && $u['role'] !== 'admin');
                ?>
                <tr style="border-bottom: 1px solid #eee; <?php echo $est_nouveau ? 'background-color: #fff3e0;' : ''; ?>">
                    <form method="POST" action="utilisateurs.php">
                        <input type="hidden" name="action" value="modifier_droits">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">

                        <td style="padding: 12px; font-weight: bold; color: #333;">
                            <?php echo htmlspecialchars($u['nom_utilisateur']); ?>
                            <?php if ($est_moi)
                                echo "<span style='color: #999; font-size: 10px;'>(Vous)</span>"; ?>
                            <?php if ($est_nouveau)
                                echo "<span style='background: #ef6c00; color: white; padding: 2px 5px; border-radius: 4px; font-size: 10px; margin-left: 5px;'>EN ATTENTE</span>"; ?>
                        </td>

                        <td style="padding: 12px; text-align: center;">
                            <select name="role" <?php echo $est_moi ? 'disabled' : ''; ?>
                                style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="user" <?php if ($u['role'] === 'user')
                                    echo 'selected'; ?>>Secouriste
                                </option>
                                <option value="admin" <?php if ($u['role'] === 'admin')
                                    echo 'selected'; ?>>Administrateur
                                </option>
                            </select>
                        </td>

                        <td style="padding: 12px; text-align: center;">
                            <input type="checkbox" name="can_view" value="1" <?php echo ($u['can_view'] || $u['role'] === 'admin') ? 'checked' : ''; ?>     <?php echo $est_moi ? 'disabled' : ''; ?>
                                style="transform: scale(1.5);">
                        </td>

                        <td style="padding: 12px; text-align: center;">
                            <input type="checkbox" name="can_edit" value="1" <?php echo ($u['can_edit'] || $u['role'] === 'admin') ? 'checked' : ''; ?>     <?php echo $est_moi ? 'disabled' : ''; ?>
                                style="transform: scale(1.5);">
                        </td>

                        <td style="padding: 12px; text-align: center; display: flex; gap: 5px; justify-content: center;">
                            <?php if (!$est_moi): ?>
                                <button type="submit"
                                    style="background: #4caf50; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">💾
                                    Valider</button>
                            <?php else: ?>
                                <span style="color: #aaa; font-style: italic;">Protégé</span>
                            <?php endif; ?>
                    </form>

                    <?php if (!$est_moi): ?>
                        <form method="POST" action="utilisateurs.php"
                            onsubmit="return confirm('Supprimer définitivement ce compte ?');" style="margin: 0;">
                            <input type="hidden" name="action" value="supprimer_utilisateur">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit"
                                style="background: #f44336; color: white; border: none; padding: 8px 10px; border-radius: 4px; font-weight: bold; cursor: pointer;">🗑️</button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>