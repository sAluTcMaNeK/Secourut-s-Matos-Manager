<?php
// remplissage.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;
$peut_editer = ($_SESSION['can_edit'] === 1);

if ($lieu_id === 0) {
    require_once 'includes/header.php';
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY type, nom")->fetchAll();
    ?>
    <div class="white-box">
        <h2 style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🔄 Mode
            Remplissage</h2>
        <p style="color: #666; margin-bottom: 20px;">Sélectionne le stockage que tu souhaites consulter ou modifier :</p>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php foreach ($lieux as $lieu):
                $type_affichage = ($lieu['type'] === 'reserve') ? 'Réserve' : (($lieu['type'] === 'sac_inter') ? 'Sac Intervention' : 'Sac Logistique');
                $icone = !empty($lieu['icone']) ? $lieu['icone'] : ($lieu['type'] === 'reserve' ? '🏢' : '🎒');
                ?>
                <a href="remplissage.php?lieu_id=<?php echo $lieu['id']; ?>" class="carte-animee"
                    style="display: block; width: 200px; padding: 20px; background-color: white; border-radius: 8px; border: 1px solid transparent; text-decoration: none; color: #333; text-align: center;">
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_editer)
        die("🛑 Action bloquée : Vous n'avez pas les droits de modification.");
    $action = $_POST['action'] ?? '';

    // --- PREPARATION DE L'HISTORIQUE : On récupère le nom du lieu ---
    $stmt_nom_lieu = $pdo->prepare("SELECT nom FROM lieux_stockage WHERE id = ?");
    $stmt_nom_lieu->execute([$lieu_id]);
    $nom_du_lieu = $stmt_nom_lieu->fetchColumn() ?: "Lieu inconnu";

    try {
        if ($action === 'edit_stock') {
            $stock_id = (int) $_POST['stock_id'];
            $qty = (int) $_POST['quantite'];
            $date_p = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;

            // Pour l'historique : on cherche le nom de l'objet qu'on est en train de modifier
            $stmt_mat_nom = $pdo->prepare("SELECT m.nom FROM materiels m JOIN stocks s ON m.id = s.materiel_id WHERE s.id = ?");
            $stmt_mat_nom->execute([$stock_id]);
            $nom_mat_edit = $stmt_mat_nom->fetchColumn() ?: "Objet";

            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);
                $action_texte = "A retiré l'objet '" . $nom_mat_edit . "' du lieu : " . $nom_du_lieu;
            } else {
                $pdo->prepare("UPDATE stocks SET quantite = :qty, date_peremption = :dp WHERE id = :id")->execute(['qty' => $qty, 'dp' => $date_p, 'id' => $stock_id]);
                $action_texte = "A mis à jour la quantité de '" . $nom_mat_edit . "' (Lieu : " . $nom_du_lieu . ")";
            }

            // --- ENREGISTREMENT HISTORIQUE ---
            $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);

        } elseif ($action === 'delete_stock') {
            $stock_id = (int) $_POST['stock_id'];

            // Pour l'historique : on cherche le nom avant de le supprimer
            $stmt_mat_nom = $pdo->prepare("SELECT m.nom FROM materiels m JOIN stocks s ON m.id = s.materiel_id WHERE s.id = ?");
            $stmt_mat_nom->execute([$stock_id]);
            $nom_mat_del = $stmt_mat_nom->fetchColumn() ?: "Objet";

            $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);

            // --- ENREGISTREMENT HISTORIQUE ---
            $action_texte = "A supprimé définitivement '" . $nom_mat_del . "' (Lieu : " . $nom_du_lieu . ")";
            $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);

        } elseif ($action === 'add_stock') {
            $materiel_id = (int) $_POST['materiel_id'];
            $quantite = (int) $_POST['quantite'];
            $date_peremption = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;
            if ($materiel_id && $quantite > 0) {
                $stmt_check = $pdo->prepare("SELECT id, quantite FROM stocks WHERE materiel_id = :mat AND lieu_id = :lieu AND IFNULL(date_peremption, '') = IFNULL(:peremp, '')");
                $stmt_check->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'peremp' => $date_peremption]);
                $stock_existant = $stmt_check->fetch();
                if ($stock_existant) {
                    $pdo->prepare("UPDATE stocks SET quantite = :qty WHERE id = :id")->execute(['qty' => $stock_existant['quantite'] + $quantite, 'id' => $stock_existant['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption) VALUES (:mat, :lieu, :qty, :peremp)")->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'qty' => $quantite, 'peremp' => $date_peremption]);
                }

                // --- ENREGISTREMENT HISTORIQUE ---
                $stmt_nom = $pdo->prepare("SELECT nom FROM materiels WHERE id = ?");
                $stmt_nom->execute([$materiel_id]);
                $nom_mat = $stmt_nom->fetchColumn() ?: "Objet inconnu";

                $action_texte = "A ajouté $quantite x $nom_mat dans : $nom_du_lieu";
                $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")->execute([$_SESSION['username'], $action_texte]);
            }
        }
        header("Location: remplissage.php?lieu_id=" . $lieu_id);
        exit;
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
}

$stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
$stmt_lieu->execute(['id' => $lieu_id]);
$lieu = $stmt_lieu->fetch();

if (!$lieu) {
    header('Location: lieux.php');
    exit;
}

$materiels = $pdo->query("SELECT id, nom FROM materiels ORDER BY nom")->fetchAll();
$stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom, s.date_peremption");
$stmt_stocks->execute(['lieu_id' => $lieu_id]);
$stocks = $stmt_stocks->fetchAll();

$stocks_par_categorie = [];
foreach ($stocks as $stock) {
    $stocks_par_categorie[$stock['categorie_nom']][] = $stock;
}
$icone_affichage = !empty($lieu['icone']) ? $lieu['icone'] : '📦';

require_once 'includes/header.php';
?>

<div class="white-box">

    <div
        style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">
        <div>
            <a href="remplissage.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Changer de
                stockage</a>
            <h2 style="margin: 10px 0 0 0; color: #d32f2f;">
                <?php echo $icone_affichage; ?> Remplissage : <?php echo htmlspecialchars($lieu['nom']); ?>
            </h2>
        </div>
    </div>

    <?php if ($peut_editer): ?>
        <div class="form-container">
            <h3 style="margin-top: 0; font-size: 16px; color: #2c3e50;">➕ Ajouter un nouveau matériel</h3>
            <form action="remplissage.php?lieu_id=<?php echo $lieu_id; ?>" method="POST" class="form-group-inline">
                <input type="hidden" name="action" value="add_stock">
                <div style="flex: 2; min-width: 200px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Catalogue</label>
                    <select name="materiel_id" required class="input-field">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($materiels as $mat): ?>
                            <option value="<?php echo $mat['id']; ?>"><?php echo htmlspecialchars($mat['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 100px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Quantité</label>
                    <input type="number" name="quantite" required min="1" value="1" class="input-field">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label
                        style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Péremption</label>
                    <input type="date" name="date_peremption" class="input-field">
                </div>
                <div>
                    <button type="submit"
                        style="padding: 11px 20px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Insérer</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="form-container"
        style="background: #fff; border: 1px solid #e0e0e0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <strong style="color: #333;">🔍 Filtrer l'inventaire :</strong>
        <input type="text" id="searchBar" onkeyup="filtrerInventaire()" placeholder="Rechercher un matériel..."
            class="input-field" style="flex: 1; min-width: 150px;">
        <select id="catFilter" onchange="filtrerInventaire()" class="input-field"
            style="min-width: 150px; flex: initial;">
            <option value="">Toutes les catégories</option>
            <?php foreach (array_keys($stocks_par_categorie) as $cat_nom): ?>
                <option value="<?php echo htmlspecialchars($cat_nom); ?>"><?php echo htmlspecialchars($cat_nom); ?></option>
            <?php endforeach; ?>
        </select>
        <label
            style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #d32f2f; font-weight: bold;">
            <input type="checkbox" id="expFilter" onchange="filtrerInventaire()"> ⚠️ Périme bientôt
        </label>
    </div>

    <h3 style="color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📦 Inventaire actuel</h3>

    <?php if (empty($stocks_par_categorie)): ?>
        <p style="text-align: center; color: #999; font-style: italic;">Le stockage est vide.</p>
    <?php else: ?>
        <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
            <div class="category-block" data-cat="<?php echo htmlspecialchars($categorie); ?>"
                style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                <h4 class="category-header"
                    style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                    <?php echo htmlspecialchars($categorie); ?>
                </h4>

                <table class="table-manager">
                    <thead>
                        <tr>
                            <th style="width: <?php echo $peut_editer ? '40%' : '60%'; ?>;">NOM DU MATÉRIEL</th>
                            <th style="text-align: center; width: 25%;">PÉREMPTION</th>
                            <th style="text-align: center; width: 15%;">QUANTITÉ</th>
                            <?php if ($peut_editer): ?>
                                <th style="text-align: center; width: 20%;">ACTIONS</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article):
                            $sid = $article['stock_id'];
                            $raw_date = $article['date_peremption'];
                            $affichage_date = $raw_date ? date('d/m/Y', strtotime($raw_date)) : '-';
                            ?>
                            <?php if ($peut_editer): ?>
                                <form id="form-edit-<?php echo $sid; ?>" method="POST"
                                    action="remplissage.php?lieu_id=<?php echo $lieu_id; ?>">
                                    <input type="hidden" name="action" value="edit_stock"><input type="hidden" name="stock_id"
                                        value="<?php echo $sid; ?>">
                                </form>
                            <?php endif; ?>

                            <tr class="item-row" data-nom="<?php echo htmlspecialchars(strtolower($article['materiel_nom'])); ?>"
                                data-peremp="<?php echo $raw_date; ?>" style="transition: background 0.2s;">
                                <td style="font-weight: 500; color: #444;">
                                    <?php echo htmlspecialchars($article['materiel_nom']); ?>
                                </td>

                                <td style="text-align: center; color: #666;">
                                    <span class="view-mode-<?php echo $sid; ?>"><?php echo $affichage_date; ?></span>
                                    <?php if ($peut_editer): ?><input class="edit-mode-<?php echo $sid; ?> input-field" type="date"
                                            form="form-edit-<?php echo $sid; ?>" name="date_peremption" value="<?php echo $raw_date; ?>"
                                            style="display:none; padding: 5px;"><?php endif; ?>
                                </td>

                                <td style="text-align: center; font-size: 16px;">
                                    <span class="view-mode-<?php echo $sid; ?>"
                                        style="font-weight: bold;"><?php echo $article['quantite']; ?></span>
                                    <?php if ($peut_editer): ?><input class="edit-mode-<?php echo $sid; ?> input-field"
                                            type="number" form="form-edit-<?php echo $sid; ?>" name="quantite"
                                            value="<?php echo $article['quantite']; ?>" min="0"
                                            style="display:none; width: 60px; padding: 5px; text-align: center; margin: 0 auto;"><?php endif; ?>
                                </td>

                                <?php if ($peut_editer): ?>
                                    <td style="text-align: center;">
                                        <div class="view-mode-<?php echo $sid; ?>"
                                            style="display: flex; justify-content: center; gap: 10px;">
                                            <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, true)"
                                                style="background: transparent; border: none; cursor: pointer; font-size: 16px;"
                                                title="Modifier">✏️</button>
                                            <form method="POST" style="margin: 0;"
                                                onsubmit="return confirm('Retirer définitivement cet objet du sac ?');">
                                                <input type="hidden" name="action" value="delete_stock">
                                                <input type="hidden" name="stock_id" value="<?php echo $sid; ?>">
                                                <button type="submit"
                                                    style="background: transparent; border: none; cursor: pointer; font-size: 16px; color: #999;"
                                                    title="Supprimer">🗑️</button>
                                            </form>
                                        </div>
                                        <div class="edit-mode-<?php echo $sid; ?>"
                                            style="display: none; justify-content: center; gap: 10px;">
                                            <button type="submit" form="form-edit-<?php echo $sid; ?>"
                                                style="background: #4caf50; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">💾</button>
                                            <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, false)"
                                                style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">❌</button>
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

