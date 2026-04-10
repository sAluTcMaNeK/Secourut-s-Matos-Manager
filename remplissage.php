<?php
// remplissage.php (Tableau de bord des DPS / Vérifications)
require_once 'includes/auth.php';
require_once 'config/db.php';
$peut_editer = $peut_editer_matos;

// Création automatique des tables pour les Lots (Rétrocompatibilité)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lots (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS lots_lieux (lot_id INT NOT NULL, lieu_id INT NOT NULL, PRIMARY KEY (lot_id, lieu_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lots (id INTEGER PRIMARY KEY AUTOINCREMENT, nom VARCHAR(255) NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS lots_lieux (lot_id INTEGER NOT NULL, lieu_id INTEGER NOT NULL, PRIMARY KEY (lot_id, lieu_id))");
    } catch (PDOException $e2) {
    }
}

$action = $_GET['action'] ?? 'dashboard';

// ==========================================
// 1. TRAITEMENT DES ACTIONS (CRÉER, MODIFIER, SUPPRIMER)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- VÉRIFICATION DU JETON CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité.</div>");
    }

    // SÉCURITÉ : Seuls les Admins et les Opérationnels peuvent faire ces actions
    if (!$peut_gerer_dps) {
        $_SESSION['flash_error'] = "🛑 Action refusée : Vous n'avez pas les droits pour gérer les DPS ou les Lots.";
        header("Location: remplissage.php");
        exit;
    }

    $post_action = $_POST['action'] ?? '';

    try {
        if ($post_action === 'delete_event') {
            $event_id = (int) $_POST['event_id'];
            $nom_event = $pdo->query("SELECT nom FROM evenements WHERE id = $event_id")->fetchColumn();
            $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$event_id]);
            $pdo->prepare("DELETE FROM evenements_lieux WHERE evenement_id = ?")->execute([$event_id]);
            logAction($pdo, "A supprimé le DPS : $nom_event");
            $_SESSION['flash_success'] = "🗑️ Le DPS a été supprimé.";
        } elseif ($post_action === 'create_event') {
            $nom_event = trim($_POST['nom_evenement']);
            $date_event = $_POST['date_evenement'];
            $sacs_selectionnes = $_POST['lieux'] ?? [];

            if (!empty($nom_event) && !empty($date_event) && !empty($sacs_selectionnes)) {
                $stmt = $pdo->prepare("INSERT INTO evenements (nom, date_evenement, statut, cree_le) VALUES (?, ?, 'a_verifier', NOW())");
                $stmt->execute([$nom_event, $date_event]);
                $event_id = $pdo->lastInsertId();

                $stmt_liaison = $pdo->prepare("INSERT INTO evenements_lieux (evenement_id, lieu_id, statut) VALUES (?, ?, 'en_attente')");
                foreach ($sacs_selectionnes as $lieu_id) {
                    $stmt_liaison->execute([$event_id, $lieu_id]);
                }
                $_SESSION['flash_success'] = "✅ Le DPS '$nom_event' a été créé.";
                logAction($pdo, "A créé le DPS : $nom_event");
            } else {
                $_SESSION['flash_error'] = "⚠️ Veuillez remplir tous les champs et sélectionner au moins un sac.";
            }
        } elseif ($post_action === 'edit_event') {
            $event_id = (int) $_POST['event_id'];
            $nom_event = trim($_POST['nom_evenement']);
            $date_event = $_POST['date_evenement'];
            $sacs_selectionnes = $_POST['lieux'] ?? [];

            if (!empty($nom_event) && !empty($date_event) && !empty($sacs_selectionnes)) {
                $pdo->prepare("UPDATE evenements SET nom = ?, date_evenement = ? WHERE id = ?")->execute([$nom_event, $date_event, $event_id]);

                $stmt_existants = $pdo->prepare("SELECT lieu_id FROM evenements_lieux WHERE evenement_id = ?");
                $stmt_existants->execute([$event_id]);
                $sacs_existants = $stmt_existants->fetchAll(PDO::FETCH_COLUMN);

                $sacs_a_ajouter = array_diff($sacs_selectionnes, $sacs_existants);
                $sacs_a_retirer = array_diff($sacs_existants, $sacs_selectionnes);

                if (!empty($sacs_a_retirer)) {
                    $placeholders = implode(',', array_fill(0, count($sacs_a_retirer), '?'));
                    $stmt_del = $pdo->prepare("DELETE FROM evenements_lieux WHERE evenement_id = ? AND lieu_id IN ($placeholders)");
                    $stmt_del->execute(array_merge([$event_id], $sacs_a_retirer));
                }

                if (!empty($sacs_a_ajouter)) {
                    $stmt_add = $pdo->prepare("INSERT INTO evenements_lieux (evenement_id, lieu_id, statut) VALUES (?, ?, 'en_attente')");
                    foreach ($sacs_a_ajouter as $l_id) {
                        $stmt_add->execute([$event_id, $l_id]);
                    }
                }
                $_SESSION['flash_success'] = "✏️ Le DPS a été mis à jour.";
                logAction($pdo, "A modifié le DPS : $nom_event");
            } else {
                $_SESSION['flash_error'] = "⚠️ La modification a échoué : des champs étaient vides.";
            }
        } elseif ($post_action === 'create_lot') {
            $nom_lot = trim($_POST['nom_lot']);
            $sacs_lot = $_POST['sacs_lot'] ?? [];

            if (!empty($nom_lot) && !empty($sacs_lot)) {
                $pdo->prepare("INSERT INTO lots (nom) VALUES (?)")->execute([$nom_lot]);
                $lot_id = $pdo->lastInsertId();

                $stmt_link = $pdo->prepare("INSERT INTO lots_lieux (lot_id, lieu_id) VALUES (?, ?)");
                foreach ($sacs_lot as $l_id) {
                    $stmt_link->execute([$lot_id, $l_id]);
                }
                $_SESSION['flash_success'] = "✅ Le Lot '$nom_lot' a été créé.";
                logAction($pdo, "A créé le lot : $nom_lot");
            } else {
                $_SESSION['flash_error'] = "⚠️ Veuillez donner un nom et sélectionner des sacs pour le lot.";
            }
        } elseif ($post_action === 'delete_lot') {
            $lot_id = (int) $_POST['lot_id'];
            $nom_lot = $pdo->query("SELECT nom FROM lots WHERE id = $lot_id")->fetchColumn();

            $pdo->prepare("DELETE FROM lots WHERE id = ?")->execute([$lot_id]);
            $pdo->prepare("DELETE FROM lots_lieux WHERE lot_id = ?")->execute([$lot_id]);

            logAction($pdo, "A supprimé le lot : $nom_lot");
            $_SESSION['flash_success'] = "🗑️ Lot supprimé.";
        }

    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "❌ Erreur base de données : " . $e->getMessage();
    }
    header("Location: remplissage.php");
    exit;
}

