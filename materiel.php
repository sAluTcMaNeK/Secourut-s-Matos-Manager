<?php
// materiel.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';

try {
    $pdo->exec("ALTER TABLE materiels ADD COLUMN fournisseur TEXT DEFAULT ''");
} catch (PDOException $e) {}

// ==========================================
// 1. TRAITEMENT DES ACTIONS (POST)
// ==========================================

// A. SUPPRESSION D'UN MATÉRIEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_materiel') {
    $id_a_supprimer = (int)$_POST['materiel_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM materiels WHERE id = :id");
        $stmt->execute(['id' => $id_a_supprimer]);
        $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✅ Référence supprimée du catalogue.</div>';
    } catch (PDOException $e) {
        $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #c62828;">❌ Erreur lors de la suppression.</div>';
    }
}

// B. AJOUT D'UN MATÉRIEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_materiel') {
    $nom = trim($_POST['nom']);
    $categorie_id = $_POST['categorie_id'];
    $seuil_alerte = (int) $_POST['seuil_alerte'];
    $fournisseur = trim($_POST['fournisseur'] ?? '');

    if (!empty($nom) && !empty($categorie_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO materiels (nom, categorie_id, seuil_alerte, fournisseur) VALUES (:nom, :cat_id, :seuil, :fournisseur)");
            $stmt->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur]);
            $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✅ Matériel ajouté au catalogue !</div>';
        } catch (PDOException $e) {
            $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px;">❌ Erreur : ' . $e->getMessage() . '</div>';
        }
    }
}

