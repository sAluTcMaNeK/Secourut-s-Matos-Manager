<?php
// verification_sac.php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;

if (!$peut_editer) {
    $_SESSION['flash_error'] = "🛑 Vous n'avez pas les droits pour effectuer une vérification.";
    header("Location: remplissage.php");
    exit;
}

$stmt_ev = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
$stmt_ev->execute([$event_id]);
$event = $stmt_ev->fetch();

$stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = ?");
$stmt_lieu->execute([$lieu_id]);
$lieu = $stmt_lieu->fetch();

if (!$event || !$lieu)
    die("Paramètres invalides.");

// --- LOGIQUE MATÉRIEL LOURD ---
$est_malle_radio = estTypeRadio($lieu['nom']);
$est_sac_dsa = estTypeDSA($lieu['nom']);
// ------------------------------

$stmt_link = $pdo->prepare("SELECT statut FROM evenements_lieux WHERE evenement_id = ? AND lieu_id = ?");
$stmt_link->execute([$event_id, $lieu_id]);
$liaison = $stmt_link->fetch();

if (!$liaison)
    die("Ce sac n'est pas assigné à cet événement.");
if ($liaison['statut'] === 'valide') {
    $_SESSION['flash_error'] = "⚠️ Ce sac a déjà été vérifié et scellé pour ce poste.";
    header("Location: remplissage.php?action=view_event&id=" . $event_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_verification'])) {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }
    // ----------------------------------

    $comptages = $_POST['stock'] ?? [];
    try {
        $pdo->beginTransaction();
        foreach ($comptages as $stock_id => $data) {
            $counted = (int) $data['counted'];
            $added_qty = (int) ($data['added_qty'] ?? 0);
            $reserve_stock_id = $data['reserve_stock_id'] ?? '';
            $added_date = !empty($data['added_date']) ? $data['added_date'] : null;
            $mat_id = (int) $data['materiel_id'];

            if ($counted === 0) {
                $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$stock_id]);
            } else {
                $pdo->prepare("UPDATE stocks SET quantite = ? WHERE id = ?")->execute([$counted, $stock_id]);
            }

            if ($added_qty > 0 && $reserve_stock_id !== 'acquitter') {
                if (is_numeric($reserve_stock_id)) {
                    $stmt_res = $pdo->prepare("SELECT id, quantite, date_peremption FROM stocks WHERE id = ?");
                    $stmt_res->execute([$reserve_stock_id]);
                    $reserve_lot = $stmt_res->fetch();

                    if ($reserve_lot) {
                        $added_date = $reserve_lot['date_peremption'];
                        if ($reserve_lot['quantite'] <= $added_qty) {
                            $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$reserve_lot['id']]);
                        } else {
                            $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$added_qty, $reserve_lot['id']]);
                        }
                    }
                }

                $stmt_check = $pdo->prepare("SELECT id FROM stocks WHERE materiel_id = ? AND lieu_id = ? AND IFNULL(date_peremption, '') = IFNULL(?, '')");
                $stmt_check->execute([$mat_id, $lieu_id, $added_date]);
                $stock_existant = $stmt_check->fetch();

                if ($stock_existant) {
                    $pdo->prepare("UPDATE stocks SET quantite = quantite + ? WHERE id = ?")->execute([$added_qty, $stock_existant['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption) VALUES (?, ?, ?, ?)")->execute([$mat_id, $lieu_id, $added_qty, $added_date]);
                }
            }
        }
        $pdo->prepare("UPDATE evenements_lieux SET statut = 'valide' WHERE evenement_id = ? AND lieu_id = ?")->execute([$event_id, $lieu_id]);
        $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")->execute([$_SESSION['username'], "A vérifié et scellé le sac '" . $lieu['nom'] . "' pour le DPS '" . $event['nom'] . "'"]);

        $pdo->commit();
        $_SESSION['flash_success'] = "✅ Le sac a été vérifié, mis à jour et scellé avec succès !";
        header("Location: remplissage.php?action=view_event&id=" . $event_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// NOUVEAU: Requête de récupération avec tri intelligent (Poche en premier)
$stmt_stocks = $pdo->prepare("
    SELECT s.id as stock_id, s.quantite, s.date_peremption, s.poche, 
           m.id AS materiel_id, m.nom AS materiel_nom, m.check_fonctionnel, c.nom AS categorie_nom 
    FROM stocks s 
    JOIN materiels m ON s.materiel_id = m.id 
    JOIN categories c ON m.categorie_id = c.id 
    WHERE s.lieu_id = ? 
    ORDER BY CASE WHEN s.poche = '' OR s.poche IS NULL THEN 1 ELSE 0 END, s.poche ASC, c.nom ASC, m.nom ASC
");
$stmt_stocks->execute([$lieu_id]);
$stocks = $stmt_stocks->fetchAll();

// NOUVEAU: Regroupement par poche, puis par catégorie
$stocks_par_groupe = [];
foreach ($stocks as $s) {
    if (!empty($s['poche'])) {
        $nom_groupe = "🎒 Poche : " . $s['poche'];
    } else {
        $nom_groupe = "📦 " . $s['categorie_nom'];
    }
    $stocks_par_groupe[$nom_groupe][] = $s;
}

$stmt_reserves = $pdo->query("SELECT s.id as reserve_stock_id, s.materiel_id, s.quantite, s.date_peremption, l.nom as lieu_nom FROM stocks s JOIN lieux_stockage l ON s.lieu_id = l.id WHERE l.est_reserve = 1 AND s.quantite > 0 ORDER BY s.date_peremption ASC");
$toutes_les_reserves = $stmt_reserves->fetchAll();
$reserves_par_materiel = [];
foreach ($toutes_les_reserves as $res) {
    $reserves_par_materiel[$res['materiel_id']][] = $res;
}

$date_event_timestamp = strtotime($event['date_evenement']);

require_once 'includes/header.php';
?>

<div class="white-box mb-20" style="position: sticky; top: 0; z-index: 100; border-bottom: 3px solid #d32f2f;">
    <div class="flex-between">
        <div>
            <a href="remplissage.php?action=view_event&id=<?php echo $event_id; ?>" class="text-muted text-md"
                style="text-decoration: none;">⬅ Annuler et retourner au DPS</a>
            <h2 class="page-title mt-5 text-dark">📋 Vérification : <?php echo htmlspecialchars($lieu['nom']); ?></h2>
            <p class="mt-5 mb-0 text-danger font-bold">Pour le DPS : <?php echo htmlspecialchars($event['nom']); ?>
                (<?php echo date('d/m/Y', $date_event_timestamp); ?>)</p>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger mb-20 font-bold"><?php echo $error; ?></div>
<?php endif; ?>

<form id="form-verification" method="POST"
    action="verification_sac.php?event_id=<?php echo $event_id; ?>&lieu_id=<?php echo $lieu_id; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="valider_verification" value="1">

    <div class="alert alert-warning mb-25">
        <strong>Consignes de vérification :</strong>
        <ul class="mt-5 mb-0 text-md text-muted" style="padding-left: 20px;">
            <li><strong class="text-danger">⚠️ SÉCURITÉ MATÉRIEL SPÉCIFIQUE :</strong> Le bouton de scellage restera
                bloqué tant que <span class="text-danger font-bold">TOUTES</span> les vérifications cochables (Radios,
                DSA, Checks Fonctionnels) ne seront pas validées.</li>
            <li>S'il manque des consommables/accessoires, vous pourrez choisir de les recompléter ou d'acquitter
                l'écart.</li>
            <li><strong>Sécurité :</strong> Si un consommable n'est plus présent ou périmé le jour du DPS, il est <span
                    class="text-danger font-bold">obligatoire</span> de le recompléter.</li>
        </ul>
    </div>

    <?php if (empty($stocks_par_groupe)): ?>
        <p class="text-center text-muted font-italic">Ce sac est actuellement vide dans la base de données.</p>
    <?php else: ?>
        <?php foreach ($stocks_par_groupe as $nom_groupe => $articles): ?>
            <div class="category-block mb-30" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">

                <?php
                // NOUVEAU: Gestion des couleurs de l'en-tête (Poche = Bleu, Reste = Couleur Classique)
                if (strpos($nom_groupe, 'Poche') !== false) {
                    $couleur = ['bg' => '#1976D2', 'text' => 'white'];
                } else {
                    $cat_pure = str_replace('📦 ', '', $nom_groupe);
                    $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($cat_pure) : ['bg' => '#2c3e50', 'text' => 'white'];
                }
                ?>
                <h3 class="category-header"
                    style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                    <?php echo htmlspecialchars($nom_groupe); ?>
                </h3>

                <table class="table-manager" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th class="p-10 text-left" style="width: 40%;">Matériel</th>
                            <th class="p-10 text-center" style="width: 20%;">Péremption</th>
                            <th class="p-10 text-center" style="width: 20%;">Théorique</th>
                            <th class="p-10 text-center" style="width: 20%;">Validé (Bon état)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $art):
                            $sid = $art['stock_id'];
                            $theo = $art['quantite'];
                            $mat_id = $art['materiel_id'];
                            $est_perime = false;
                            $texte_peremption = '-';

                            $est_item_radio = ($est_malle_radio && estTypeRadio($art['materiel_nom']));
                            $est_item_dsa = ($est_sac_dsa && estTypeDSA($art['materiel_nom']));
                            $est_item_check = ($art['check_fonctionnel'] == 1);

                            $est_item_special = ($est_item_radio || $est_item_dsa || $est_item_check);

                            if ($art['date_peremption']) {
                                $date_art_timestamp = strtotime($art['date_peremption']);
                                if ($date_art_timestamp < $date_event_timestamp)
                                    $est_perime = true;
                                $texte_peremption = date('d/m/Y', $date_art_timestamp);
                            }
                            $lots_dispos = $reserves_par_materiel[$mat_id] ?? [];
                            ?>
                            <tr class="item-row" data-stock-id="<?php echo $sid; ?>" data-theo="<?php echo $theo; ?>"
                                data-nom="<?php echo htmlspecialchars($art['materiel_nom']); ?>"
                                data-perime="<?php echo $est_perime ? 'true' : 'false'; ?>"
                                style="border-bottom: 1px solid #eee; <?php echo $est_perime ? 'background-color: #ffebee;' : ''; ?>">

                                <td class="font-bold text-dark p-10">
                                    <?php echo htmlspecialchars($art['materiel_nom']); ?>
                                    <?php if ($est_perime): ?>
                                        <div class="text-sm text-danger font-bold mt-5">⚠️ Périmé le jour du poste</div>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-muted text-md p-10"><?php echo $texte_peremption; ?></td>
                                <td class="text-center text-muted font-bold text-lg p-10"><?php echo $theo; ?></td>

                                <td class="text-center p-10">
                                    <?php if ($est_item_radio): ?>
                                        <div class="text-left mt-10 mb-10"
                                            style="display: inline-block; text-align: left; user-select: none;">
                                            <label class="display-block mb-10 font-bold" style="cursor: pointer; font-size: 15px;">
                                                <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                    onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>)"
                                                    style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                🔋 Batterie chargée
                                            </label>
                                            <label class="display-block mb-10 font-bold" style="cursor: pointer; font-size: 15px;">
                                                <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                    onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>)"
                                                    style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                📻 Bon état de marche
                                            </label>
                                            <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                    onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>)"
                                                    style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                🔴 Appareil éteint
                                            </label>
                                        </div>
                                        <input type="hidden" name="stock[<?php echo $sid; ?>][counted]" id="counted-<?php echo $sid; ?>"
                                            class="input-counted" value="0">

                                    <?php elseif ($est_item_dsa): ?>
                                        <div class="text-left mt-10 mb-10"
                                            style="display: inline-block; text-align: left; user-select: none;">
                                            <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                    onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>)"
                                                    style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                🔋 Niveau des piles OK
                                            </label>
                                        </div>
                                        <input type="hidden" name="stock[<?php echo $sid; ?>][counted]" id="counted-<?php echo $sid; ?>"
                                            class="input-counted" value="0">

                                    <?php elseif ($est_item_check): ?>
                                        <div class="text-left mt-10 mb-10"
                                            style="display: inline-block; text-align: left; user-select: none;">
                                            <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                    onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>)"
                                                    style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                ⚙️ Fonctionnel.le
                                            </label>
                                        </div>
                                        <input type="hidden" name="stock[<?php echo $sid; ?>][counted]" id="counted-<?php echo $sid; ?>"
                                            class="input-counted" value="0">

                                    <?php else: ?>
                                        <input type="number" min="0" name="stock[<?php echo $sid; ?>][counted]" class="input-counted"
                                            value="<?php echo $est_perime ? 0 : $theo; ?>" oninput="checkDifferenceVerif(this)" <?php echo $est_perime ? 'readonly' : ''; ?>
                                            style="width: 70px; padding: 8px; font-size: 16px; text-align: center; border: 2px solid <?php echo $est_perime ? '#f44336' : '#ccc'; ?>; border-radius: 4px; outline: none; <?php echo $est_perime ? 'background-color: #ffcdd2;' : ''; ?>">
                                    <?php endif; ?>

                                    <input type="hidden" name="stock[<?php echo $sid; ?>][materiel_id]"
                                        value="<?php echo $mat_id; ?>">
                                </td>
                            </tr>

                            <?php if (!$est_item_special): ?>
                                <tr class="refill-row" id="refill-<?php echo $sid; ?>"
                                    style="display: <?php echo $est_perime ? 'table-row' : 'none'; ?>; background-color: #fdfaf6; border-bottom: 2px solid #ddd;">
                                    <td colspan="4" style="padding: 10px 10px 10px 40px; border-left: 4px solid #ef6c00;">
                                        <span class="text-warning font-bold text-md">
                                            ↳ Il manque <span class="missing-display"><?php echo $est_perime ? $theo : '0'; ?></span>
                                            unité(s).
                                        </span>
                                        <div class="flex-center mt-10 bg-white p-10 border-radius-4"
                                            style="border: 1px solid #ccc; flex-wrap: wrap;">
                                            <label class="text-sm font-bold text-dark">Action :</label>
                                            <select name="stock[<?php echo $sid; ?>][reserve_stock_id]" class="input-reserve-lot"
                                                style="padding: 6px; border: 1px solid #aaa; border-radius: 3px; max-width: 350px;"
                                                onchange="updateMaxQtyVerif(this)">
                                                <option value="acquitter" class="opt-acquitter"
                                                    style="<?php echo $est_perime ? 'display:none;' : ''; ?>">✅ Acquitter l'écart
                                                </option>
                                                <option value="" class="opt-separator" <?php echo $est_perime ? 'selected' : 'disabled'; ?>>-- Recompléter avec --</option>
                                                <?php foreach ($lots_dispos as $res):
                                                    $date_format = $res['date_peremption'] ? date('d/m/Y', strtotime($res['date_peremption'])) : 'Aucune';
                                                    $label = htmlspecialchars($res['lieu_nom']) . " | Pér: " . $date_format . " | Dispo: " . $res['quantite'];
                                                    ?>
                                                    <option value="<?php echo $res['reserve_stock_id']; ?>"
                                                        data-max="<?php echo $res['quantite']; ?>"><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                                <option value="manual">Saisie manuelle externe</option>
                                            </select>

                                            <div class="qty-container flex-center ml-10"
                                                style="display: <?php echo $est_perime ? 'flex' : 'none'; ?>;">
                                                <label class="text-sm font-bold text-dark">Qté :</label>
                                                <input type="number" name="stock[<?php echo $sid; ?>][added_qty]"
                                                    class="input-added-qty" value="0" min="0" oninput="checkMaxQty(this)"
                                                    style="width: 60px; padding: 6px; text-align: center; border: 1px solid #aaa; border-radius: 3px;">
                                            </div>

                                            <div class="manual-date-container flex-center ml-10" style="display: none;">
                                                <label class="text-sm font-bold text-dark">Péremption :</label>
                                                <input type="date" name="stock[<?php echo $sid; ?>][added_date]"
                                                    class="input-added-date"
                                                    style="padding: 6px; border: 1px solid #aaa; border-radius: 3px;">
                                            </div>
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

    <div class="text-center mt-30 mb-30 pb-30">
        <button type="button" id="btn-valider-sac" onclick="validerFormulaireVerification()"
            class="btn btn-success-dark btn-lg">🔒 Valider et Sceller le sac</button>
    </div>
</form>

<script>
    function checkSpecial(sid, theo) {
        let checks = document.querySelectorAll('.special-check-' + sid);
        let allChecked = true;
        checks.forEach(c => { if (!c.checked) allChecked = false; });

        let hiddenInput = document.getElementById('counted-' + sid);
        if (hiddenInput) {
            hiddenInput.value = allChecked ? theo : 0;
        }

        if (typeof checkDifferenceVerif === 'function') {
            checkDifferenceVerif(hiddenInput);
        }

        checkGlobalSpecialState();
    }

    function checkGlobalSpecialState() {
        let allCheckboxes = document.querySelectorAll('input[type="checkbox"][class^="special-check-"]');
        let btn = document.getElementById('btn-valider-sac');

        if (allCheckboxes.length > 0) {
            let allValid = true;
            allCheckboxes.forEach(c => {
                if (!c.checked) allValid = false;
            });

            if (btn) {
                btn.disabled = !allValid;
                if (allValid) {
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                } else {
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                }
            }
        } else {
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        checkGlobalSpecialState();
    });
</script>

<?php require_once 'includes/footer.php'; ?>