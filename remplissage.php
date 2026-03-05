<?php
// remplissage.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;

if ($lieu_id === 0) {
    require_once 'includes/header.php';
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY type, nom")->fetchAll();
    ?>
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h2 style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🔄 Mode Remplissage</h2>
        <p style="color: #666; margin-bottom: 20px;">Sélectionne le stockage que tu souhaites inventorier :</p>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php foreach ($lieux as $lieu): ?>
                <?php
                $type_affichage = ($lieu['type'] === 'reserve') ? 'Réserve' : (($lieu['type'] === 'sac_inter') ? 'Sac Intervention' : 'Sac Logistique');
                $icone = !empty($lieu['icone']) ? $lieu['icone'] : ($lieu['type'] === 'reserve' ? '🏢' : '🎒');
                ?>
                <a href="remplissage.php?lieu_id=<?php echo $lieu['id']; ?>" class="carte-animee" style="display: block; width: 200px; padding: 20px; background-color: white; border: 1px solid transparent; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-decoration: none; color: #333; text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;"><?php echo htmlspecialchars($icone); ?></div>
                    <strong style="font-size: 16px; display: block;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                    <span style="font-size: 12px; color: #999; text-transform: uppercase;"><?php echo $type_affichage; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// ==========================================
// TRAITEMENT DES FORMULAIRES (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. Édition d'une ligne (Quantité et Date)
        if ($action === 'edit_stock') {
            $stock_id = (int) $_POST['stock_id'];
            $qty = (int) $_POST['quantite'];
            $date_p = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;

            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);
            } else {
                $pdo->prepare("UPDATE stocks SET quantite = :qty, date_peremption = :dp WHERE id = :id")->execute(['qty' => $qty, 'dp' => $date_p, 'id' => $stock_id]);
            }
        }
        // 2. Suppression totale
        elseif ($action === 'delete_stock') {
            $stock_id = (int) $_POST['stock_id'];
            $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);
        }
        // 3. Ajout d'un nouveau matériel
        elseif ($action === 'add_stock') {
            $materiel_id = (int) $_POST['materiel_id'];
            $quantite = (int) $_POST['quantite'];
            $date_peremption = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;

            if ($materiel_id && $quantite > 0) {
                $stmt_check = $pdo->prepare("SELECT id, quantite FROM stocks WHERE materiel_id = :mat AND lieu_id = :lieu AND IFNULL(date_peremption, '') = IFNULL(:peremp, '')");
                $stmt_check->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'peremp' => $date_peremption]);
                $stock_existant = $stmt_check->fetch();

                if ($stock_existant) {
                    $nouvelle_quantite = $stock_existant['quantite'] + $quantite;
                    $pdo->prepare("UPDATE stocks SET quantite = :qty WHERE id = :id")->execute(['qty' => $nouvelle_quantite, 'id' => $stock_existant['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption) VALUES (:mat, :lieu, :qty, :peremp)")->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'qty' => $quantite, 'peremp' => $date_peremption]);
                }
            }
        }
        header("Location: remplissage.php?lieu_id=" . $lieu_id);
        exit;
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
}

// RÉCUPÉRATION DES DONNÉES
$stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
$stmt_lieu->execute(['id' => $lieu_id]);
$lieu = $stmt_lieu->fetch();

if (!$lieu) { header('Location: lieux.php'); exit; }

$materiels = $pdo->query("SELECT id, nom FROM materiels ORDER BY nom")->fetchAll();
$stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom, s.date_peremption");
$stmt_stocks->execute(['lieu_id' => $lieu_id]);
$stocks = $stmt_stocks->fetchAll();

$stocks_par_categorie = [];
foreach ($stocks as $stock) { $stocks_par_categorie[$stock['categorie_nom']][] = $stock; }
$icone_affichage = !empty($lieu['icone']) ? $lieu['icone'] : '📦';

require_once 'includes/header.php';
?>

