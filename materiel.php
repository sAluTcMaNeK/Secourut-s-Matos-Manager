<?php
// materiel.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';
$peut_editer = ($_SESSION['can_edit'] === 1);

try {
    $pdo->exec("ALTER TABLE materiels ADD COLUMN fournisseur TEXT DEFAULT ''");
} catch (PDOException $e) {
}

// ==========================================
// 1. TRAITEMENT DES ACTIONS (SÉCURISÉ)
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peut_editer) {

    // A. SUPPRESSION D'UN MATÉRIEL
    if (isset($_POST['action']) && $_POST['action'] === 'delete_materiel') {
        $id_a_supprimer = (int) $_POST['materiel_id'];
        try {
            // On récupère le nom avant de supprimer pour l'historique
            $nom_supprime = $pdo->query("SELECT nom FROM materiels WHERE id = $id_a_supprimer")->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM materiels WHERE id = :id");
            $stmt->execute(['id' => $id_a_supprimer]);

            // --- HISTORIQUE ---
            $action_texte = "A supprimé la référence : " . $nom_supprime;
            $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);

            $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✅ Référence supprimée du catalogue.</div>';
        } catch (PDOException $e) {
            $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #c62828;">❌ Erreur lors de la suppression.</div>';
        }
    }

    // B. AJOUT D'UN MATÉRIEL
    if (isset($_POST['action']) && $_POST['action'] === 'add_materiel') {
        $nom = trim($_POST['nom']);
        $categorie_id = $_POST['categorie_id'];
        $seuil_alerte = (int) $_POST['seuil_alerte'];
        $fournisseur = trim($_POST['fournisseur'] ?? '');

        if (!empty($nom) && !empty($categorie_id)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO materiels (nom, categorie_id, seuil_alerte, fournisseur) VALUES (:nom, :cat_id, :seuil, :fournisseur)");
                $stmt->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur]);

                // --- HISTORIQUE ---
                $action_texte = "A ajouté une nouvelle référence : " . $nom;
                $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);

                $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✅ Matériel ajouté au catalogue !</div>';
            } catch (PDOException $e) {
                $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px;">❌ Erreur : ' . $e->getMessage() . '</div>';
            }
        }
    }

    // C. MODIFICATION D'UN MATÉRIEL
    if (isset($_POST['action']) && $_POST['action'] === 'edit_materiel') {
        $id = (int) $_POST['materiel_id'];
        $nom = trim($_POST['nom']);
        $categorie_id = $_POST['categorie_id'];
        $seuil_alerte = (int) $_POST['seuil_alerte'];
        $fournisseur = trim($_POST['fournisseur'] ?? '');

        if ($id > 0 && !empty($nom) && !empty($categorie_id)) {
            try {
                $stmt = $pdo->prepare("UPDATE materiels SET nom = :nom, categorie_id = :cat_id, seuil_alerte = :seuil, fournisseur = :fournisseur WHERE id = :id");
                $stmt->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur, 'id' => $id]);

                // --- HISTORIQUE ---
                $action_texte = "A modifié la référence : " . $nom;
                $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);

                $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✏️ Modifications enregistrées avec succès !</div>';
            } catch (PDOException $e) {
                $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px;">❌ Erreur : ' . $e->getMessage() . '</div>';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$peut_editer) {
    $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px;">🛑 Vous n\'avez pas les droits de modification.</div>';
}

// ==========================================
// 2. RÉCUPÉRATION DES DONNÉES
// ==========================================
$stmt_cat = $pdo->query("SELECT * FROM categories ORDER BY nom");
$categories = $stmt_cat->fetchAll();

