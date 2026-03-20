<?php
// parametres.php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "🛑 Accès refusé.";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_cat') {
            $nom = trim($_POST['nom']);
            if (!empty($nom)) {
                $pdo->prepare("INSERT INTO categories (nom, couleur_fond, couleur_texte) VALUES (?, ?, ?)")->execute([$nom, $_POST['couleur_fond'], $_POST['couleur_texte']]);

                logAction($pdo, "A ajouté la catégorie : $nom"); // NOUVEAU
                $_SESSION['flash_success'] = "✅ Catégorie '$nom' ajoutée.";
            }
        } elseif ($action === 'edit_cat') {
            $id = (int) $_POST['id'];
            $nom = trim($_POST['nom']);
            if (!empty($nom) && $id > 0) {
                $pdo->prepare("UPDATE categories SET nom = ?, couleur_fond = ?, couleur_texte = ? WHERE id = ?")->execute([$nom, $_POST['couleur_fond'], $_POST['couleur_texte'], $id]);

                logAction($pdo, "A modifié la catégorie : $nom"); // NOUVEAU
                $_SESSION['flash_success'] = "✏️ Catégorie mise à jour.";
            }
        } elseif ($action === 'delete_cat') {
            $id = (int) $_POST['id'];
            $nom_cat = $pdo->query("SELECT nom FROM categories WHERE id = $id")->fetchColumn();

            if ($pdo->prepare("SELECT COUNT(*) FROM materiels WHERE categorie_id = ?")->execute([$id]) > 0) {
                $_SESSION['flash_error'] = "❌ Impossible : Catégorie non vide.";
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);

                logAction($pdo, "A supprimé la catégorie : " . ($nom_cat ?: "ID $id")); // NOUVEAU
                $_SESSION['flash_success'] = "🗑️ Catégorie supprimée.";
            }
        } elseif ($action === 'add_type') {
            $nom = trim($_POST['nom']);
            if (!empty($nom)) {
                $pdo->prepare("INSERT INTO types_lieux (nom) VALUES (?)")->execute([$nom]);

                logAction($pdo, "A ajouté le type de stockage : $nom"); // NOUVEAU
                $_SESSION['flash_success'] = "✅ Nouveau type ajouté.";
            }
        } elseif ($action === 'edit_type') {
            $id = (int) $_POST['id'];
            $nom = trim($_POST['nom']);
            if (!empty($nom) && $id > 0) {
                $pdo->prepare("UPDATE types_lieux SET nom = ? WHERE id = ?")->execute([$nom, $id]);

                logAction($pdo, "A modifié le type de stockage : $nom"); // NOUVEAU
                $_SESSION['flash_success'] = "✏️ Type de stockage mis à jour.";
            }
        } elseif ($action === 'delete_type') {
            $id = (int) $_POST['id'];
            $nom_type = $pdo->query("SELECT nom FROM types_lieux WHERE id = $id")->fetchColumn();

            $pdo->prepare("DELETE FROM types_lieux WHERE id = ?")->execute([$id]);

            logAction($pdo, "A supprimé le type de stockage : " . ($nom_type ?: "ID $id")); // NOUVEAU
            $_SESSION['flash_success'] = "🗑️ Type supprimé.";

        } elseif ($action === 'add_icone') {
            if (!empty($_POST['icone'])) {
                $pdo->prepare("INSERT INTO icones_lieux (icone) VALUES (?)")->execute([trim($_POST['icone'])]);

                logAction($pdo, "A ajouté l'icône : " . trim($_POST['icone'])); // NOUVEAU
                $_SESSION['flash_success'] = "✅ Icône ajoutée.";
            }
        } elseif ($action === 'delete_icone') {
            $id = (int) $_POST['id'];
            $icone = $pdo->query("SELECT icone FROM icones_lieux WHERE id = $id")->fetchColumn();

            $pdo->prepare("DELETE FROM icones_lieux WHERE id = ?")->execute([$id]);

            logAction($pdo, "A supprimé l'icône : " . ($icone ?: "ID $id")); // NOUVEAU
            $_SESSION['flash_success'] = "🗑️ Icône supprimée.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "❌ Erreur BDD : " . $e->getMessage();
    }
    header("Location: parametres.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$types_lieux = $pdo->query("SELECT * FROM types_lieux ORDER BY nom")->fetchAll();
$icones = $pdo->query("SELECT * FROM icones_lieux ORDER BY id")->fetchAll();
require_once 'includes/header.php';
?>

<div class="flex-between mb-20">
    <h2 class="page-title text-dark mb-0">⚙️ Paramètres Généraux</h2>
    <span class="badge badge-pill btn-danger-dark">Espace Administrateur</span>
</div>

<div class="flex-row">

    <div class="white-box flex-1 min-w-350">
        <h3 class="section-title">🏷️ Catégories de Matériel</h3>

        <form method="POST" action="parametres.php" class="flex-center form-box mb-20">
            <input type="hidden" name="action" value="add_cat">
            <input type="text" name="nom" placeholder="Nouvelle catégorie" required class="input-field flex-2 mb-0">
            <input type="color" name="couleur_fond" value="#2c3e50" title="Fond"
                style="height: 40px; width: 40px; border: none; cursor: pointer;">
            <input type="color" name="couleur_texte" value="#ffffff" title="Texte"
                style="height: 40px; width: 40px; border: none; cursor: pointer;">
            <button type="submit" class="btn btn-success-dark">+</button>
        </form>
        <div class="table-responsive">
            <table class="table-manager" style="width: 100%;">
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <form method="POST" action="parametres.php">
                                <input type="hidden" name="action" value="edit_cat">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <td style="width: 40%;">
                                    <input type="text" name="nom" value="<?php echo htmlspecialchars($cat['nom']); ?>"
                                        required class="input-field mb-0" style="padding: 5px;">
                                </td>
                                <td class="text-center" style="width: 15%;"><input type="color" name="couleur_fond"
                                        value="<?php echo htmlspecialchars($cat['couleur_fond'] ?? '#2c3e50'); ?>"></td>
                                <td class="text-center" style="width: 15%;"><input type="color" name="couleur_texte"
                                        value="<?php echo htmlspecialchars($cat['couleur_texte'] ?? '#ffffff'); ?>"></td>
                                <td class="text-right" style="width: 30%;">
                                    <div class="flex-center" style="justify-content: flex-end;">
                                        <button type="submit" class="btn btn-success btn-sm">💾</button>
                            </form>
                            <form method="POST" action="parametres.php" onsubmit="return confirm('Supprimer ?');"
                                class="mb-0">
                                <input type="hidden" name="action" value="delete_cat">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
            </div>
            </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>
</div>

<div class="flex-1 min-w-350" style="display: flex; flex-direction: column; gap: 20px;">
    <div class="white-box mb-0">
        <h3 class="section-title">📋 Types de Stockage</h3>

        <form method="POST" action="parametres.php" class="flex-row-sm align-center mb-15">
            <input type="hidden" name="action" value="add_type">
            <input type="text" name="nom" placeholder="Ex: Boîte Pharmacie" required
                class="input-field flex-1 min-w-150 mb-0">
            <button type="submit" class="btn btn-success-dark">Ajouter</button>
        </form>

        <table class="table-manager" style="width: 100%;">
            <tbody>
                <?php foreach ($types_lieux as $type): ?>
                    <tr>
                        <form method="POST" action="parametres.php">
                            <input type="hidden" name="action" value="edit_type">
                            <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                            <td class="font-bold text-dark" style="width: 60%;">
                                <input type="text" name="nom" value="<?php echo htmlspecialchars($type['nom']); ?>" required
                                    class="input-field mb-0" style="padding: 5px;">
                            </td>
                            <td class="text-right" style="width: 40%;">
                                <div class="flex-center" style="justify-content: flex-end;">
                                    <button type="submit" class="btn btn-success btn-sm">💾</button>
                        </form>
                        <form method="POST" action="parametres.php" onsubmit="return confirm('Supprimer ce type ?');"
                            class="mb-0">
                            <input type="hidden" name="action" value="delete_type">
                            <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
        </div>
        </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
</div>

<div class="white-box mb-0">
    <h3 class="section-title">🖼️ Icônes disponibles</h3>
    <form method="POST" action="parametres.php" class="flex-center mb-15">
        <input type="hidden" name="action" value="add_icone">
        <input type="text" name="icone" placeholder="Emoji (ex: 🩸)" required class="input-field flex-1 mb-0">
        <button type="submit" class="btn btn-success-dark">Ajouter</button>
    </form>
    <div class="flex-row-sm">
        <?php foreach ($icones as $ic): ?>
            <form method="POST" action="parametres.php" onsubmit="return confirm('Supprimer ?');" class="mb-0">
                <input type="hidden" name="action" value="delete_icone">
                <input type="hidden" name="id" value="<?php echo $ic['id']; ?>">
                <button type="submit" title="Cliquez pour supprimer" class="btn-outline-primary"
                    style="font-size: 24px; padding: 10px; border-color: #ccc; border-radius: 8px;">
                    <?php echo htmlspecialchars($ic['icone']); ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
</div>
</div>
<?php require_once 'includes/footer.php'; ?>