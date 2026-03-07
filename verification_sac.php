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

            // B. Traitement du nouveau lot rajouté depuis la réserve
            if ($added_qty > 0) {
                // On vérifie s'il n'existe pas DÉJÀ un lot avec cette même date exacte pour ce matériel dans ce sac
                $stmt_check = $pdo->prepare("SELECT id, quantite FROM stocks WHERE materiel_id = ? AND lieu_id = ? AND IFNULL(date_peremption, '') = IFNULL(?, '')");
                $stmt_check->execute([$mat_id, $lieu_id, $added_date]);
                $stock_existant = $stmt_check->fetch();

                if ($stock_existant) {
                    // Les dates sont identiques : on fusionne pour garder la base propre
                    $pdo->prepare("UPDATE stocks SET quantite = quantite + ? WHERE id = ?")->execute([$added_qty, $stock_existant['id']]);
                } else {
                    // La date est différente (ou nouvelle) : on crée une NOUVELLE ligne indépendante !
                    $pdo->prepare("INSERT INTO stocks (materiel_id, lieu_id, quantite, date_peremption) VALUES (?, ?, ?, ?)")
                        ->execute([$mat_id, $lieu_id, $added_qty, $added_date]);
                }
                // B. Traitement du nouveau lot rajouté depuis la réserve
                if ($added_qty > 0) {
                    // ... (Le code existant qui fait INSERT et UPDATE dans le sac) ...

                    // NOUVEAU : On déduit automatiquement ce qu'on vient de prendre des réserves !
                    deduireDeLaReserve($pdo, $mat_id, $added_qty);
                }
            }
        }

        // C. On verrouille le sac pour cet événement
        $pdo->prepare("UPDATE evenements_lieux SET statut = 'valide' WHERE evenement_id = ? AND lieu_id = ?")->execute([$event_id, $lieu_id]);

        // D. On trace l'action dans l'historique
        $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, datetime('now', 'localtime'))")
            ->execute([$_SESSION['username'], "A vérifié et scellé le sac '" . $lieu['nom'] . "' pour le DPS '" . $event['nom'] . "'"]);

        $pdo->commit();
        $_SESSION['flash_success'] = "✅ Le sac a été vérifié, recomplété et scellé avec succès !";
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
// Attention : on récupère aussi le materiel_id (m.id) pour pouvoir créer les nouveaux lots
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
            <li>Inscrivez dans la case <strong>"Validé"</strong> le nombre d'objets en bon état présents dans le sac.
            </li>
            <li>S'il en manque, un menu de remplissage apparaîtra pour indiquer ce que vous rajoutez depuis la réserve.
            </li>
            <li>Les objets en <span style="color: #d32f2f; font-weight: bold;">rouge</span> seront périmés le jour du
                DPS. Leur compte a été mis à 0, merci de les jeter et de les remplacer.</li>
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
                            ?>
                            <tr class="item-row" data-stock-id="<?php echo $sid; ?>" data-theo="<?php echo $theo; ?>"
                                style="border-bottom: 1px solid #eee; <?php echo $est_perime ? 'background-color: #ffebee;' : ''; ?>">
                                <td style="padding: 10px; font-weight: 500; color: #333;">
                                    <?php echo htmlspecialchars($art['materiel_nom']); ?>
                                    <?php if ($est_perime): ?>
                                        <div style="font-size: 11px; color: #d32f2f; font-weight: bold; margin-top: 5px;">⚠️ Périmé le
                                            jour du poste</div>
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
                                        value="<?php echo $est_perime ? 0 : $theo; ?>" oninput="checkDifference(this)"
                                        style="width: 70px; padding: 8px; font-size: 16px; text-align: center; border: 2px solid #ccc; border-radius: 4px; outline: none;">
                                    <input type="hidden" name="stock[<?php echo $sid; ?>][materiel_id]"
                                        value="<?php echo $art['materiel_id']; ?>">
                                </td>
                            </tr>

                            <tr class="refill-row" id="refill-<?php echo $sid; ?>"
                                style="display: none; background-color: #fdfaf6; border-bottom: 2px solid #ddd;">
                                <td colspan="4" style="padding: 10px 10px 10px 40px; border-left: 4px solid #ef6c00;">
                                    <span style="color: #ef6c00; font-weight: bold; font-size: 14px;">
                                        ↳ Il manque <span class="missing-display">0</span> unité(s). <span
                                            style="color:#666;">Remplissage depuis la réserve :</span>
                                    </span>
                                    <div
                                        style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px; background: white; padding: 5px 10px; border-radius: 4px; border: 1px solid #ccc;">
                                        <label style="font-size: 12px; font-weight:bold; color:#333;">Qté ajoutée :</label>
                                        <input type="number" name="stock[<?php echo $sid; ?>][added_qty]" class="input-added-qty"
                                            value="0" min="0"
                                            style="width: 60px; padding: 4px; text-align: center; border: 1px solid #aaa; border-radius: 3px;">

                                        <label style="font-size: 12px; font-weight:bold; color:#333; margin-left: 10px;">Nouv.
                                            Péremption :</label>
                                        <input type="date" name="stock[<?php echo $sid; ?>][added_date]" class="input-added-date"
                                            style="padding: 4px; border: 1px solid #aaa; border-radius: 3px;">
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
    // 1. Fonction appelée à chaque fois qu'on tape dans une case "Validé"
    function checkDifference(input) {
        const row = input.closest('.item-row');
        const stockId = row.getAttribute('data-stock-id');
        const theo = parseInt(row.getAttribute('data-theo'));
        let counted = parseInt(input.value);
        if (isNaN(counted)) counted = 0;

        const refillRow = document.getElementById('refill-' + stockId);
        const missingDisplay = refillRow.querySelector('.missing-display');
        const addedQtyInput = refillRow.querySelector('.input-added-qty');

        // S'il en manque
        if (counted < theo) {
            const missing = theo - counted;
            refillRow.style.display = 'table-row';
            missingDisplay.textContent = missing;

            // On pré-remplit la quantité à rajouter pour faire gagner du temps
            addedQtyInput.value = missing;
            input.style.borderColor = '#ef6c00'; // Met la case du haut en orange
        }
        // Si le compte est bon (ou supérieur)
        else {
            refillRow.style.display = 'none';
            addedQtyInput.value = 0;
            input.style.borderColor = '#4caf50'; // Met la case du haut en vert
        }
    }

    // 2. Initialisation au chargement de la page (pour faire apparaître les alertes des produits périmés)
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.input-counted').forEach(input => {
            checkDifference(input);
        });
    });

    // 3. Validation finale avant envoi
    function validerFormulaireVerification() {
        let sousEffectif = false;
        let nbLignesVides = 0;

        // On vérifie toutes les lignes pour voir s'il y a des manques
        document.querySelectorAll('.item-row').forEach(row => {
            const stockId = row.getAttribute('data-stock-id');
            const theo = parseInt(row.getAttribute('data-theo'));
            const counted = parseInt(row.querySelector('.input-counted').value) || 0;

            const refillRow = document.getElementById('refill-' + stockId);
            const added = parseInt(refillRow.querySelector('.input-added-qty').value) || 0;

            if ((counted + added) < theo) {
                sousEffectif = true;
                nbLignesVides++;
            }
        });

        // Avertissement intelligent
        if (sousEffectif) {
            if (!confirm(`⚠️ ATTENTION : Le sac n'est pas rempli à son niveau théorique (manque sur ${nbLignesVides} ligne(s)).\n\nÊtes-vous sûr de vouloir sceller un sac incomplet pour ce DPS ?`)) {
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