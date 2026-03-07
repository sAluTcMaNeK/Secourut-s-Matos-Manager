<?php
// verification_sac.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;
$peut_editer = ($_SESSION['can_edit'] === 1);

if (!$peut_editer) {
    $_SESSION['flash_error'] = "🛑 Vous n'avez pas les droits pour effectuer une vérification.";
    header("Location: remplissage.php");
    exit;
}

// 1. VÉRIFICATIONS DE BASE
$stmt_ev = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
$stmt_ev->execute([$event_id]);
$event = $stmt_ev->fetch();

$stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = ?");
$stmt_lieu->execute([$lieu_id]);
$lieu = $stmt_lieu->fetch();

if (!$event || !$lieu)
    die("Paramètres invalides.");

// 2. VÉRIFICATION DU STATUT DU SAC
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

// ==========================================
// 3. TRAITEMENT DU FORMULAIRE DE VÉRIFICATION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_verification'])) {
    $comptages = $_POST['stock'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($comptages as $stock_id => $data) {
            $counted = (int) $data['counted'];
            $added_qty = (int) ($data['added_qty'] ?? 0);
            $reserve_stock_id = $data['reserve_stock_id'] ?? '';
            $added_date = !empty($data['added_date']) ? $data['added_date'] : null;
            $mat_id = (int) $data['materiel_id'];

            // A. Mise à jour du lot existant (ON NE TOUCHE PAS À SA DATE D'ORIGINE)
            if ($counted === 0) {
                // Tout a été jeté (périmé ou perdu), on supprime la ligne
                $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$stock_id]);
            } else {
                // On met à jour uniquement la quantité validée
                $pdo->prepare("UPDATE stocks SET quantite = ? WHERE id = ?")->execute([$counted, $stock_id]);
            }

            // B. Traitement du nouveau lot rajouté
            if ($added_qty > 0 && $reserve_stock_id !== 'acquitter') {

                // Si on pioche dans un lot de réserve précis
                if (is_numeric($reserve_stock_id)) {
                    $stmt_res = $pdo->prepare("SELECT id, quantite, date_peremption FROM stocks WHERE id = ?");
                    $stmt_res->execute([$reserve_stock_id]);
                    $reserve_lot = $stmt_res->fetch();

                    if ($reserve_lot) {
                        $added_date = $reserve_lot['date_peremption']; // On force la date du lot de réserve

                        // Déduction de la réserve source
                        if ($reserve_lot['quantite'] <= $added_qty) {
                            $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$reserve_lot['id']]);
                        } else {
                            $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$added_qty, $reserve_lot['id']]);
                        }
                    }
                }

                // Ajout dans le sac actuel (Fusion si date identique, ou création)
                $stmt_check = $pdo->prepare("SELECT id FROM stocks WHERE materiel_id = ? AND lieu_id = ? AND IFNULL(date_peremption, '') = IFNULL(?, '')");
                $stmt_check->execute([$mat_id, $lieu_id, $added_date]);
                $stock_existant = $stmt_check->fetch();

                if ($stock_existant) {
                    $pdo->prepare("UPDATE stocks SET quantite = quantite + ? WHERE id = ?")->execute([$added_qty, $stock_existant['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption) VALUES (?, ?, ?, ?)")
                        ->execute([$mat_id, $lieu_id, $added_qty, $added_date]);
                }
            }
        }

        // C. On verrouille le sac pour cet événement
        $pdo->prepare("UPDATE evenements_lieux SET statut = 'valide' WHERE evenement_id = ? AND lieu_id = ?")->execute([$event_id, $lieu_id]);

        // D. On trace l'action dans l'historique
        $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")
            ->execute([$_SESSION['username'], "A vérifié et scellé le sac '" . $lieu['nom'] . "' pour le DPS '" . $event['nom'] . "'"]);

        $pdo->commit();
        $_SESSION['flash_success'] = "✅ Le sac a été vérifié, mis à jour et scellé avec succès !";
        header("Location: remplissage.php?action=view_event&id=" . $event_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// ==========================================
// 4. RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// ==========================================

// A. Récupération du contenu du sac
$stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.id AS materiel_id, m.nom AS materiel_nom, c.nom AS categorie_nom 
                              FROM stocks s 
                              JOIN materiels m ON s.materiel_id = m.id 
                              JOIN categories c ON m.categorie_id = c.id 
                              WHERE s.lieu_id = ? 
                              ORDER BY c.nom, m.nom");
$stmt_stocks->execute([$lieu_id]);
$stocks = $stmt_stocks->fetchAll();

$stocks_par_categorie = [];
foreach ($stocks as $s) {
    $stocks_par_categorie[$s['categorie_nom']][] = $s;
}

// B. Récupération des lots disponibles en Réserve
$stmt_reserves = $pdo->query("SELECT s.id as reserve_stock_id, s.materiel_id, s.quantite, s.date_peremption, l.nom as lieu_nom 
                              FROM stocks s 
                              JOIN lieux_stockage l ON s.lieu_id = l.id 
                              WHERE l.est_reserve = 1 AND s.quantite > 0 
                              ORDER BY s.date_peremption ASC");
$toutes_les_reserves = $stmt_reserves->fetchAll();
$reserves_par_materiel = [];
foreach ($toutes_les_reserves as $res) {
    $reserves_par_materiel[$res['materiel_id']][] = $res;
}

$date_event_timestamp = strtotime($event['date_evenement']);

require_once 'includes/header.php';
?>

<div class="white-box"
    style="position: sticky; top: 0; z-index: 100; border-bottom: 3px solid #d32f2f; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="remplissage.php?action=view_event&id=<?php echo $event_id; ?>"
                style="color: #666; text-decoration: none; font-size: 14px;">⬅ Annuler et retourner au DPS</a>
            <h2 style="margin: 5px 0 0 0; color: #333;">📋 Vérification : <?php echo htmlspecialchars($lieu['nom']); ?>
            </h2>
            <p style="margin: 5px 0 0 0; color: #d32f2f; font-weight: bold;">Pour le DPS :
                <?php echo htmlspecialchars($event['nom']); ?> (<?php echo date('d/m/Y', $date_event_timestamp); ?>)
            </p>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div
        style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<form id="form-verification" method="POST"
    action="verification_sac.php?event_id=<?php echo $event_id; ?>&lieu_id=<?php echo $lieu_id; ?>">
    <input type="hidden" name="valider_verification" value="1">

    <div
        style="background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 5px solid #ef6c00; margin-bottom: 25px;">
        <strong>Consignes de vérification :</strong>
        <ul style="margin: 5px 0 0 0; padding-left: 20px; font-size: 14px; color: #555;">
            <li>S'il manque des objets, vous pourrez choisir de les recompléter depuis une réserve ou d'acquitter
                l'écart.</li>
            <li><strong>Sécurité :</strong> Si un objet n'est plus présent (quantité 0) ou s'il sera périmé le jour du
                DPS, il est <span style="color: #d32f2f; font-weight: bold;">obligatoire</span> de le recompléter.
                L'acquittement sera bloqué.</li>
        </ul>
    </div>

    <?php if (empty($stocks_par_categorie)): ?>
        <p style="text-align: center; color: #999;">Ce sac est actuellement vide dans la base de données.</p>
    <?php else: ?>
        <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
            <div style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                <h3 class="category-header"
                    style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>; margin:0; padding:10px 15px;">
                    <?php echo htmlspecialchars($categorie); ?>
                </h3>

                <table class="table-manager" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th style="padding: 10px; text-align: left; width: 40%;">Matériel</th>
                            <th style="padding: 10px; text-align: center; width: 20%;">Péremption</th>
                            <th style="padding: 10px; text-align: center; width: 20%;">Théorique</th>
                            <th style="padding: 10px; text-align: center; width: 20%;">Validé (Bon état)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $art):
                            $sid = $art['stock_id'];
                            $theo = $art['quantite'];
                            $mat_id = $art['materiel_id'];
                            $est_perime = false;
                            $texte_peremption = '-';

                            // Vérification de la péremption
                            if ($art['date_peremption']) {
                                $date_art_timestamp = strtotime($art['date_peremption']);
                                if ($date_art_timestamp < $date_event_timestamp) {
                                    $est_perime = true;
                                }
                                $texte_peremption = date('d/m/Y', $date_art_timestamp);
                            }

                            $lots_dispos = $reserves_par_materiel[$mat_id] ?? [];
                            ?>
                            <tr class="item-row" data-stock-id="<?php echo $sid; ?>" data-theo="<?php echo $theo; ?>"
                                data-nom="<?php echo htmlspecialchars($art['materiel_nom']); ?>"
                                data-perime="<?php echo $est_perime ? 'true' : 'false'; ?>"
                                style="border-bottom: 1px solid #eee; <?php echo $est_perime ? 'background-color: #ffebee;' : ''; ?>">

                                <td style="padding: 10px; font-weight: 500; color: #333;">
                                    <?php echo htmlspecialchars($art['materiel_nom']); ?>
                                    <?php if ($est_perime): ?>
                                        <div style="font-size: 11px; color: #d32f2f; font-weight: bold; margin-top: 5px;">⚠️ Périmé le
                                            jour du poste (À remplacer)</div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px; text-align: center; font-size: 13px; color: #666;">
                                    <?php echo $texte_peremption; ?>
                                </td>

                                <td style="padding: 10px; text-align: center; font-size: 16px; color: #999; font-weight: bold;">
                                    <?php echo $theo; ?>
                                </td>

                                <td style="padding: 10px; text-align: center;">
                                    <input type="number" min="0" name="stock[<?php echo $sid; ?>][counted]" class="input-counted"
                                        value="<?php echo $est_perime ? 0 : $theo; ?>" oninput="checkDifference(this)" <?php echo $est_perime ? 'readonly' : ''; ?>
                                        style="width: 70px; padding: 8px; font-size: 16px; text-align: center; border: 2px solid <?php echo $est_perime ? '#f44336' : '#ccc'; ?>; border-radius: 4px; outline: none; <?php echo $est_perime ? 'background-color: #ffcdd2;' : ''; ?>">
                                    <input type="hidden" name="stock[<?php echo $sid; ?>][materiel_id]"
                                        value="<?php echo $mat_id; ?>">
                                </td>
                            </tr>

                            <tr class="refill-row" id="refill-<?php echo $sid; ?>"
                                style="display: <?php echo $est_perime ? 'table-row' : 'none'; ?>; background-color: #fdfaf6; border-bottom: 2px solid #ddd;">
                                <td colspan="4" style="padding: 10px 10px 10px 40px; border-left: 4px solid #ef6c00;">
                                    <span style="color: #ef6c00; font-weight: bold; font-size: 14px;">
                                        ↳ Il manque <span class="missing-display"><?php echo $est_perime ? $theo : '0'; ?></span>
                                        unité(s).
                                    </span>
                                    <div
                                        style="display: flex; align-items: center; gap: 10px; margin-top: 10px; background: white; padding: 10px; border-radius: 4px; border: 1px solid #ccc; flex-wrap: wrap;">

                                        <label style="font-size: 12px; font-weight:bold; color:#333;">Action :</label>
                                        <select name="stock[<?php echo $sid; ?>][reserve_stock_id]" class="input-reserve-lot"
                                            style="padding: 6px; border: 1px solid #aaa; border-radius: 3px; max-width: 350px;"
                                            onchange="updateMaxQty(this)">

                                            <option value="acquitter" class="opt-acquitter"
                                                style="<?php echo $est_perime ? 'display:none;' : ''; ?>">✅ Acquitter l'écart
                                                (Laisser tel quel)</option>
                                            <option value="" class="opt-separator" <?php echo $est_perime ? 'selected' : 'disabled'; ?>>-- Recompléter avec le lot suivant --</option>

                                            <?php foreach ($lots_dispos as $res):
                                                $date_format = $res['date_peremption'] ? date('d/m/Y', strtotime($res['date_peremption'])) : 'Aucune';
                                                $label = htmlspecialchars($res['lieu_nom']) . " | Pér: " . $date_format . " | Dispo: " . $res['quantite'];
                                                ?>
                                                <option value="<?php echo $res['reserve_stock_id']; ?>"
                                                    data-max="<?php echo $res['quantite']; ?>">
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="manual">Saisie manuelle externe</option>
                                        </select>

                                        <div class="qty-container"
                                            style="display: <?php echo $est_perime ? 'flex' : 'none'; ?>; align-items: center; gap: 10px; margin-left: 10px;">
                                            <label style="font-size: 12px; font-weight:bold; color:#333;">Qté ajoutée :</label>
                                            <input type="number" name="stock[<?php echo $sid; ?>][added_qty]"
                                                class="input-added-qty" value="0" min="0" oninput="checkMaxQty(this)"
                                                style="width: 60px; padding: 6px; text-align: center; border: 1px solid #aaa; border-radius: 3px;">
                                        </div>

                                        <div class="manual-date-container"
                                            style="display: none; align-items: center; gap: 10px; margin-left: 10px;">
                                            <label style="font-size: 12px; font-weight:bold; color:#333;">Nouv. Pér. :</label>
                                            <input type="date" name="stock[<?php echo $sid; ?>][added_date]"
                                                class="input-added-date"
                                                style="padding: 6px; border: 1px solid #aaa; border-radius: 3px;">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="text-align: center; margin: 40px 0; padding-bottom: 40px;">
        <button type="button" onclick="validerFormulaireVerification()" class="carte-animee"
            style="background-color: #2e7d32; color: white; border: none; padding: 15px 40px; font-size: 18px; font-weight: bold; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 10px rgba(46,125,50,0.3);">
            🔒 Valider et Sceller le sac
        </button>
    </div>
</form>

<script>
    // 1. Fonction appelée à chaque fois qu'on modifie le comptage
    function checkDifference(input) {
        const row = input.closest('.item-row');
        const stockId = row.getAttribute('data-stock-id');
        const theo = parseInt(row.getAttribute('data-theo'));
        const estPerime = row.getAttribute('data-perime') === 'true';
        let counted = parseInt(input.value);
        if (isNaN(counted)) counted = 0;

        const refillRow = document.getElementById('refill-' + stockId);
        const missingDisplay = refillRow.querySelector('.missing-display');
        const selectLot = refillRow.querySelector('.input-reserve-lot');
        const optAcquitter = refillRow.querySelector('.opt-acquitter');

        if (counted < theo) {
            const missing = theo - counted;
            refillRow.style.display = 'table-row';
            missingDisplay.textContent = missing;

            // Règles d'Acquittement
            if (counted === 0 || estPerime) {
                optAcquitter.style.display = 'none'; // Interdit d'acquitter un sac vide
                if (selectLot.value === 'acquitter') {
                    selectLot.value = ''; // Force à choisir un lot
                }
            } else {
                optAcquitter.style.display = 'block'; // Autorisé
                if (!selectLot.value) {
                    selectLot.value = 'acquitter';
                }
            }

            input.style.borderColor = '#ef6c00';
            updateMaxQty(selectLot); // Mets à jour l'interface (cache ou affiche les quantités)

        } else {
            refillRow.style.display = 'none';
            selectLot.value = 'acquitter';
            refillRow.querySelector('.input-added-qty').value = 0;
            input.style.borderColor = '#4caf50';
        }
    }

    // 2. Gestion de l'affichage des dates et quantités max
    function updateMaxQty(selectElement) {
        const container = selectElement.closest('.refill-row');
        const qtyContainer = container.querySelector('.qty-container');
        const manualDateContainer = container.querySelector('.manual-date-container');
        const addedQtyInput = container.querySelector('.input-added-qty');

        const stockId = container.id.replace('refill-', '');
        const row = document.querySelector(`.item-row[data-stock-id="${stockId}"]`);
        const theo = parseInt(row.getAttribute('data-theo'));
        const counted = parseInt(row.querySelector('.input-counted').value) || 0;
        const missing = theo - counted;

        if (selectElement.value === 'acquitter') {
            qtyContainer.style.display = 'none';
            manualDateContainer.style.display = 'none';
            addedQtyInput.value = 0;
        } else if (selectElement.value === 'manual') {
            qtyContainer.style.display = 'flex';
            manualDateContainer.style.display = 'flex';
            addedQtyInput.removeAttribute('max');
            if (parseInt(addedQtyInput.value) === 0) addedQtyInput.value = missing;
        } else if (selectElement.value === '') {
            qtyContainer.style.display = 'none';
            manualDateContainer.style.display = 'none';
            addedQtyInput.value = 0;
        } else {
            qtyContainer.style.display = 'flex';
            manualDateContainer.style.display = 'none';
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const max = selectedOption.getAttribute('data-max');
            addedQtyInput.setAttribute('max', max);

            if (parseInt(addedQtyInput.value) === 0) addedQtyInput.value = missing;
            if (parseInt(addedQtyInput.value) > parseInt(max)) addedQtyInput.value = max;
        }
        selectElement.style.borderColor = '#aaa'; // Enlève l'erreur visuelle
    }

    function checkMaxQty(inputElement) {
        const max = inputElement.getAttribute('max');
        if (max && parseInt(inputElement.value) > parseInt(max)) {
            inputElement.value = max;
            alert("Attention : Le lot sélectionné ne contient que " + max + " unités.");
        }
    }

    // Initialisation au chargement
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.input-counted').forEach(input => {
            checkDifference(input);
        });
    });

    // 3. Validation finale avant scellement
    function validerFormulaireVerification() {
        let sousEffectif = false;
        let nbLignesVides = 0;
        let erreurLotManquant = false;
        let blocageZero = false;
        let messageBlocage = "";

        document.querySelectorAll('.item-row').forEach(row => {
            const stockId = row.getAttribute('data-stock-id');
            const theo = parseInt(row.getAttribute('data-theo'));
            const nomMat = row.getAttribute('data-nom');
            const estPerime = row.getAttribute('data-perime') === 'true';
            const counted = parseInt(row.querySelector('.input-counted').value) || 0;

            const refillRow = document.getElementById('refill-' + stockId);
            const added = parseInt(refillRow.querySelector('.input-added-qty').value) || 0;
            const selectLot = refillRow.querySelector('.input-reserve-lot');

            // Règle stricte : 0 ou périmé interdit d'être acquitté
            if (counted === 0 && added === 0) {
                blocageZero = true;
                messageBlocage += `- ${nomMat}\n`;
            }

            // Calcul du sous-effectif assumé
            if ((counted + added) < theo) {
                sousEffectif = true;
                nbLignesVides++;
            }

            // Si on recomplète, il faut un lot
            if (added > 0 && (selectLot.value === "" || selectLot.value === "acquitter")) {
                erreurLotManquant = true;
                selectLot.style.borderColor = '#f44336';
            }
        });

        if (blocageZero) {
            alert("⚠️ IMPOSSIBLE DE SCELLER.\nLes matériels suivants sont indispensables (manquants ou périmés) et n'ont pas été remplacés :\n\n" + messageBlocage + "\nVous devez obligatoirement sélectionner un lot pour les recompléter.");
            return;
        }

        if (erreurLotManquant) {
            alert("⚠️ Veuillez choisir un lot de réserve pour le matériel que vous rajoutez (encadré en rouge).");
            return;
        }

        if (sousEffectif) {
            if (!confirm(`⚠️ ATTENTION : Le sac n'est pas rempli à son niveau théorique (manque assumé sur ${nbLignesVides} ligne(s)).\nLes écarts seront acquittés en base de données.\n\nÊtes-vous sûr de vouloir sceller un sac incomplet pour ce DPS ?`)) {
                return;
            }
        } else {
            if (!confirm("Tout est en règle. Confirmez-vous le scellement définitif du sac ?")) {
                return;
            }
        }

        document.getElementById('form-verification').submit();
    }
</script>

<?php require_once 'includes/footer.php'; ?>