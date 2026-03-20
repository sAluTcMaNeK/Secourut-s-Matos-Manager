<?php
// remplissage.php (Tableau de bord des DPS / Vérifications)
require_once 'includes/auth.php';
require_once 'config/db.php';

// --- NOUVEAU : Vérification des droits ---
$est_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$action = $_GET['action'] ?? 'dashboard';

// ==========================================
// 1. TRAITEMENT DES ACTIONS (CRÉER, MODIFIER, SUPPRIMER)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // SÉCURITÉ : Seuls les admins peuvent faire ces actions
    if (!$est_admin) {
        $_SESSION['flash_error'] = "🛑 Action refusée : Seuls les administrateurs peuvent gérer les DPS.";
        header("Location: remplissage.php");
        exit;
    }

    $post_action = $_POST['action'] ?? '';

    try {
        // --- A. SUPPRIMER UN DPS ---
        if ($post_action === 'delete_event') {
            $event_id = (int) $_POST['event_id'];

            // On récupère le nom pour l'historique
            $nom_event = $pdo->query("SELECT nom FROM evenements WHERE id = $event_id")->fetchColumn();

            // Suppression en cascade (l'événement et ses liaisons de sacs)
            $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$event_id]);
            $pdo->prepare("DELETE FROM evenements_lieux WHERE evenement_id = ?")->execute([$event_id]);
            logAction($pdo, "A supprimé le DPS : $nom_event");

            $pdo->prepare("INSERT INTO historique_actions (nom_utilisateur, action, date_action) VALUES (?, ?, NOW())")
                ->execute([$_SESSION['username'], "A supprimé le DPS : $nom_event"]);

            $_SESSION['flash_success'] = "🗑️ Le DPS a été supprimé.";
        }

        // --- B. CRÉER UN DPS ---
        elseif ($post_action === 'create_event') {
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
        }

        // --- C. MODIFIER UN DPS ---
        elseif ($post_action === 'edit_event') {
            $event_id = (int) $_POST['event_id'];
            $nom_event = trim($_POST['nom_evenement']);
            $date_event = $_POST['date_evenement'];
            $sacs_selectionnes = $_POST['lieux'] ?? [];

            if (!empty($nom_event) && !empty($date_event) && !empty($sacs_selectionnes)) {
                // 1. Mise à jour des infos de base
                $pdo->prepare("UPDATE evenements SET nom = ?, date_evenement = ? WHERE id = ?")->execute([$nom_event, $date_event, $event_id]);

                // 2. Mise à jour des sacs
                $stmt_existants = $pdo->prepare("SELECT lieu_id FROM evenements_lieux WHERE evenement_id = ?");
                $stmt_existants->execute([$event_id]);
                $sacs_existants = $stmt_existants->fetchAll(PDO::FETCH_COLUMN);

                $sacs_a_ajouter = array_diff($sacs_selectionnes, $sacs_existants);
                $sacs_a_retirer = array_diff($sacs_existants, $sacs_selectionnes);

                // Retirer les sacs décochés
                if (!empty($sacs_a_retirer)) {
                    $placeholders = implode(',', array_fill(0, count($sacs_a_retirer), '?'));
                    $stmt_del = $pdo->prepare("DELETE FROM evenements_lieux WHERE evenement_id = ? AND lieu_id IN ($placeholders)");
                    $stmt_del->execute(array_merge([$event_id], $sacs_a_retirer));
                }

                // Ajouter les nouveaux sacs cochés
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
    // CORRECTION : On sélectionne tous les lieux qui ne sont PAS des réserves
    $tous_les_sacs = $pdo->query("SELECT id, nom, icone FROM lieux_stockage WHERE est_reserve = 0 ORDER BY nom")->fetchAll();
    $evenements = $pdo->query("SELECT * FROM evenements ORDER BY date_evenement ASC")->fetchAll();
    ?>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">

        <?php if ($est_admin): ?>
            <div class="white-box" style="flex: 1; min-width: 300px; height: fit-content;">
                <h2 id="form_titre"
                    style="margin-top: 0; color: #d32f2f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🚑 Planifier
                    un DPS</h2>
                <form action="remplissage.php" method="POST" id="form_dps">
                    <input type="hidden" name="action" value="create_event" id="form_action">
                    <input type="hidden" name="event_id" value="" id="form_event_id">

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Nom de
                            l'événement</label>
                        <input type="text" name="nom_evenement" id="input_nom" required placeholder="Ex: DPS Intégration UTC"
                            class="input-field">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Date du
                            poste</label>
                        <input type="date" name="date_evenement" id="input_date" required class="input-field">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Sacs à préparer
                            (Cocher)</label>
                        <div
                            style="background: #f4f7f6; padding: 10px; border-radius: 4px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($tous_les_sacs as $sac): ?>
                                <label style="display: block; padding: 5px 0; cursor: pointer; border-bottom: 1px solid #eee;">
                                    <input type="checkbox" name="lieux[]" class="checkbox-sac" value="<?php echo $sac['id']; ?>">
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
        <?php endif; ?>

        <div class="white-box" style="flex: 2; min-width: 400px;">
            <h2 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📋 DPS et
                Vérifications</h2>

            <?php if (empty($evenements)): ?>
                <p style="text-align: center; color: #999; font-style: italic; padding: 20px;">Aucun événement programmé pour le
                    moment.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($evenements as $ev):
                        // Progression
                        $stmt_progression = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides FROM evenements_lieux WHERE evenement_id = ?");
                        $stmt_progression->execute([$ev['id']]);
                        $prog = $stmt_progression->fetch();

                        $total = $prog['total'];
                        $valides = $prog['valides'] ?: 0;
                        $est_termine = ($total > 0 && $total == $valides);

                        $couleur_bordure = $est_termine ? '#4caf50' : '#f39c12';
                        $statut_texte = $est_termine ? '✅ Vérification effectuée' : '⏳ Vérification à effectuer';

                        // Sacs liés pour l'édition
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
                                    <div style="font-size: 13px; color: #666; margin-top: 5px;">Prévu pour le :
                                        <strong><?php echo date('d/m/Y', strtotime($ev['date_evenement'])); ?></strong>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 12px; font-weight: bold; color: <?php echo $couleur_bordure; ?>;">
                                        <?php echo $statut_texte; ?>
                                    </div>
                                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">
                                        <?php echo $valides; ?> / <?php echo $total; ?> sacs prêts
                                    </div>
                                </div>
                            </div>

                            <div
                                style="border-top: 1px solid #eee; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">

                                <div style="display: flex; gap: 10px;">
                                    <?php if ($est_admin): ?>
                                        <button type="button"
                                            onclick='editerDPS(<?php echo $ev['id']; ?>, <?php echo json_encode(htmlspecialchars($ev['nom'])); ?>, "<?php echo $ev['date_evenement']; ?>", <?php echo $sacs_json; ?>)'
                                            style="background: none; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight:bold; color: #333;">✏️
                                            Modifier</button>

                                        <form method="POST" style="margin: 0;"
                                            onsubmit="return confirm('Supprimer définitivement ce DPS ? Toutes les données de vérification associées seront perdues.');">
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                            <button type="submit"
                                                style="background: none; border: 1px solid #ffcdd2; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight:bold; color: #c62828;">🗑️
                                                Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <?php if ($peut_editer): ?>
                                    <div style="text-align: right;">
                                        <a href="remplissage.php?action=view_event&id=<?php echo $ev['id']; ?>" class="carte-animee"
                                            style="display: inline-block; background-color: #2c3e50; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold;">Ouvrir
                                            la fiche ➡️</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($est_admin): ?>
        <script>
            // Fonction pour transformer le formulaire de création en formulaire d'édition
            function editerDPS(id, nom, date, sacs) {
                document.getElementById('form_titre').innerText = '✏️ Modifier le DPS';
                document.getElementById('form_action').value = 'edit_event';
                document.getElementById('form_event_id').value = id;

                document.getElementById('input_nom').value = nom;
                document.getElementById('input_date').value = date;

                // On décoche tout, puis on coche les sacs concernés
                document.querySelectorAll('.checkbox-sac').forEach(cb => {
                    cb.checked = sacs.includes(parseInt(cb.value));
                });

                document.getElementById('btn_submit').innerText = 'Enregistrer les modifications';
                document.getElementById('btn_submit').style.backgroundColor = '#ef6c00'; // Orange
                document.getElementById('btn_cancel').style.display = 'block';

                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            // Fonction pour annuler l'édition et repasser en mode création
            function annulerEdition() {
                document.getElementById('form_titre').innerText = '🚑 Planifier un DPS';
                document.getElementById('form_action').value = 'create_event';
                document.getElementById('form_event_id').value = '';

                document.getElementById('form_dps').reset();

                document.getElementById('btn_submit').innerText = 'Créer la fiche';
                document.getElementById('btn_submit').style.backgroundColor = '#d32f2f'; // Rouge
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

    // Récupérer les sacs liés
    $stmt_sacs = $pdo->prepare("SELECT l.id, l.nom, l.icone, el.statut FROM evenements_lieux el JOIN lieux_stockage l ON el.lieu_id = l.id WHERE el.evenement_id = ?");
    $stmt_sacs->execute([$event_id]);
    $sacs_lies = $stmt_sacs->fetchAll();
    ?>

    <div class="white-box">
        <a href="remplissage.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Retour aux événements</a>
        <h2 style="margin: 10px 0 0 0; color: #2c3e50;">Préparation : <?php echo htmlspecialchars($evenement['nom']); ?>
        </h2>
        <p style="color: #666; margin-bottom: 20px;">Sélectionne un sac pour commencer sa vérification. Les éléments
            périmant avant le <strong><?php echo date('d/m/Y', strtotime($evenement['date_evenement'])); ?></strong> seront
            signalés en rouge.</p>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php foreach ($sacs_lies as $sac):
                $est_valide = ($sac['statut'] === 'valide');
                ?>
                <a href="verification_sac.php?event_id=<?php echo $event_id; ?>&lieu_id=<?php echo $sac['id']; ?>"
                    class="carte-animee"
                    style="display: block; width: 200px; padding: 20px; background-color: <?php echo $est_valide ? '#e8f5e9' : 'white'; ?>; border: 2px solid <?php echo $est_valide ? '#4caf50' : '#ddd'; ?>; border-radius: 8px; text-decoration: none; color: #333; text-align: center; opacity: <?php echo $est_valide ? '0.8' : '1'; ?>;">
                    <div style="font-size: 40px; margin-bottom: 10px;">
                        <?php echo $est_valide ? '✅' : ($sac['icone'] ?: '🎒'); ?>
                    </div>
                    <strong
                        style="font-size: 16px; display: block; <?php echo $est_valide ? 'text-decoration: line-through;' : ''; ?>"><?php echo htmlspecialchars($sac['nom']); ?></strong>
                    <span
                        style="font-size: 12px; font-weight: bold; color: <?php echo $est_valide ? '#2e7d32' : '#d32f2f'; ?>; margin-top: 10px; display: block;">
                        <?php echo $est_valide ? 'Sac prêt et scellé' : '👉 Vérifier ce sac'; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
}

require_once 'includes/footer.php';
?>