require_once 'includes/header.php';

// ==========================================
// VUE : TABLEAU DE BORD (Liste des événements)
// ==========================================
if ($action === 'dashboard') {
    $tous_les_sacs = $pdo->query("SELECT id, nom, icone FROM lieux_stockage WHERE est_reserve = 0 ORDER BY nom")->fetchAll();
    $evenements = $pdo->query("SELECT * FROM evenements ORDER BY date_evenement ASC")->fetchAll();

    $lots = $pdo->query("SELECT * FROM lots ORDER BY nom")->fetchAll();
    $lots_sacs = [];
    foreach ($lots as $lot) {
        $stmt_ls = $pdo->prepare("SELECT lieu_id FROM lots_lieux WHERE lot_id = ?");
        $stmt_ls->execute([$lot['id']]);
        $lots_sacs[$lot['id']] = $stmt_ls->fetchAll(PDO::FETCH_COLUMN);
    }
    ?>

    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">

        <?php if ($peut_gerer_dps): ?>
            <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 20px;">
                <div class="white-box" style="margin: 0;">
                    <h2 id="form_titre"
                        style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🚑
                        Planifier un DPS</h2>
                    <form action="" method="POST" id="form_dps">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_event" id="form_action">
                        <input type="hidden" name="event_id" value="" id="form_event_id">

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Nom de
                                l'événement</label>
                            <input type="text" name="nom_evenement" id="input_nom" required
                                placeholder="Ex: DPS Intégration UTC" class="input-field">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Date du
                                poste</label>
                            <input type="date" name="date_evenement" id="input_date" required class="input-field">
                        </div>

                        <?php if (!empty($lots)): ?>
                            <div style="margin-bottom: 15px;">
                                <label
                                    style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px; color: #1976D2;">📦
                                    Sélection rapide par Lot</label>
                                <div
                                    style="background: #e3f2fd; padding: 10px; border-radius: 4px; border: 1px solid #bbdefb; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php foreach ($lots as $lot): ?>
                                        <label
                                            style="cursor: pointer; font-size: 13px; font-weight: bold; color: #0d47a1; background: rgba(25, 118, 210, 0.1); padding: 4px 8px; border-radius: 4px;">
                                            <input type="checkbox" class="checkbox-lot"
                                                onchange='toggleLot(this, <?php echo json_encode($lots_sacs[$lot['id']]); ?>)'>
                                            <?php echo htmlspecialchars($lot['nom']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Sacs à
                                préparer</label>
                            <div
                                style="background: #f4f7f6; padding: 10px; border-radius: 4px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">
                                <?php foreach ($tous_les_sacs as $sac): ?>
                                    <label style="display: block; padding: 5px 0; cursor: pointer; border-bottom: 1px solid #eee;">
                                        <input type="checkbox" name="lieux[]" class="checkbox-sac"
                                            value="<?php echo $sac['id']; ?>">
                                        <?php echo ($sac['icone'] ?: '🎒') . ' ' . htmlspecialchars($sac['nom']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" id="btn_submit"
                                style="flex: 2; padding: 12px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Créer
                                la fiche</button>
                            <button type="button" id="btn_cancel" onclick="annulerEdition()"
                                style="flex: 1; padding: 12px; background-color: #ccc; color: #333; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: none;">Annuler</button>
                        </div>
                    </form>
                </div>

                <div class="white-box" style="margin: 0; border-top: 5px solid #1976D2;">
                    <h2 style="margin-top: 0; color: #1976D2; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📦 Gérer
                        les Lots types</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_lot">

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 5px;">Créer un
                                nouveau lot :</label>
                            <input type="text" name="nom_lot" required placeholder="Nom du lot (ex: Dispositif Plage)"
                                class="input-field mb-10">

                            <div
                                style="background: #fafafa; padding: 10px; border-radius: 4px; border: 1px solid #ccc; max-height: 150px; overflow-y: auto; margin-bottom: 10px;">
                                <?php foreach ($tous_les_sacs as $sac): ?>
                                    <label
                                        style="display: block; font-size: 12px; padding: 3px 0; cursor: pointer; border-bottom: 1px solid #eee;">
                                        <input type="checkbox" name="sacs_lot[]" value="<?php echo $sac['id']; ?>">
                                        <?php echo ($sac['icone'] ?: '🎒') . ' ' . htmlspecialchars($sac['nom']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-sm"
                                style="background-color: #1976D2; color: white; width: 100%;">💾 Enregistrer le lot</button>
                        </div>
                    </form>

                    <?php if (!empty($lots)): ?>
                        <h4 style="margin: 15px 0 5px 0; color: #333;">Lots existants :</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($lots as $lot): ?>
                                <li
                                    style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 5px 0; font-size: 13px;">
                                    <span>📦 <strong><?php echo htmlspecialchars($lot['nom']); ?></strong> <span
                                            style="color:#888;">(<?php echo count($lots_sacs[$lot['id']]); ?> sacs)</span></span>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Supprimer ce lot ?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete_lot">
                                        <input type="hidden" name="lot_id" value="<?php echo $lot['id']; ?>">
                                        <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer;"
                                            title="Supprimer">🗑️</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="white-box" style="flex: 2; min-width: 400px; margin: 0;">
            <h2 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📋 DPS et
                Vérifications</h2>

            <?php if (empty($evenements)): ?>
                <p style="text-align: center; color: #999; font-style: italic; padding: 20px;">Aucun événement programmé pour le
                    moment.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($evenements as $ev):
                        $stmt_progression = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides FROM evenements_lieux WHERE evenement_id = ?");
                        $stmt_progression->execute([$ev['id']]);
                        $prog = $stmt_progression->fetch();

                        $total = $prog['total'];
                        $valides = $prog['valides'] ?: 0;
                        $est_termine = ($total > 0 && $total == $valides);

                        $couleur_bordure = $est_termine ? '#4caf50' : '#f39c12';
                        $statut_texte = $est_termine ? '✅ Vérification effectuée' : '⏳ Vérification à effectuer';

                        $stmt_ids_sacs = $pdo->prepare("SELECT lieu_id FROM evenements_lieux WHERE evenement_id = ?");
                        $stmt_ids_sacs->execute([$ev['id']]);
                        $sacs_json = json_encode($stmt_ids_sacs->fetchAll(PDO::FETCH_COLUMN));
                        ?>
                        <div
                            style="border: 1px solid #e0e0e0; border-left: 5px solid <?php echo $couleur_bordure; ?>; border-radius: 6px; padding: 15px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <h3 style="margin: 0; color: #333; font-size: 18px;"><?php echo htmlspecialchars($ev['nom']); ?>
                                    </h3>
                                    <div style="font-size: 13px; color: #666; margin-top: 5px;">Prévu le :
                                        <strong><?php echo date('d/m/Y', strtotime($ev['date_evenement'])); ?></strong></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 12px; font-weight: bold; color: <?php echo $couleur_bordure; ?>;">
                                        <?php echo $statut_texte; ?></div>
                                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">
                                        <?php echo $valides; ?> / <?php echo $total; ?> sacs prêts</div>
                                </div>
                            </div>

                            <div
                                style="border-top: 1px solid #eee; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 10px;">
                                    <?php if ($peut_gerer_dps): ?>
                                        <button type="button"
                                            onclick='editerDPS(<?php echo $ev['id']; ?>, <?php echo json_encode(htmlspecialchars($ev['nom'])); ?>, "<?php echo $ev['date_evenement']; ?>", <?php echo $sacs_json; ?>)'
                                            style="background: none; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight:bold; color: #333;">✏️
                                            Modifier</button>

                                        <form method="POST" action="" style="margin: 0;"
                                            onsubmit="return confirm('Supprimer définitivement ce DPS ? Toutes les données de vérification associées seront perdues.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                            <button type="submit"
                                                style="background: none; border: 1px solid #ffcdd2; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight:bold; color: #c62828;">🗑️
                                                Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div style="text-align: right;">
                                    <a href="remplissage.php?action=view_event&id=<?php echo $ev['id']; ?>" class="carte-animee"
                                        style="display: inline-block; background-color: #2c3e50; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold;">Ouvrir
                                        la fiche ➡️</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($peut_gerer_dps): ?>
        <script>
            function toggleLot(checkboxElement, sacsArray) {
                const isChecked = checkboxElement.checked;
                sacsArray.forEach(sacId => {
                    const sacCheckbox = document.querySelector(`.checkbox-sac[value="${sacId}"]`);
                    if (sacCheckbox) {
                        sacCheckbox.checked = isChecked;
                    }
                });
            }

            function editerDPS(id, nom, date, sacs) {
                document.getElementById('form_titre').innerText = '✏️ Modifier le DPS';
                document.getElementById('form_action').value = 'edit_event';
                document.getElementById('form_event_id').value = id;
                document.getElementById('input_nom').value = nom;
                document.getElementById('input_date').value = date;

                document.querySelectorAll('.checkbox-lot').forEach(cb => cb.checked = false);
                document.querySelectorAll('.checkbox-sac').forEach(cb => {
                    cb.checked = sacs.includes(parseInt(cb.value));
                });

                document.getElementById('btn_submit').innerText = 'Enregistrer les modifications';
                document.getElementById('btn_submit').style.backgroundColor = '#ef6c00';
                document.getElementById('btn_cancel').style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function annulerEdition() {
                document.getElementById('form_titre').innerText = '🚑 Planifier un DPS';
                document.getElementById('form_action').value = 'create_event';
                document.getElementById('form_event_id').value = '';
                document.getElementById('form_dps').reset();
                document.getElementById('btn_submit').innerText = 'Créer la fiche';
                document.getElementById('btn_submit').style.backgroundColor = '#d32f2f';
                document.getElementById('btn_cancel').style.display = 'none';
            }
        </script>
    <?php endif; ?>

    <?php
}

// ==========================================
// VUE : DÉTAIL D'UN ÉVÉNEMENT (Choix du sac)
// ==========================================
elseif ($action === 'view_event' && isset($_GET['id'])) {
    $event_id = (int) $_GET['id'];
    $stmt_ev = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt_ev->execute([$event_id]);
    $evenement = $stmt_ev->fetch();

    if (!$evenement)
        die("Événement introuvable.");

    $stmt_sacs = $pdo->prepare("SELECT l.id, l.nom, l.icone, el.statut FROM evenements_lieux el JOIN lieux_stockage l ON el.lieu_id = l.id WHERE el.evenement_id = ?");
    $stmt_sacs->execute([$event_id]);
    $sacs_lies = $stmt_sacs->fetchAll();
    ?>

    <div class="white-box">
        <a href="remplissage.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Retour aux événements</a>
        <h2 style="margin: 10px 0 0 0; color: #2c3e50;">Préparation : <?php echo htmlspecialchars($evenement['nom']); ?>
        </h2>
        <p style="color: #666; margin-bottom: 20px;">
            <?php echo $peut_verifier_sceller ? "Sélectionne un sac pour commencer sa vérification." : "Voici les sacs liés à ce dispositif. Vous êtes en mode consultation."; ?>
        </p>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php foreach ($sacs_lies as $sac):
                $est_valide = ($sac['statut'] === 'valide');
                // Si l'utilisateur n'a pas les droits de vérification, on l'envoie juste "voir" le sac dans lieux.php
                $lien_cible = $peut_verifier_sceller ? "verification_sac.php?event_id={$event_id}&lieu_id={$sac['id']}" : "lieux.php?id={$sac['id']}";
                $texte_lien = $peut_verifier_sceller ? '👉 Vérifier ce sac' : '👁️ Consulter ce sac';
                ?>
                <a href="<?php echo $lien_cible; ?>" class="carte-animee"
                    style="display: block; width: 200px; padding: 20px; background-color: <?php echo $est_valide ? '#e8f5e9' : 'white'; ?>; border: 2px solid <?php echo $est_valide ? '#4caf50' : '#ddd'; ?>; border-radius: 8px; text-decoration: none; color: #333; text-align: center; opacity: <?php echo $est_valide ? '0.8' : '1'; ?>;">
                    <div style="font-size: 40px; margin-bottom: 10px;">
                        <?php echo $est_valide ? '✅' : ($sac['icone'] ?: '🎒'); ?>
                    </div>
                    <strong
                        style="font-size: 16px; display: block; <?php echo $est_valide ? 'text-decoration: line-through;' : ''; ?>"><?php echo htmlspecialchars($sac['nom']); ?></strong>
                    <span
                        style="font-size: 12px; font-weight: bold; color: <?php echo $est_valide ? '#2e7d32' : ($peut_verifier_sceller ? '#d32f2f' : '#1976D2'); ?>; margin-top: 10px; display: block;">
                        <?php echo $est_valide ? 'Sac prêt et scellé' : $texte_lien; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

require_once 'includes/footer.php';
?>