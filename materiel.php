<?php
// materiel.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// Création de la colonne fournisseur (si elle n'existe pas)
try {
    $pdo->exec("ALTER TABLE materiels ADD COLUMN fournisseur TEXT DEFAULT ''");
} catch (PDOException $e) {
}

// Création de la colonne check_fonctionnel (si elle n'existe pas)
try {
    $pdo->exec("ALTER TABLE materiels ADD COLUMN check_fonctionnel TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {
}

// ==========================================
// 1. TRAITEMENT DES ACTIONS (PATTERN PRG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_editer) {
        $_SESSION['flash_error'] = "🛑 Vous n'avez pas les droits de modification.";
        header("Location: materiel.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_materiel') {
        $id_a_supprimer = (int) $_POST['materiel_id'];
        try {
            $nom_supprime = $pdo->query("SELECT nom FROM materiels WHERE id = $id_a_supprimer")->fetchColumn();
            $pdo->prepare("DELETE FROM materiels WHERE id = :id")->execute(['id' => $id_a_supprimer]);

            $action_texte = "A supprimé la référence : " . $nom_supprime;
            $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")->execute([$_SESSION['username'], $action_texte]);

            $_SESSION['flash_success'] = "✅ Référence supprimée du catalogue.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "❌ Erreur lors de la suppression.";
        }
        header("Location: materiel.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_materiel') {
        $nom = trim($_POST['nom']);
        $categorie_id = $_POST['categorie_id'];
        $seuil_alerte = (int) $_POST['seuil_alerte'];
        $fournisseur = trim($_POST['fournisseur'] ?? '');
        $check_fonctionnel = isset($_POST['check_fonctionnel']) ? 1 : 0;

        if (!empty($nom) && !empty($categorie_id)) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM materiels WHERE LOWER(nom) = LOWER(:nom)");
            $stmt_check->execute(['nom' => $nom]);

            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "❌ Impossible : La référence '$nom' existe déjà.";
            } else {
                try {
                    $pdo->prepare("INSERT INTO materiels (nom, categorie_id, seuil_alerte, fournisseur, check_fonctionnel) VALUES (:nom, :cat_id, :seuil, :fournisseur, :check)")->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur, 'check' => $check_fonctionnel]);

                    $action_texte = "A ajouté une nouvelle référence : " . $nom;
                    $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")->execute([$_SESSION['username'], $action_texte]);

                    $_SESSION['flash_success'] = "✅ Matériel ajouté au catalogue !";
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = "❌ Erreur : " . $e->getMessage();
                }
            }
        }
        header("Location: materiel.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'edit_materiel') {
        $id = (int) $_POST['materiel_id'];
        $nom = trim($_POST['nom']);
        $categorie_id = $_POST['categorie_id'];
        $seuil_alerte = (int) $_POST['seuil_alerte'];
        $fournisseur = trim($_POST['fournisseur'] ?? '');
        $check_fonctionnel = isset($_POST['check_fonctionnel']) ? 1 : 0;

        if ($id > 0 && !empty($nom) && !empty($categorie_id)) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM materiels WHERE LOWER(nom) = LOWER(:nom) AND id != :id");
            $stmt_check->execute(['nom' => $nom, 'id' => $id]);

            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "❌ Impossible : Une autre référence porte déjà ce nom.";
            } else {
                try {
                    $pdo->prepare("UPDATE materiels SET nom = :nom, categorie_id = :cat_id, seuil_alerte = :seuil, fournisseur = :fournisseur, check_fonctionnel = :check WHERE id = :id")->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur, 'check' => $check_fonctionnel, 'id' => $id]);

                    $action_texte = "A modifié la référence : " . $nom;
                    $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")->execute([$_SESSION['username'], $action_texte]);

                    $_SESSION['flash_success'] = "✏️ Modifications enregistrées avec succès !";
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = "❌ Erreur : " . $e->getMessage();
                }
            }
        }
        header("Location: materiel.php");
        exit;
    }
}

$stmt_cat = $pdo->query("SELECT * FROM categories ORDER BY nom");
$categories = $stmt_cat->fetchAll();

$stmt_mat = $pdo->query("SELECT m.id, m.nom, m.seuil_alerte, m.fournisseur, m.check_fonctionnel, m.categorie_id, c.nom AS categorie_nom FROM materiels m JOIN categories c ON m.categorie_id = c.id ORDER BY c.nom, m.nom");
$materiels_bruts = $stmt_mat->fetchAll();
$materiels_par_categorie = [];
foreach ($materiels_bruts as $mat) {
    $materiels_par_categorie[$mat['categorie_nom']][] = $mat;
}

require_once 'includes/header.php';
?>