<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">

    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">
        <div>
            <a href="remplissage.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Changer de stockage</a>
            <h2 style="margin: 10px 0 0 0; color: #d32f2f;">
                <?php echo $icone_affichage; ?> Remplissage : <?php echo htmlspecialchars($lieu['nom']); ?>
            </h2>
        </div>
    </div>

    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2c3e50;">
        <h3 style="margin-top: 0; font-size: 16px; color: #2c3e50;">➕ Ajouter un nouveau matériel</h3>
        <form action="remplissage.php?lieu_id=<?php echo $lieu_id; ?>" method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add_stock">
            <div style="flex: 2; min-width: 200px;"><label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Catalogue</label><select name="materiel_id" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">-- Sélectionner --</option><?php foreach ($materiels as $mat): ?><option value="<?php echo $mat['id']; ?>"><?php echo htmlspecialchars($mat['nom']); ?></option><?php endforeach; ?></select></div>
            <div style="flex: 1; min-width: 100px;"><label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Quantité</label><input type="number" name="quantite" required min="1" value="1" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;"></div>
            <div style="flex: 1; min-width: 150px;"><label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Péremption</label><input type="date" name="date_peremption" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;"></div>
            <div><button type="submit" style="padding: 9px 20px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Insérer</button></div>
        </form>
    </div>

    <div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; border: 1px solid #e0e0e0;">
        <strong style="color: #333;">🔍 Filtrer l'inventaire :</strong>
        <input type="text" id="searchBar" onkeyup="filtrerInventaire()" placeholder="Rechercher un matériel..." style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; flex: 1; min-width: 150px;">
        <select id="catFilter" onchange="filtrerInventaire()" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 150px;">
            <option value="">Toutes les catégories</option>
            <?php foreach (array_keys($stocks_par_categorie) as $cat_nom): ?>
                <option value="<?php echo htmlspecialchars($cat_nom); ?>"><?php echo htmlspecialchars($cat_nom); ?></option>
            <?php endforeach; ?>
        </select>
        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #d32f2f; font-weight: bold;">
            <input type="checkbox" id="expFilter" onchange="filtrerInventaire()"> ⚠️ Périme bientôt
        </label>
    </div>

    <h3 style="color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📦 Inventaire actuel</h3>

    <?php if (empty($stocks_par_categorie)): ?>
        <p style="text-align: center; color: #999; font-style: italic;">Le stockage est vide.</p>
    <?php else: ?>
        <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
            
            <div class="category-block" data-cat="<?php echo htmlspecialchars($categorie); ?>" style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                <h4 style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>; padding: 12px 15px; border-radius: 4px 4px 0 0; margin: 0; font-size: 16px;">
                    <?php echo htmlspecialchars($categorie); ?>
                </h4>
                
                <table style="width: 100%; border-collapse: collapse; background: white;">
                    <thead>
                        <tr style="background-color: #f8f9fa; text-transform: uppercase; font-size: 11px; color: #666; letter-spacing: 0.5px;">
                            <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; width: 40%;">NOM DU MATÉRIEL</th>
                            <th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; width: 25%;">PÉREMPTION</th>
                            <th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; width: 15%;">QUANTITÉ</th>
                            <th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; width: 20%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article): 
                            $sid = $article['stock_id']; 
                            $raw_date = $article['date_peremption'];
                            $affichage_date = $raw_date ? date('d/m/Y', strtotime($raw_date)) : '-';
                        ?>
                            <form id="form-edit-<?php echo $sid; ?>" method="POST" action="remplissage.php?lieu_id=<?php echo $lieu_id; ?>">
                                <input type="hidden" name="action" value="edit_stock">
                                <input type="hidden" name="stock_id" value="<?php echo $sid; ?>">
                            </form>

                            <tr class="item-row" data-nom="<?php echo htmlspecialchars(strtolower($article['materiel_nom'])); ?>" data-peremp="<?php echo $raw_date; ?>" style="border-bottom: 1px solid #eee; transition: background 0.2s;">
                                <td style="padding: 12px 15px; font-weight: 500; color: #444;">
                                    <?php echo htmlspecialchars($article['materiel_nom']); ?>
                                </td>
                                
                                <td style="padding: 12px 15px; text-align: center; color: #666;">
                                    <span class="view-mode-<?php echo $sid; ?>"><?php echo $affichage_date; ?></span>
                                    <input class="edit-mode-<?php echo $sid; ?>" type="date" form="form-edit-<?php echo $sid; ?>" name="date_peremption" value="<?php echo $raw_date; ?>" style="display:none; width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
                                </td>
                                
                                <td style="padding: 12px 15px; text-align: center; font-size: 16px;">
                                    <span class="view-mode-<?php echo $sid; ?>" style="font-weight: bold;"><?php echo $article['quantite']; ?></span>
                                    <input class="edit-mode-<?php echo $sid; ?>" type="number" form="form-edit-<?php echo $sid; ?>" name="quantite" value="<?php echo $article['quantite']; ?>" min="0" style="display:none; width: 60px; padding: 5px; text-align: center; border: 1px solid #ccc; border-radius: 4px; margin: 0 auto;">
                                </td>
                                
                                <td style="padding: 12px 15px; text-align: center;">
                                    <div class="view-mode-<?php echo $sid; ?>" style="display: flex; justify-content: center; gap: 10px;">
                                        <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, true)" style="background: transparent; border: none; cursor: pointer; font-size: 16px;" title="Modifier (Quantité/Date)">✏️</button>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Retirer définitivement cet objet du sac ?');">
                                            <input type="hidden" name="action" value="delete_stock">
                                            <input type="hidden" name="stock_id" value="<?php echo $sid; ?>">
                                            <button type="submit" style="background: transparent; border: none; cursor: pointer; font-size: 16px; color: #999;" title="Supprimer">🗑️</button>
                                        </form>
                                    </div>
                                    <div class="edit-mode-<?php echo $sid; ?>" style="display: none; justify-content: center; gap: 10px;">
                                        <button type="submit" form="form-edit-<?php echo $sid; ?>" style="background: #4caf50; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;" title="Enregistrer">💾</button>
                                        <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, false)" style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;" title="Annuler">❌</button>
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

