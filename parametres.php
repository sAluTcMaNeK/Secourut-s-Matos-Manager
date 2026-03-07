<?php
// parametres.php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "🛑 Accès refusé.";
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. MISE À JOUR DE LA BASE DE DONNÉES
// ==========================================
try {
    $pdo->exec("ALTER TABLE categories ADD COLUMN couleur_fond TEXT DEFAULT '#2c3e50'");
    $pdo->exec("ALTER TABLE categories ADD COLUMN couleur_texte TEXT DEFAULT '#ffffff'");
} catch (PDOException $e) {
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS types_lieux (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS icones_lieux (id INTEGER PRIMARY KEY AUTOINCREMENT, icone TEXT)");

    // NOUVEAU : Ajout du paramètre "est_reserve"
    $pdo->exec("ALTER TABLE types_lieux ADD COLUMN est_reserve INTEGER DEFAULT 0");
    // On passe automatiquement l'ancien type "Réserve" en réserve officielle
    $pdo->exec("UPDATE types_lieux SET est_reserve = 1 WHERE nom LIKE '%éserve%'");

    if ($pdo->query("SELECT COUNT(*) FROM types_lieux")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO types_lieux (nom, est_reserve) VALUES ('Sac d''intervention', 0), ('Sac logistique', 0), ('Réserve', 1)");
    }
    if ($pdo->query("SELECT COUNT(*) FROM icones_lieux")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO icones_lieux (icone) VALUES ('🎒'), ('💼'), ('🏢'), ('🧰'), ('🚑'), ('💊')");
    }
} catch (PDOException $e) {
}

// ==========================================
// 2. TRAITEMENT DES FORMULAIRES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_cat') {
            $nom = trim($_POST['nom']);
            if (!empty($nom)) {
                $pdo->prepare("INSERT INTO categories (nom, couleur_fond, couleur_texte) VALUES (?, ?, ?)")->execute([$nom, $_POST['couleur_fond'], $_POST['couleur_texte']]);
                $_SESSION['flash_success'] = "✅ Catégorie '$nom' ajoutée.";
            }
        } elseif ($action === 'edit_cat') {
            $id = (int) $_POST['id'];
            $nom = trim($_POST['nom']);
            if (!empty($nom) && $id > 0) {
                $pdo->prepare("UPDATE categories SET nom = ?, couleur_fond = ?, couleur_texte = ? WHERE id = ?")->execute([$nom, $_POST['couleur_fond'], $_POST['couleur_texte'], $id]);
                $_SESSION['flash_success'] = "✏️ Catégorie mise à jour.";
            }
        } elseif ($action === 'delete_cat') {
            $id = (int) $_POST['id'];
            if ($pdo->prepare("SELECT COUNT(*) FROM materiels WHERE categorie_id = ?")->execute([$id]) > 0) {
                $_SESSION['flash_error'] = "❌ Impossible : Catégorie non vide.";
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "🗑️ Catégorie supprimée.";
            }
        }
        // --- NOUVEAU : GESTION DES TYPES AVEC CASE À COCHER ---
        elseif ($action === 'add_type') {
            $nom = trim($_POST['nom']);
            $est_reserve = isset($_POST['est_reserve']) ? 1 : 0;
            if (!empty($nom)) {
                $pdo->prepare("INSERT INTO types_lieux (nom, est_reserve) VALUES (?, ?)")->execute([$nom, $est_reserve]);
                $_SESSION['flash_success'] = "✅ Nouveau type ajouté.";
            }
        } elseif ($action === 'delete_type') {
            $pdo->prepare("DELETE FROM types_lieux WHERE id = ?")->execute([(int) $_POST['id']]);
            $_SESSION['flash_success'] = "🗑️ Type supprimé.";
        } elseif ($action === 'add_icone') {
            if (!empty($_POST['icone'])) {
                $pdo->prepare("INSERT INTO icones_lieux (icone) VALUES (?)")->execute([trim($_POST['icone'])]);
                $_SESSION['flash_success'] = "✅ Icône ajoutée.";
            }
        } elseif ($action === 'delete_icone') {
            $pdo->prepare("DELETE FROM icones_lieux WHERE id = ?")->execute([(int) $_POST['id']]);
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="color: #2c3e50; margin: 0;">⚙️ Paramètres Généraux</h2>
    <span
        style="background: #d32f2f; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold;">Espace
        Administrateur</span>
</div>

<div style="display: flex; flex-wrap: wrap; gap: 20px;">
    <div class="white-box" style="flex: 1; min-width: 350px;">
        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px;">🏷️ Catégories de
            Matériel</h3>
        <form method="POST" action="parametres.php"
            style="display: flex; gap: 10px; margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
            <input type="hidden" name="action" value="add_cat">
            <input type="text" name="nom" placeholder="Nouvelle catégorie" required class="input-field"
                style="margin: 0; flex: 2;">
            <input type="color" name="couleur_fond" value="#2c3e50" title="Fond"
                style="height: 40px; width: 40px; border: none; cursor: pointer;">
            <input type="color" name="couleur_texte" value="#ffffff" title="Texte"
                style="height: 40px; width: 40px; border: none; cursor: pointer;">
            <button type="submit"
                style="background: #2e7d32; color: white; border: none; border-radius: 4px; padding: 0 15px; cursor: pointer;">+</button>
        </form>
        <table class="table-manager" style="width: 100%;">
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <form method="POST" action="parametres.php">
                            <input type="hidden" name="action" value="edit_cat">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <td style="width: 40%;"><input type="text" name="nom"
                                    value="<?php echo htmlspecialchars($cat['nom']); ?>" required class="input-field"
                                    style="margin: 0; padding: 5px;"></td>
                            <td style="width: 15%; text-align: center;"><input type="color" name="couleur_fond"
                                    value="<?php echo htmlspecialchars($cat['couleur_fond'] ?? '#2c3e50'); ?>"></td>
                            <td style="width: 15%; text-align: center;"><input type="color" name="couleur_texte"
                                    value="<?php echo htmlspecialchars($cat['couleur_texte'] ?? '#ffffff'); ?>"></td>
                            <td style="width: 30%; text-align: right;">
                                <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                    <button type="submit"
                                        style="background: #4caf50; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">💾</button>
                        </form>
                        <form method="POST" action="parametres.php" onsubmit="return confirm('Supprimer ?');">
                            <input type="hidden" name="action" value="delete_cat">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit"
                                style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">🗑️</button>
                        </form>
        </div>
        </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
</div>

<div style="flex: 1; min-width: 350px; display: flex; flex-direction: column; gap: 20px;">
    <div class="white-box" style="margin: 0;">
        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px;">📋 Types de
            Stockage</h3>

        <form method="POST" action="parametres.php"
            style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="action" value="add_type">
            <input type="text" name="nom" placeholder="Ex: Boîte Pharmacie" required class="input-field"
                style="margin: 0; flex: 1; min-width: 150px;">
            <button type="submit"
                style="background: #2e7d32; color: white; border: none; border-radius: 4px; padding: 10px 15px; cursor: pointer; font-weight: bold;">Ajouter</button>
        </form>

        <table class="table-manager" style="width: 100%;">
            <tbody>
                <?php foreach ($types_lieux as $type): ?>
                    <tr>
                        <td style="font-weight: 500; color: #555;">
                            <?php echo htmlspecialchars($type['nom']); ?>
                        </td>
                        <td style="text-align: right;">
                            <form method="POST" action="parametres.php" style="margin: 0;"
                                onsubmit="return confirm('Supprimer ce type ?');">
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                <button type="submit"
                                    style="background: transparent; color: #c62828; border: none; cursor: pointer; font-size: 16px;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="white-box" style="margin: 0;">
        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px;">🖼️ Icônes
            disponibles</h3>
        <form method="POST" action="parametres.php" style="display: flex; gap: 10px; margin-bottom: 15px;">
            <input type="hidden" name="action" value="add_icone">
            <input type="text" name="icone" placeholder="Emoji (ex: 🩸)" required class="input-field"
                style="margin: 0; flex: 1;">
            <button type="submit"
                style="background: #2e7d32; color: white; border: none; border-radius: 4px; padding: 0 15px; cursor: pointer; font-weight: bold;">Ajouter</button>
        </form>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($icones as $ic): ?>
                <form method="POST" action="parametres.php" style="margin: 0;" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="action" value="delete_icone">
                    <input type="hidden" name="id" value="<?php echo $ic['id']; ?>">
                    <button type="submit" title="Cliquez pour supprimer"
                        style="background: #f4f7f6; border: 1px solid #ccc; border-radius: 8px; font-size: 24px; padding: 10px; cursor: pointer;"><?php echo htmlspecialchars($ic['icone']); ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
<?php require_once 'includes/footer.php'; ?>