$stmt_mat = $pdo->query("
    SELECT m.id, m.nom, m.seuil_alerte, m.fournisseur, m.categorie_id, c.nom AS categorie_nom 
    FROM materiels m 
    JOIN categories c ON m.categorie_id = c.id 
    ORDER BY c.nom, m.nom
");
$materiels_bruts = $stmt_mat->fetchAll();

$materiels_par_categorie = [];
foreach ($materiels_bruts as $mat) {
    $materiels_par_categorie[$mat['categorie_nom']][] = $mat;
}

require_once 'includes/header.php';
?>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">

    <?php if ($peut_editer): ?>
        <div style="flex: 1; min-width: 300px;">
            <?php echo $message; ?>

            <div id="bloc-ajout" class="white-box">
                <h2 style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">➕ Ajouter
                    une référence</h2>
                <form action="materiel.php" method="POST">
                    <input type="hidden" name="action" value="add_materiel">

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; font-size: 14px;">Nom</label>
                        <input type="text" name="nom" required placeholder="Ex: Compresses stériles" class="input-field">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; font-size: 14px;">Catégorie</label>
                        <select name="categorie_id" required class="input-field">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; font-size: 14px;">Fournisseur</label>
                        <input type="text" name="fournisseur" placeholder="Ex: SMSP, Ylea..." class="input-field">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; font-size: 14px;">Seuil d'alerte (Stock
                            min.)</label>
                        <input type="number" name="seuil_alerte" value="0" min="0" class="input-field">
                    </div>

                    <button type="submit"
                        style="width: 100%; padding: 12px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Ajouter
                        au catalogue</button>
                </form>
            </div>

            <div id="bloc-edition"
                style="display: none; background: #fff3e0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #ef6c00;">
                <h2 style="margin-top: 0; color: #e65100; border-bottom: 2px solid #ffe0b2; padding-bottom: 10px;">✏️
                    Modifier la référence</h2>
                <form action="materiel.php" method="POST">
                    <input type="hidden" name="action" value="edit_materiel">
                    <input type="hidden" name="materiel_id" id="edit_id" value="">

                    <div style="margin-bottom: 15px;"><label
                            style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Nom</label><input
                            type="text" name="nom" id="edit_nom" required class="input-field"
                            style="border-color: #ffcc80;"></div>
                    <div style="margin-bottom: 15px;"><label
                            style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Catégorie</label><select
                            name="categorie_id" id="edit_categorie_id" required class="input-field"
                            style="border-color: #ffcc80;"><?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div style="margin-bottom: 15px;"><label
                            style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Fournisseur</label><input
                            type="text" name="fournisseur" id="edit_fournisseur" class="input-field"
                            style="border-color: #ffcc80;"></div>
                    <div style="margin-bottom: 20px;"><label
                            style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Seuil
                            d'alerte</label><input type="number" name="seuil_alerte" id="edit_seuil_alerte" min="0"
                            class="input-field" style="border-color: #ffcc80;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit"
                            style="flex: 2; padding: 12px; background-color: #ef6c00; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Enregistrer</button>
                        <button type="button" onclick="fermerEdition()"
                            style="flex: 1; padding: 12px; background-color: #ccc; color: #333; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="white-box" style="flex: 2; min-width: 400px; margin-bottom: 0;">
        <?php if (!$peut_editer)
            echo $message; ?>
        <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📦 Catalogue
            Secourut's</h2>

        <?php if (empty($materiels_par_categorie)): ?>
            <p style="color: #666; font-style: italic; text-align: center; padding: 20px;">Le catalogue est vide.</p>
        <?php else: ?>
            <?php foreach ($materiels_par_categorie as $categorie => $articles): ?>
                <div style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>

                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?>
                    </h3>

                    <table class="table-manager">
                        <thead>
                            <tr>
                                <th style="width: <?php echo $peut_editer ? '40%' : '50%'; ?>;">NOM</th>
                                <th style="width: <?php echo $peut_editer ? '35%' : '40%'; ?>;">FOURNISSEUR</th>
                                <th style="text-align: center; width: 10%;">SEUIL</th>
                                <?php if ($peut_editer): ?>
                                    <th style="text-align: center; width: 15%;">ACTIONS</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $mat): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #444;"><?php echo htmlspecialchars($mat['nom']); ?></td>
                                    <td style="color: #666; font-size: 14px;">
                                        <?php echo !empty($mat['fournisseur']) ? htmlspecialchars($mat['fournisseur']) : ''; ?>
                                    </td>
                                    <td style="text-align: center;"><span
                                            style="color: #e65100; font-weight: bold;"><?php echo htmlspecialchars($mat['seuil_alerte']); ?></span>
                                    </td>
                                    <?php if ($peut_editer): ?>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 10px;">
                                                <button type="button"
                                                    onclick="ouvrirEdition(<?php echo $mat['id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['nom'])); ?>', <?php echo $mat['categorie_id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['fournisseur'])); ?>', <?php echo $mat['seuil_alerte']; ?>)"
                                                    style="background: transparent; border: none; cursor: pointer; font-size: 16px;"
                                                    title="Modifier">✏️</button>
                                                <form method="POST" action="materiel.php" style="margin: 0;"
                                                    onsubmit="return confirm('ATTENTION ! Supprimer ?');">
                                                    <input type="hidden" name="action" value="delete_materiel">
                                                    <input type="hidden" name="materiel_id" value="<?php echo $mat['id']; ?>">
                                                    <button type="submit"
                                                        style="background: transparent; border: none; cursor: pointer; font-size: 16px; color: #999;"
                                                        title="Supprimer">🗑️</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($peut_editer): ?>
    <script>
        function ouvrirEdition(id, nom, categorie_id, fournisseur, seuil) {
            document.getElementById('bloc-ajout').style.display = 'none';
            document.getElementById('bloc-edition').style.display = 'block';

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_categorie_id').value = categorie_id;
            document.getElementById('edit_fournisseur').value = fournisseur;
            document.getElementById('edit_seuil_alerte').value = seuil;

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function fermerEdition() {
            document.getElementById('bloc-edition').style.display = 'none';
            document.getElementById('bloc-ajout').style.display = 'block';
        }
    </script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>