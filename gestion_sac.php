<?php
// gestion_sac.php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Création des colonnes si elles n'existent pas
try {
    $pdo->exec("ALTER TABLE stocks ADD COLUMN poche VARCHAR(100) DEFAULT ''");
} catch (PDOException $e) {
}

try {
    $pdo->exec("ALTER TABLE stocks ADD COLUMN note VARCHAR(255) DEFAULT ''");
} catch (PDOException $e) {
}

$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;
// NOUVEAU: On applique les droits Matos
$peut_editer = $peut_editer_matos;

// SÉCURITÉ : Si l'utilisateur n'a pas les droits, on le renvoie directement sur la page de consultation
if (!$peut_editer) {
    $_SESSION['flash_error'] = "🛑 Accès refusé : Vous n'avez pas les droits pour modifier le contenu des sacs.";
    header("Location: lieux.php?id=" . $lieu_id);
    exit;
}

// --- VÉRIFICATION DU VERROUILLAGE (SCELLÉ) ---
$dps_scelle = false;
if ($lieu_id > 0) {
    $stmt_scelle = $pdo->prepare("
        SELECT e.nom, e.date_evenement 
        FROM evenements_lieux el 
        JOIN evenements e ON el.evenement_id = e.id 
        WHERE el.lieu_id = ? AND el.statut = 'valide' AND DATE(e.date_evenement) >= CURRENT_DATE()
        LIMIT 1
    ");
    $stmt_scelle->execute([$lieu_id]);
    $dps_scelle = $stmt_scelle->fetch();
}
$est_verrouille = ($dps_scelle !== false);
// ---------------------------------------------

// ==========================================
// TRAITEMENT DES FORMULAIRES (PATTERN PRG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }
    // ----------------------------------

    $action = $_POST['action'] ?? '';
    $stmt_lieu_info = $pdo->prepare("SELECT nom, est_reserve FROM lieux_stockage WHERE id = ?");
    $stmt_lieu_info->execute([$lieu_id]);
    $lieu_info = $stmt_lieu_info->fetch();
    $nom_du_lieu = $lieu_info['nom'] ?? "Lieu inconnu";
    $est_res_dest = $lieu_info ? ($lieu_info['est_reserve'] == 1) : false;

    try {
        if ($action === 'edit_stock') {
            $stock_id = (int) $_POST['stock_id'];
            $qty = (int) $_POST['quantite'];
            $date_p = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;
            $reserve_stock_id = $_POST['reserve_stock_id'] ?? '';
            $poche = trim($_POST['poche'] ?? '');
            $note = trim($_POST['note'] ?? ''); // NOUVEAU: Récupère la note

            $stmt_current = $pdo->prepare("SELECT s.quantite, s.materiel_id, m.nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id WHERE s.id = ?");
            $stmt_current->execute([$stock_id]);
            $current = $stmt_current->fetch();
            $nom_mat_edit = $current ? $current['nom'] : "Objet";
            $old_qty = $current ? $current['quantite'] : 0;

            if ($qty <= 0) {
                $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);
                $action_texte = "A retiré l'objet '" . $nom_mat_edit . "' du lieu : " . $nom_du_lieu;
                $_SESSION['flash_success'] = "✅ Objet retiré du stockage.";
            } else {
                $action_suffix = "";

                // GESTION DU SURPLUS DEPUIS LA RÉSERVE
                if ($qty > $old_qty && !$est_res_dest) {
                    $diff = $qty - $old_qty;

                    if (is_numeric($reserve_stock_id)) {
                        $stmt_res = $pdo->prepare("SELECT id, quantite, lieu_id FROM stocks WHERE id = ?");
                        $stmt_res->execute([$reserve_stock_id]);
                        $reserve_lot = $stmt_res->fetch();

                        if ($reserve_lot) {
                            if ($reserve_lot['quantite'] <= $diff) {
                                $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$reserve_lot['id']]);
                            } else {
                                $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$diff, $reserve_lot['id']]);
                            }
                            $nom_res = $pdo->query("SELECT nom FROM lieux_stockage WHERE id = " . $reserve_lot['lieu_id'])->fetchColumn() ?: 'Réserve';
                            $action_suffix = " (Ajout de +$diff prélevé dans : $nom_res)";
                        }
                    } elseif ($reserve_stock_id === 'manual') {
                        $action_suffix = " (Ajout de +$diff via correction manuelle hors base)";
                    } else {
                        $_SESSION['flash_error'] = "❌ Vous devez sélectionner une réserve pour justifier d'où provient le matériel ajouté.";
                        header("Location: gestion_sac.php?lieu_id=" . $lieu_id);
                        exit;
                    }
                }

                // NOUVEAU: On met à jour la quantité, la poche ET LA NOTE
                $pdo->prepare("UPDATE stocks SET quantite = :qty, date_peremption = :dp, poche = :poche, note = :note WHERE id = :id")->execute(['qty' => $qty, 'dp' => $date_p, 'poche' => $poche, 'note' => $note, 'id' => $stock_id]);

                if ($qty < $old_qty) {
                    $diff = $old_qty - $qty;
                    $action_texte = "A réduit la quantité de '" . $nom_mat_edit . "' (-$diff) (Lieu : " . $nom_du_lieu . ")";
                } elseif ($qty > $old_qty) {
                    $action_texte = "A augmenté la quantité de '" . $nom_mat_edit . "' (Lieu : " . $nom_du_lieu . ")" . $action_suffix;
                } else {
                    $action_texte = "A mis à jour la fiche de '" . $nom_mat_edit . "' (Lieu : " . $nom_du_lieu . ")";
                }
                $_SESSION['flash_success'] = "✅ Fiche mise à jour.";
            }
            if (function_exists('logAction'))
                logAction($pdo, $action_texte);
            else
                $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")->execute([$_SESSION['username'], $action_texte]);

        } elseif ($action === 'delete_stock') {
            $stock_id = (int) $_POST['stock_id'];
            $stmt_mat_nom = $pdo->prepare("SELECT m.nom FROM materiels m JOIN stocks s ON m.id = s.materiel_id WHERE s.id = ?");
            $stmt_mat_nom->execute([$stock_id]);
            $nom_mat_del = $stmt_mat_nom->fetchColumn() ?: "Objet";

            $pdo->prepare("DELETE FROM stocks WHERE id = :id")->execute(['id' => $stock_id]);
            if (function_exists('logAction'))
                logAction($pdo, "A supprimé définitivement '" . $nom_mat_del . "' (Lieu : " . $nom_du_lieu . ")");
            $_SESSION['flash_success'] = "🗑️ L'objet a été retiré du sac.";

        } elseif ($action === 'add_stock') {
            $materiel_id = (int) $_POST['materiel_id'];
            $quantite = (int) $_POST['quantite'];
            $reserve_stock_id = $_POST['reserve_stock_id'] ?? 'manual';
            $date_peremption = !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null;
            $poche = trim($_POST['poche'] ?? '');
            $note = trim($_POST['note'] ?? ''); // NOUVEAU: Récupère la note

            if ($materiel_id && $quantite > 0) {
                $added_date = $date_peremption;
                $action_suffix = "";

                if (!$est_res_dest && is_numeric($reserve_stock_id)) {
                    $stmt_res = $pdo->prepare("SELECT id, quantite, date_peremption, lieu_id FROM stocks WHERE id = ?");
                    $stmt_res->execute([$reserve_stock_id]);
                    $reserve_lot = $stmt_res->fetch();

                    if ($reserve_lot) {
                        $added_date = $reserve_lot['date_peremption'];
                        if ($reserve_lot['quantite'] <= $quantite) {
                            $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$reserve_lot['id']]);
                        } else {
                            $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$quantite, $reserve_lot['id']]);
                        }
                        $nom_res = $pdo->query("SELECT nom FROM lieux_stockage WHERE id = " . $reserve_lot['lieu_id'])->fetchColumn() ?: 'Réserve';
                        $action_suffix = " (Prélevé dans : $nom_res)";
                    }
                }

                // On vérifie s'il existe déjà la même chose dans LA MÊME POCHE
                $stmt_check = $pdo->prepare("SELECT id, quantite FROM stocks WHERE materiel_id = :mat AND lieu_id = :lieu AND IFNULL(date_peremption, '') = IFNULL(:peremp, '') AND IFNULL(poche, '') = IFNULL(:poche, '')");
                $stmt_check->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'peremp' => $added_date, 'poche' => $poche]);
                $stock_existant = $stmt_check->fetch();

                if ($stock_existant) {
                    $pdo->prepare("UPDATE stocks SET quantite = :qty, note = :note WHERE id = :id")->execute(['qty' => $stock_existant['quantite'] + $quantite, 'note' => $note, 'id' => $stock_existant['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption, poche, note) VALUES (:mat, :lieu, :qty, :peremp, :poche, :note)")->execute(['mat' => $materiel_id, 'lieu' => $lieu_id, 'qty' => $quantite, 'peremp' => $added_date, 'poche' => $poche, 'note' => $note]);
                }

                $nom_mat = $pdo->query("SELECT nom FROM materiels WHERE id = $materiel_id")->fetchColumn() ?: "Objet";
                if (function_exists('logAction'))
                    logAction($pdo, "A ajouté $quantite x $nom_mat dans : $nom_du_lieu" . $action_suffix);
                $_SESSION['flash_success'] = "➕ Matériel inséré avec succès.";
            }
        }
        header("Location: gestion_sac.php?lieu_id=" . $lieu_id);
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "❌ Erreur : " . $e->getMessage();
        header("Location: gestion_sac.php?lieu_id=" . $lieu_id);
        exit;
    }
}