<?php if ($peut_editer): ?>
    <script>
        function toggleEdit(id, showEdit) {
            document.querySelectorAll('.view-mode-' + id).forEach(el => el.style.display = showEdit ? 'none' : '');
            document.querySelectorAll('.edit-mode-' + id).forEach(el => el.style.display = showEdit ? 'inline-block' : 'none');
        }
    </script>
<?php endif; ?>

<script>
    function filtrerInventaire() {
        const search = document.getElementById('searchBar').value.toLowerCase();
        const catFilter = document.getElementById('catFilter').value;
        const expFilter = document.getElementById('expFilter').checked;
        const limitDate = new Date();
        limitDate.setDate(limitDate.getDate() + 30);

        document.querySelectorAll('.category-block').forEach(block => {
            const blockCat = block.getAttribute('data-cat');
            let hasVisibleRow = false;

            block.querySelectorAll('.item-row').forEach(row => {
                const nom = row.getAttribute('data-nom');
                const peremp = row.getAttribute('data-peremp');
                let show = true;

                if (search && !nom.includes(search)) show = false;
                if (catFilter && blockCat !== catFilter) show = false;
                if (expFilter) {
                    if (!peremp) show = false;
                    else {
                        const pDate = new Date(peremp);
                        if (pDate > limitDate) show = false;
                    }
                }
                row.style.display = show ? '' : 'none';
                if (show) hasVisibleRow = true;
            });
            block.style.display = hasVisibleRow ? '' : 'none';
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>