<div class="catalogue-container">

    <?php if ($peut_editer): ?>
        <div class="catalogue-sidebar">

            <div id="bloc-ajout" class="white-box">
                <h2 class="title-red">➕ Ajouter une référence</h2>
                <form action="materiel.php" method="POST">
                    <input type="hidden" name="action" value="add_materiel">

                    <div class="mb-15">
                        <label class="form-label">Nom</label>
                        <input type="text" name="nom" required placeholder="Ex: Tensiomètre" class="input-field">
                    </div>

                    <div class="mb-15">
                        <label class="form-label">Catégorie</label>
                        <select name="categorie_id" required class="input-field">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-15">
                        <label class="form-label">Fournisseur</label>
                        <input type="text" name="fournisseur" placeholder="Ex: SMSP, Ylea..." class="input-field">
                    </div>

                    <div class="mb-15">
                        <label class="form-label">Seuil d'alerte (Stock min.)</label>
                        <input type="number" name="seuil_alerte" value="0" min="0" class="input-field">
                    </div>

                    <div class="mb-20 flex-row align-center p-10 border-radius-4"
                        style="background-color: #f5f5f5; border: 1px solid #ddd;">
                        <input type="checkbox" name="check_fonctionnel" value="1" id="check_func_add"
                            style="transform: scale(1.4); margin-right: 12px; cursor: pointer;">
                        <label for="check_func_add" class="form-label mb-0" style="cursor: pointer; font-size: 13px;">Check Fonctionnel)</label>
                    </div>

                    <button type="submit" class="btn btn-danger-dark btn-full">Ajouter au catalogue</button>
                </form>
            </div>

            <div id="bloc-edition" class="form-box-orange">
                <h2 class="title-orange">✏️ Modifier la référence</h2>
                <form action="materiel.php" method="POST">
                    <input type="hidden" name="action" value="edit_materiel">
                    <input type="hidden" name="materiel_id" id="edit_id" value="">

                    <div class="mb-15">
                        <label class="form-label-edit">Nom</label>
                        <input type="text" name="nom" id="edit_nom" required class="input-field input-edit-focus">
                    </div>
                    <div class="mb-15">
                        <label class="form-label-edit">Catégorie</label>
                        <select name="categorie_id" id="edit_categorie_id" required class="input-field input-edit-focus">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-15">
                        <label class="form-label-edit">Fournisseur</label>
                        <input type="text" name="fournisseur" id="edit_fournisseur" class="input-field input-edit-focus">
                    </div>
                    <div class="mb-15">
                        <label class="form-label-edit">Seuil d'alerte</label>
                        <input type="number" name="seuil_alerte" id="edit_seuil_alerte" min="0"
                            class="input-field input-edit-focus">
                    </div>

                    <div class="mb-20 flex-row align-center p-10 border-radius-4"
                        style="background-color: #ffe0b2; border: 1px solid #ffcc80;">
                        <input type="checkbox" name="check_fonctionnel" value="1" id="edit_check_fonctionnel"
                            style="transform: scale(1.4); margin-right: 12px; cursor: pointer;">
                        <label for="edit_check_fonctionnel" class="form-label-edit mb-0"
                            style="cursor: pointer; font-size: 13px; color: #e65100;">Remplacer le comptage par un "Check
                            Fonctionnel"</label>
                    </div>

                    <div class="flex-row-sm">
                        <button type="submit" class="btn btn-warning btn-flex-2">Enregistrer</button>
                        <button type="button" onclick="fermerEdition()"
                            class="btn btn-secondary btn-flex-1">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="white-box catalogue-main">

        <div class="search-header">
            <h2 class="mb-0 text-dark mt-0">📦 Catalogue Secourut's</h2>
            <div class="search-container">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchCatalogue" class="search-input" placeholder="Chercher un matériel..."
                    onkeyup="filtrerCatalogue()">
            </div>
        </div>

        <?php if (empty($materiels_par_categorie)): ?>
            <p class="empty-message">Le catalogue est vide.</p>
        <?php else: ?>
            <?php foreach ($materiels_par_categorie as $categorie => $articles): ?>

                <div class="catalogue-block category-card">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>

                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?>
                    </h3>

                    <div class="table-responsive">
                        <table class="table-manager">
                            <thead>
                                <tr>
                                    <th style="width: <?php echo $peut_editer ? '40%' : '50%'; ?>;">NOM</th>
                                    <th style="width: <?php echo $peut_editer ? '35%' : '40%'; ?>;">FOURNISSEUR</th>
                                    <th class="text-center" style="width: 10%;">SEUIL</th>
                                    <?php if ($peut_editer): ?>
                                        <th class="text-center" style="width: 15%;">ACTIONS</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $mat): ?>
                                    <tr class="catalogue-row" data-nom="<?php echo htmlspecialchars(strtolower($mat['nom'])); ?>">
                                        <td class="item-name">
                                            <?php echo htmlspecialchars($mat['nom']); ?>
                                            <?php if ($mat['check_fonctionnel'] == 1): ?>
                                                <div class="text-muted mt-5" style="font-size: 11px;">⚙️ Vérif. Fonctionnelle</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="item-supplier">
                                            <?php echo !empty($mat['fournisseur']) ? htmlspecialchars($mat['fournisseur']) : ''; ?>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="text-warning font-bold"><?php echo htmlspecialchars($mat['seuil_alerte']); ?></span>
                                        </td>
                                        <?php if ($peut_editer): ?>
                                            <td class="text-center">
                                                <div class="flex-center" style="justify-content: center;">
                                                    <button type="button" class="btn-icon"
                                                        onclick="ouvrirEdition(<?php echo $mat['id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['nom'])); ?>', <?php echo $mat['categorie_id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['fournisseur'])); ?>', <?php echo $mat['seuil_alerte']; ?>, <?php echo $mat['check_fonctionnel']; ?>)"
                                                        title="Modifier">✏️</button>
                                                    <form method="POST" action="materiel.php" class="mb-0"
                                                        onsubmit="return confirm('ATTENTION ! Supprimer ?');">
                                                        <input type="hidden" name="action" value="delete_materiel">
                                                        <input type="hidden" name="materiel_id" value="<?php echo $mat['id']; ?>">
                                                        <button type="submit" class="btn-icon text-muted" title="Supprimer">🗑️</button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>