if ($lieu_id === 0) {
    header('Location: lieux.php');
    exit;
}

$stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
$stmt_lieu->execute(['id' => $lieu_id]);
$lieu = $stmt_lieu->fetch();

if (!$lieu) {
    header('Location: lieux.php');
    exit;
}
$est_reserve = $lieu['est_reserve'] == 1;

$stmt_mat = $pdo->query("SELECT m.id, m.nom, c.nom AS categorie_nom FROM materiels m JOIN categories c ON m.categorie_id = c.id ORDER BY c.nom, m.nom");
$materiels = $stmt_mat->fetchAll();

// NOUVEAU: Récupération de s.poche et s.note
$stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.materiel_id, s.quantite, s.date_peremption, s.poche, s.note, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom, s.date_peremption");
$stmt_stocks->execute(['lieu_id' => $lieu_id]);
$stocks = $stmt_stocks->fetchAll();

$stocks_par_categorie = [];
foreach ($stocks as $stock) {
    $stocks_par_categorie[$stock['categorie_nom']][] = $stock;
}

$reserves_par_materiel = [];
if (!$est_reserve) {
    $stmt_reserves = $pdo->query("SELECT s.id as reserve_stock_id, s.materiel_id, s.quantite, s.date_peremption, l.nom as lieu_nom FROM stocks s JOIN lieux_stockage l ON s.lieu_id = l.id WHERE l.est_reserve = 1 AND s.quantite > 0 ORDER BY s.date_peremption ASC");
    foreach ($stmt_reserves->fetchAll() as $res) {
        $reserves_par_materiel[$res['materiel_id']][] = $res;
    }
}