<script>
// Fonction pour basculer entre le mode lecture et le mode édition pour une ligne
function toggleEdit(id, showEdit) {
    const viewElements = document.querySelectorAll('.view-mode-' + id);
    const editElements = document.querySelectorAll('.edit-mode-' + id);
    
    viewElements.forEach(el => el.style.display = showEdit ? 'none' : '');
    editElements.forEach(el => el.style.display = showEdit ? 'inline-block' : 'none');
}

// Fonction de filtrage en temps réel
function filtrerInventaire() {
    const search = document.getElementById('searchBar').value.toLowerCase();
    const catFilter = document.getElementById('catFilter').value;
    const expFilter = document.getElementById('expFilter').checked;
    
    // Calcul de la date limite (aujourd'hui + 30 jours)
    const limitDate = new Date();
    limitDate.setDate(limitDate.getDate() + 30);

    document.querySelectorAll('.category-block').forEach(block => {
        const blockCat = block.getAttribute('data-cat');
        let hasVisibleRow = false;

        block.querySelectorAll('.item-row').forEach(row => {
            const nom = row.getAttribute('data-nom');
            const peremp = row.getAttribute('data-peremp');
            let show = true;
            
            // Filtre Texte
            if (search && !nom.includes(search)) show = false;
            // Filtre Catégorie
            if (catFilter && blockCat !== catFilter) show = false;
            // Filtre Péremption
            if (expFilter) {
                if (!peremp) {
                    show = false; // Pas de date = pas périmé
                } else {
                    const pDate = new Date(peremp);
                    if (pDate > limitDate) show = false; // Expire dans longtemps
                }
            }

            row.style.display = show ? '' : 'none';
            if (show) hasVisibleRow = true;
        });

        // Masquer tout le bloc si aucun élément ne correspond
        block.style.display = hasVisibleRow ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>