// C. MODIFICATION D'UN MATÉRIEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_materiel') {
    $id = (int) $_POST['materiel_id'];
    $nom = trim($_POST['nom']);
    $categorie_id = $_POST['categorie_id'];
    $seuil_alerte = (int) $_POST['seuil_alerte'];
    $fournisseur = trim($_POST['fournisseur'] ?? '');

    if ($id > 0 && !empty($nom) && !empty($categorie_id)) {
        try {
            $stmt = $pdo->prepare("UPDATE materiels SET nom = :nom, categorie_id = :cat_id, seuil_alerte = :seuil, fournisseur = :fournisseur WHERE id = :id");
            $stmt->execute(['nom' => $nom, 'cat_id' => $categorie_id, 'seuil' => $seuil_alerte, 'fournisseur' => $fournisseur, 'id' => $id]);
            $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">✏️ Modifications enregistrées avec succès !</div>';
        } catch (PDOException $e) {
            $message = '<div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px;">❌ Erreur : ' . $e->getMessage() . '</div>';
        }
    }
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

    <div style="flex: 1; min-width: 300px;">
        
        <?php echo $message; ?>

        <div id="bloc-ajout" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">➕ Ajouter une référence</h2>
            <form action="materiel.php" method="POST">
                <input type="hidden" name="action" value="add_materiel">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; font-size: 14px;">Nom</label>
                    <input type="text" name="nom" required placeholder="Ex: Compresses stériles 10x10" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; font-size: 14px;">Catégorie</label>
                    <select name="categorie_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; font-size: 14px;">Fournisseur</label>
                    <input type="text" name="fournisseur" placeholder="Ex: SMSP, Ylea, Medisafe..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; font-size: 14px;">Seuil d'alerte (Stock min.)</label>
                    <input type="number" name="seuil_alerte" value="0" min="0" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                
                <button type="submit" style="width: 100%; padding: 12px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Ajouter au catalogue</button>
            </form>
        </div>

        <div id="bloc-edition" style="display: none; background: #fff3e0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #ef6c00;">
            <h2 style="margin-top: 0; color: #e65100; border-bottom: 2px solid #ffe0b2; padding-bottom: 10px;">✏️ Modifier la référence</h2>
            <form action="materiel.php" method="POST">
                <input type="hidden" name="action" value="edit_materiel">
                <input type="hidden" name="materiel_id" id="edit_id" value="">
                
                <div style="margin-bottom: 15px;"><label style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Nom</label><input type="text" name="nom" id="edit_nom" required style="width: 100%; padding: 10px; border: 1px solid #ffcc80; border-radius: 4px; box-sizing: border-box;"></div>
                <div style="margin-bottom: 15px;"><label style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Catégorie</label><select name="categorie_id" id="edit_categorie_id" required style="width: 100%; padding: 10px; border: 1px solid #ffcc80; border-radius: 4px; box-sizing: border-box;"><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option><?php endforeach; ?></select></div>
                <div style="margin-bottom: 15px;"><label style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Fournisseur</label><input type="text" name="fournisseur" id="edit_fournisseur" style="width: 100%; padding: 10px; border: 1px solid #ffcc80; border-radius: 4px; box-sizing: border-box;"></div>
                <div style="margin-bottom: 20px;"><label style="display: block; font-weight: bold; font-size: 14px; color: #e65100;">Seuil d'alerte</label><input type="number" name="seuil_alerte" id="edit_seuil_alerte" min="0" style="width: 100%; padding: 10px; border: 1px solid #ffcc80; border-radius: 4px; box-sizing: border-box;"></div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="flex: 2; padding: 12px; background-color: #ef6c00; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Enregistrer</button>
                    <button type="button" onclick="fermerEdition()" style="flex: 1; padding: 12px; background-color: #ccc; color: #333; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Annuler</button>
                </div>
            </form>
        </div>

    </div>

    <div style="flex: 2; min-width: 400px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📦 Catalogue Secourut's</h2>
        
        <?php if (empty($materiels_par_categorie)): ?>
            <p style="color: #666; font-style: italic; text-align: center; padding: 20px;">Le catalogue est actuellement vide.</p>
        <?php else: ?>
            
            <?php foreach ($materiels_par_categorie as $categorie => $articles): ?>
                <div style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    
                    <?php 
                        // On utilise la fonction de header.php s'il y a des couleurs, sinon fallback sur le style bleu de l'image
                        $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; 
                    ?>
                    <h3 style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>; padding: 12px 15px; border-radius: 4px 4px 0 0; margin: 0; font-size: 16px;">
                        <?php echo htmlspecialchars($categorie); ?>
                    </h3>
                    
                    <table style="width: 100%; border-collapse: collapse; background: white;">
                        <thead>
                            <tr style="background-color: #f8f9fa; text-transform: uppercase; font-size: 11px; color: #666; letter-spacing: 0.5px;">
                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; width: 40%;">NOM</th>
                                <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; width: 35%;">FOURNISSEUR</th>
                                <th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; width: 10%;">SEUIL</th>
                                <th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; width: 15%;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $mat): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px 15px; font-weight: 500; color: #444;">
                                        <?php echo htmlspecialchars($mat['nom']); ?>
                                    </td>
                                    <td style="padding: 12px 15px; color: #666; font-size: 14px;">
                                        <?php echo !empty($mat['fournisseur']) ? htmlspecialchars($mat['fournisseur']) : ''; ?>
                                    </td>
                                    <td style="padding: 12px 15px; text-align: center;">
                                        <span style="color: #e65100; font-weight: bold;">
                                            <?php echo htmlspecialchars($mat['seuil_alerte']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 15px; text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 10px;">
                                            
                                            <button type="button" onclick="ouvrirEdition(<?php echo $mat['id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['nom'])); ?>', <?php echo $mat['categorie_id']; ?>, '<?php echo htmlspecialchars(addslashes($mat['fournisseur'])); ?>', <?php echo $mat['seuil_alerte']; ?>)" style="background: transparent; border: none; cursor: pointer; font-size: 16px;" title="Modifier">✏️</button>
                                            
                                            <form method="POST" action="materiel.php" style="margin: 0;" onsubmit="return confirm('ATTENTION ! Supprimer cette référence la supprimera de TOUS vos lieux de stockage. Sûr ?');">
                                                <input type="hidden" name="action" value="delete_materiel">
                                                <input type="hidden" name="materiel_id" value="<?php echo $mat['id']; ?>">
                                                <button type="submit" style="background: transparent; border: none; cursor: pointer; font-size: 16px; color: #999;" title="Supprimer">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
        <?php endif; ?>
    </div>

</div>

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

<?php require_once 'includes/footer.php'; ?>