$icone_affichage = !empty($lieu['icone']) ? $lieu['icone'] : '📦';
$afficher_banniere_verrou = ($peut_editer && $est_verrouille);
if ($est_verrouille) {
    $peut_editer = false;
}

require_once 'includes/header.php';
?>

<script>window.reservesData = <?php echo json_encode($reserves_par_materiel); ?>;</script>

<div class="white-box">

    <div class="flex-between-start border-bottom pb-15 mb-20">
        <div>
            <a href="lieux.php?id=<?php echo $lieu_id; ?>" class="text-muted text-md" style="text-decoration: none;">⬅
                Retour aux détails du sac</a>
            <h2 class="page-title mt-10">
                <?php echo $icone_affichage; ?> Remplissage : <?php echo htmlspecialchars($lieu['nom']); ?>
            </h2>
        </div>
    </div>
    <?php if (isset($afficher_banniere_verrou) && $afficher_banniere_verrou): ?>
        <div class="alert alert-warning mb-30"
            style="border-left: 5px solid #ff9800; background-color: #fff3e0; color: #e65100;">
            <h3 style="margin-top: 0; color: #e65100;">🔒 Sac scellé pour un DPS</h3>
            <p style="margin-bottom: 0;">Ce sac est validé pour le poste
                "<strong><?php echo htmlspecialchars($dps_scelle['nom']); ?></strong>" prévu le
                <strong><?php echo date('d/m/Y', strtotime($dps_scelle['date_evenement'])); ?></strong>.<br>
                Les modifications de l'inventaire sont bloquées. Le sac sera automatiquement déverrouillé une fois la date
                de l'événement passée.
            </p>
        </div>
    <?php endif; ?>
    <?php if ($peut_editer): ?>
        <div class="form-box mb-30">
            <h3 class="mt-0 text-primary" style="font-size: 16px;">➕ Ajouter un nouveau matériel</h3>
            <form action="gestion_sac.php?lieu_id=<?php echo $lieu_id; ?>" method="POST" class="flex-row align-center">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_stock">

                <div class="flex-2 min-w-200">
                    <label class="font-bold text-md mb-5 display-block">Catalogue</label>
                    <select name="materiel_id" id="select-materiel" required class="input-field mb-0"
                        onchange="updateReserveOptions(this.value)">
                        <option value="">-- Sélectionner le matériel --</option>
                        <?php foreach ($materiels as $mat): ?>
                            <option value="<?php echo $mat['id']; ?>">
                                <?php echo htmlspecialchars($mat['categorie_nom'] . ' - ' . $mat['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-2 min-w-150" style="<?php echo ($est_reserve) ? 'display:none;' : ''; ?>">
                    <label class="font-bold text-md mb-5 display-block" style="color: #1976D2;">🎒 Poche</label>
                    <input type="text" name="poche" placeholder="Ex: Face avant..." class="input-field mb-0"
                        list="liste-poches">
                    <datalist id="liste-poches">
                        <?php
                        $stmt_poches = $pdo->query("SELECT DISTINCT poche FROM stocks WHERE lieu_id = $lieu_id AND poche != '' AND poche IS NOT NULL");
                        while ($p = $stmt_poches->fetchColumn()) {
                            echo "<option value=\"" . htmlspecialchars($p) . "\">";
                        }
                        ?>
                    </datalist>
                </div>

                <div class="flex-2 min-w-150">
                    <label class="font-bold text-md mb-5 display-block" style="color: #e65100;">📝 Note / Min.</label>
                    <input type="text" name="note" placeholder="Ex: Min. 2 dans le sac..." class="input-field mb-0">
                </div>

                <?php if (!$est_reserve): ?>
                    <div class="flex-2 min-w-200">
                        <label class="font-bold text-md mb-5 display-block">Prélever depuis :</label>
                        <select name="reserve_stock_id" id="select-reserve-lot" class="input-field mb-0"
                            onchange="toggleManualDate(this.value)" disabled>
                            <option value="">Sélectionnez d'abord un matériel</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="flex-1 min-w-100">
                    <label class="font-bold text-md mb-5 display-block">Quantité</label>
                    <input type="number" name="quantite" id="input-add-qty" required min="1" value="1"
                        class="input-field mb-0" oninput="checkMaxQty(this)">
                </div>

                <div id="container-date-peremption" class="flex-1 min-w-150"
                    style="<?php echo (!$est_reserve) ? 'display:none;' : ''; ?>">
                    <label class="font-bold text-md mb-5 display-block">Péremption</label>
                    <input type="date" name="date_peremption" id="input-add-date" class="input-field mb-0">
                </div>

                <div>
                    <button type="submit" class="btn btn-danger-dark">Insérer</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="flex-row-sm align-center bg-white p-10 mb-20 border-radius-4" style="border: 1px solid #e0e0e0;">
        <strong class="text-dark">🔍 Filtrer l'inventaire :</strong>
        <input type="text" id="searchBar" onkeyup="filtrerInventaire()" placeholder="Rechercher un matériel..."
            class="input-field flex-1 min-w-150 mb-0">
        <select id="catFilter" onchange="filtrerInventaire()" class="input-field min-w-150 mb-0" style="flex: initial;">
            <option value="">Toutes les catégories</option>
            <?php foreach (array_keys($stocks_par_categorie) as $cat_nom): ?>
                <option value="<?php echo htmlspecialchars($cat_nom); ?>"><?php echo htmlspecialchars($cat_nom); ?></option>
            <?php endforeach; ?>
        </select>
        <label class="flex-center font-bold text-danger" style="cursor: pointer;">
            <input type="checkbox" id="expFilter" onchange="filtrerInventaire()"> ⚠️ Périme bientôt
        </label>
    </div>

    <h3 class="section-title">📦 Inventaire actuel</h3>

    <?php if (empty($stocks_par_categorie)): ?>
        <p class="text-center text-muted font-italic p-20">Le stockage est vide.</p>
    <?php else: ?>
        <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
            <div class="category-block mb-30" data-cat="<?php echo htmlspecialchars($categorie); ?>"
                style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                <h4 class="category-header"
                    style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                    <?php echo htmlspecialchars($categorie); ?>
                </h4>

                <table class="table-manager">
                    <thead>
                        <tr>
                            <th style="width: <?php echo $peut_editer ? '40%' : '60%'; ?>;">NOM DU MATÉRIEL</th>
                            <th class="text-center" style="width: 25%;">PÉREMPTION</th>
                            <th class="text-center" style="width: 15%;">QUANTITÉ</th>
                            <?php if ($peut_editer): ?>
                                <th class="text-center" style="width: 20%;">ACTIONS</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article):
                            $sid = $article['stock_id'];
                            $mat_id = $article['materiel_id'];
                            $raw_date = $article['date_peremption'];
                            $affichage_date = $raw_date ? date('d/m/Y', strtotime($raw_date)) : '-';
                            ?>
                            <?php if ($peut_editer): ?>
                                <form id="form-edit-<?php echo $sid; ?>" method="POST"
                                    action="gestion_sac.php?lieu_id=<?php echo $lieu_id; ?>"
                                    onsubmit="return validateEdit(<?php echo $sid; ?>, <?php echo $est_reserve ? 'true' : 'false'; ?>)">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="edit_stock">
                                    <input type="hidden" name="stock_id" value="<?php echo $sid; ?>">
                                </form>
                            <?php endif; ?>

                            <tr class="item-row" data-nom="<?php echo htmlspecialchars(strtolower($article['materiel_nom'])); ?>"
                                data-peremp="<?php echo $raw_date; ?>" style="transition: background 0.2s;">

                                <td class="font-bold text-dark">
                                    <?php echo htmlspecialchars($article['materiel_nom']); ?>

                                    <?php if (!empty($article['poche'])): ?>
                                        <div class="view-mode-<?php echo $sid; ?> text-sm mt-5" style="color: #1976D2;">
                                            🎒 <?php echo htmlspecialchars($article['poche']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($article['note'])): ?>
                                        <div class="view-mode-<?php echo $sid; ?> text-sm mt-5" style="color: #e65100;">
                                            📝 <?php echo htmlspecialchars($article['note']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($peut_editer): ?>
                                        <input class="edit-mode-<?php echo $sid; ?> input-field mt-5" type="text"
                                            form="form-edit-<?php echo $sid; ?>" name="poche"
                                            value="<?php echo htmlspecialchars($article['poche'] ?? ''); ?>"
                                            placeholder="Rangé dans la poche..." list="liste-poches"
                                            style="display:none; padding: 5px; width: 90%; font-size: 12px;">

                                        <input class="edit-mode-<?php echo $sid; ?> input-field mt-5" type="text"
                                            form="form-edit-<?php echo $sid; ?>" name="note"
                                            value="<?php echo htmlspecialchars($article['note'] ?? ''); ?>"
                                            placeholder="Note ou qté min..."
                                            style="display:none; padding: 5px; width: 90%; font-size: 12px;">
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-muted">
                                    <span class="view-mode-<?php echo $sid; ?>"><?php echo $affichage_date; ?></span>
                                    <?php if ($peut_editer): ?><input class="edit-mode-<?php echo $sid; ?> input-field" type="date"
                                            form="form-edit-<?php echo $sid; ?>" name="date_peremption" value="<?php echo $raw_date; ?>"
                                            style="display:none; padding: 5px; margin: 0 auto;"><?php endif; ?>
                                </td>

                                <td class="text-center text-xl">
                                    <span class="view-mode-<?php echo $sid; ?> font-bold"><?php echo $article['quantite']; ?></span>
                                    <?php if ($peut_editer): ?>
                                        <input class="edit-mode-<?php echo $sid; ?> input-field input-edit-qty" type="number"
                                            form="form-edit-<?php echo $sid; ?>" name="quantite"
                                            value="<?php echo $article['quantite']; ?>" data-old="<?php echo $article['quantite']; ?>"
                                            min="0"
                                            oninput="checkEditDifference(this, <?php echo $sid; ?>, <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                            style="display:none; width: 60px; padding: 5px; text-align: center; margin: 0 auto;">
                                    <?php endif; ?>
                                </td>

                                <?php if ($peut_editer): ?>
                                    <td class="text-center">
                                        <div class="view-mode-<?php echo $sid; ?> flex-center" style="justify-content: center;">
                                            <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, true)" class="btn-icon"
                                                title="Modifier">✏️</button>
                                            <form method="POST" style="margin: 0;"
                                                onsubmit="return confirm('Retirer définitivement cet objet ?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_stock">
                                                <input type="hidden" name="stock_id" value="<?php echo $sid; ?>">
                                                <button type="submit" class="btn-icon text-muted" title="Supprimer">🗑️</button>
                                            </form>
                                        </div>
                                        <div class="edit-mode-<?php echo $sid; ?> flex-center"
                                            style="display: none; justify-content: center;">
                                            <button type="submit" form="form-edit-<?php echo $sid; ?>"
                                                class="btn btn-success btn-sm">💾</button>
                                            <button type="button" onclick="toggleEdit(<?php echo $sid; ?>, false)"
                                                class="btn btn-danger btn-sm">❌</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($peut_editer && !$est_reserve): ?>
                                <tr class="edit-refill-row" id="edit-refill-<?php echo $sid; ?>"
                                    style="display: none; background-color: #fdfaf6; border-bottom: 2px solid #ddd;">
                                    <td colspan="4" style="padding: 10px 10px 10px 40px; border-left: 4px solid #2c3e50;">
                                        <div class="flex-center">
                                            <label class="text-sm font-bold text-dark">L'ajout (+<span
                                                    class="diff-display text-danger font-bold">0</span>) provient de :</label>
                                            <select name="reserve_stock_id" form="form-edit-<?php echo $sid; ?>"
                                                class="input-reserve-lot input-field mb-0" style="width: auto; padding: 5px;"
                                                onchange="updateEditMaxQty(this, <?php echo $sid; ?>)">
                                                <option value="">-- Sélectionner une réserve --</option>
                                                <?php
                                                $lots = $reserves_par_materiel[$mat_id] ?? [];
                                                foreach ($lots as $res):
                                                    $date_format = $res['date_peremption'] ? date('d/m/Y', strtotime($res['date_peremption'])) : 'Aucune';
                                                    $label = htmlspecialchars($res['lieu_nom']) . " | Pér: " . $date_format . " | Dispo: " . $res['quantite'];
                                                    ?>
                                                    <option value="<?php echo $res['reserve_stock_id']; ?>"
                                                        data-max="<?php echo $res['quantite']; ?>"><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                                <option value="manual">Correction manuelle (Hors base)</option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>