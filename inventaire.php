<?php
// inventaire.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$message = '';
$action = $_GET['action'] ?? 'resume';
$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;
$peut_editer = ($_SESSION['can_edit'] === 1);

// ==========================================
// 1. MISE À JOUR DE LA BASE DE DONNÉES
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventaires (id INTEGER PRIMARY KEY AUTOINCREMENT, date_debut TEXT, date_fin TEXT, statut TEXT DEFAULT 'en_cours')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventaires_lieux (inventaire_id INTEGER, lieu_id INTEGER, PRIMARY KEY(inventaire_id, lieu_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS historique_comptages (id INTEGER PRIMARY KEY AUTOINCREMENT, inventaire_id INTEGER, lieu_id INTEGER, materiel_id INTEGER, qte_avant INTEGER, qte_apres INTEGER)");
} catch (PDOException $e) {
}

$stmt_actif = $pdo->query("SELECT * FROM inventaires WHERE statut = 'en_cours' ORDER BY id DESC LIMIT 1");
$inventaire_actif = $stmt_actif->fetch();

// ==========================================
// 2. TRAITEMENT DES ACTIONS
// ==========================================

if ($action === 'export_xls') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Inventaire_Complet_" . date('Y-m-d') . ".xls");
    echo "\xEF\xBB\xBF";
    echo "<table border='1' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    echo "<tr style='background-color: #2c3e50; color: #ffffff; font-weight: bold;'>
            <th style='padding: 10px;'>Lieu de Stockage</th>
            <th style='padding: 10px;'>Catégorie</th>
            <th style='padding: 10px;'>Nom du Matériel</th>
            <th style='padding: 10px;'>Date de Péremption</th>
            <th style='padding: 10px;'>Quantité</th>
          </tr>";

    $stmt_export = $pdo->query("SELECT l.nom AS lieu_nom, c.nom AS categorie_nom, m.nom AS materiel_nom, s.date_peremption, s.quantite FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id JOIN lieux_stockage l ON s.lieu_id = l.id ORDER BY l.nom, c.nom, m.nom");
    while ($row = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
        $date_p = $row['date_peremption'] ? date('d/m/Y', strtotime($row['date_peremption'])) : 'Aucune';
        echo "<tr><td style='padding: 5px;'>" . htmlspecialchars($row['lieu_nom']) . "</td><td style='padding: 5px;'>" . htmlspecialchars($row['categorie_nom']) . "</td><td style='padding: 5px;'>" . htmlspecialchars($row['materiel_nom']) . "</td><td style='padding: 5px; text-align: center;'>" . htmlspecialchars($date_p) . "</td><td style='padding: 5px; text-align: center; font-weight: bold;'>" . htmlspecialchars($row['quantite']) . "</td></tr>";
    }
    echo "</table>";
    exit;
}

if ($action === 'lancer' && !$inventaire_actif && $peut_editer) {
    $pdo->exec("INSERT INTO inventaires (date_debut) VALUES (datetime('now', 'localtime'))");
    header("Location: inventaire.php");
    exit;
}

if ($action === 'cloturer' && $inventaire_actif && $peut_editer) {
    $stmt = $pdo->prepare("UPDATE inventaires SET statut = 'termine', date_fin = datetime('now', 'localtime') WHERE id = ?");
    $stmt->execute([$inventaire_actif['id']]);
    header("Location: inventaire.php?msg=cloture");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_lieu']) && $inventaire_actif) {
    if (!$peut_editer)
        die("🛑 Action bloquée : Vous n'avez pas les droits de modification.");
    $inv_id = $inventaire_actif['id'];
    if (isset($_POST['comptage']) && is_array($_POST['comptage'])) {
        foreach ($_POST['comptage'] as $stock_id => $qte_saisie) {
            if ($qte_saisie === '')
                continue;
            $qte = (int) $qte_saisie;
            $stock_id = (int) $stock_id;
            $stmt = $pdo->prepare("SELECT quantite, materiel_id FROM stocks WHERE id = ?");
            $stmt->execute([$stock_id]);
            $current = $stmt->fetch();
            if ($current && $current['quantite'] != $qte) {
                $stmt_hist = $pdo->prepare("INSERT INTO historique_comptages (inventaire_id, lieu_id, materiel_id, qte_avant, qte_apres) VALUES (?, ?, ?, ?, ?)");
                $stmt_hist->execute([$inv_id, $lieu_id, $current['materiel_id'], $current['quantite'], $qte]);
                if ($qte === 0)
                    $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$stock_id]);
                else
                    $pdo->prepare("UPDATE stocks SET quantite = ? WHERE id = ?")->execute([$qte, $stock_id]);
            }
        }
        $pdo->prepare("INSERT OR IGNORE INTO inventaires_lieux (inventaire_id, lieu_id) VALUES (?, ?)")->execute([$inv_id, $lieu_id]);
        header("Location: inventaire.php?msg=lieu_valide");
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cloture')
        $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 20px;">🎉 Félicitations ! L\'inventaire est clôturé et enregistré.</div>';
    if ($_GET['msg'] === 'lieu_valide')
        $message = '<div style="background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 20px;">✅ Le lieu a été inventorié avec succès !</div>';
}

require_once 'includes/header.php';

// ==========================================
// VUE 4 : RAPPORT DES ÉCARTS & INVENTAIRE DÉTAILLÉ
// ==========================================
if ($action === 'rapport') {
    $inv_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $stmt_inv = $pdo->prepare("SELECT * FROM inventaires WHERE id = ?");
    $stmt_inv->execute([$inv_id]);
    $inv = $stmt_inv->fetch();

    if (!$inv) {
        echo "Rapport introuvable.";
        require_once 'includes/footer.php';
        exit;
    }

    $stmt_diffs = $pdo->prepare("SELECT h.*, l.nom AS lieu_nom, l.icone AS lieu_icone, m.nom AS materiel_nom, c.nom AS categorie_nom FROM historique_comptages h JOIN lieux_stockage l ON h.lieu_id = l.id JOIN materiels m ON h.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE h.inventaire_id = ? ORDER BY l.nom, c.nom, m.nom");
    $stmt_diffs->execute([$inv_id]);
    $diffs = $stmt_diffs->fetchAll();

    $stmt_full = $pdo->query("SELECT s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom, l.nom AS lieu_nom, l.icone AS lieu_icone FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id JOIN lieux_stockage l ON s.lieu_id = l.id ORDER BY l.nom, c.nom, m.nom");
    $full_inventory = $stmt_full->fetchAll();

    $inventory_by_lieu = [];
    foreach ($full_inventory as $item) {
        $inventory_by_lieu[$item['lieu_nom']][] = $item;
    }
    ?>
    <style>
        .print-header {
            display: none;
        }

        @media print {
            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            .no-print,
            .topbar {
                display: none !important;
            }

            #sidebar,
            .sidebar {
                display: none !important;
            }

            main,
            #main-content,
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }

            .white-box {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }

            @page {
                size: auto;
                margin: 10mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-header {
                display: flex !important;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                font-weight: bold;
                color: #d32f2f !important;
                margin-bottom: 25px;
                border-bottom: 2px solid #d32f2f;
                padding-bottom: 15px;
                letter-spacing: 1px;
            }

            .print-header img {
                width: 60px;
                height: auto;
                margin-right: 15px;
            }
        }
    </style>
    <div class="white-box">
        <div class="print-header"><img src="assets/img/favicon.png" alt="Logo Secourut's">MATOS MANAGER</div>
        <div
            style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">
            <div>
                <a href="inventaire.php" class="no-print" style="color: #666; text-decoration: none; font-size: 14px;">⬅
                    Retour au résumé</a>
                <h2 style="margin: 10px 0 0 0; color: #d32f2f;">📊 Rapport d'inventaire</h2>
                <p style="margin: 5px 0 0 0; color: #666; font-style: italic;">Clôturé le
                    <?php echo date('d/m/Y à H:i', strtotime($inv['date_fin'])); ?></p>
            </div>
            <div class="no-print" style="display: flex; gap: 10px;">
                <a href="inventaire.php?action=export_xls" class="carte-animee"
                    style="background-color: #2e7d32; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px;">📥
                    Export Excel</a>
                <button onclick="window.print()" class="carte-animee"
                    style="background-color: #333; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px;">🖨️
                    Imprimer / PDF</button>
            </div>
        </div>
        <h3 style="color: #c62828;">Écarts de la base de donnée constatés</h3>
        <?php if (empty($diffs)): ?>
            <div
                style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; text-align: center; font-size: 16px; border: 1px solid #c8e6c9;">
                Aucune erreur d'inventaire n'a été constatée.</div>
        <?php else: ?>
            <table class="table-manager" style="margin-bottom: 30px; border: 1px solid #ddd;">
                <thead>
                    <tr>
                        <th>Lieu</th>
                        <th>Matériel</th>
                        <th style="text-align: center;">Avant</th>
                        <th style="text-align: center;">Après</th>
                        <th style="text-align: center;">Écart</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diffs as $d):
                        $ecart = $d['qte_apres'] - $d['qte_avant'];
                        $couleur_ecart = $ecart > 0 ? '#2e7d32' : '#c62828';
                        $signe = $ecart > 0 ? '+' : ''; ?>
                        <tr>
                            <td style="font-weight: bold; border-right: 1px solid #eee;">
                                <?php echo $d['lieu_icone'] . ' ' . htmlspecialchars($d['lieu_nom']); ?></td>
                            <td style="border-right: 1px solid #eee;"><?php echo htmlspecialchars($d['materiel_nom']); ?></td>
                            <td style="text-align: center; color: #999; border-right: 1px solid #eee;">
                                <?php echo $d['qte_avant']; ?></td>
                            <td style="text-align: center; font-weight: bold; border-right: 1px solid #eee;">
                                <?php echo $d['qte_apres']; ?></td>
                            <td style="text-align: center; font-weight: bold; color: <?php echo $couleur_ecart; ?>;">
                                <?php echo $signe . $ecart; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <h3 style="color: #2c3e50; margin-top: 40px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">📋 Inventaire
            Détaillé Complet</h3>
        <?php if (empty($inventory_by_lieu)): ?>
            <p style="text-align: center; color: #999;">Le stock total est entièrement vide.</p>
        <?php else: ?>
            <?php foreach ($inventory_by_lieu as $lieu_nom => $articles): ?>
                <div style="page-break-inside: avoid; margin-bottom: 20px;">
                    <h4
                        style="background-color: #e0e0e0; color: #333; padding: 8px 15px; border-radius: 4px 4px 0 0; margin: 0; font-size: 15px; border: 1px solid #ccc; border-bottom: none;">
                        <?php echo htmlspecialchars($articles[0]['lieu_icone'] . ' ' . $lieu_nom); ?></h4>
                    <table class="table-manager" style="border: 1px solid #ccc; margin-bottom: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 25%; border-right: 1px solid #ccc;">Catégorie</th>
                                <th style="width: 45%; border-right: 1px solid #ccc;">Matériel</th>
                                <th style="text-align: center; width: 15%; border-right: 1px solid #ccc;">Péremption</th>
                                <th style="text-align: center; width: 15%;">Quantité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $art): ?>
                                <tr>
                                    <td style="border-right: 1px solid #eee; font-size: 13px;">
                                        <?php echo htmlspecialchars($art['categorie_nom']); ?></td>
                                    <td style="border-right: 1px solid #eee; font-size: 13px; font-weight: 500;">
                                        <?php echo htmlspecialchars($art['materiel_nom']); ?></td>
                                    <td style="text-align: center; font-size: 12px; color: #666; border-right: 1px solid #eee;">
                                        <?php echo $art['date_peremption'] ? date('d/m/Y', strtotime($art['date_peremption'])) : '-'; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 14px; font-weight: bold;"><?php echo $art['quantite']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// VUE 3 : INTERFACE DE COMPTAGE D'UN LIEU
// ==========================================
elseif ($inventaire_actif && $action === 'comptage' && $lieu_id > 0) {
    if (!$peut_editer) {
        header('Location: inventaire.php');
        exit;
    }
    $stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
    $stmt_lieu->execute(['id' => $lieu_id]);
    $lieu = $stmt_lieu->fetch();
    if (!$lieu) {
        header('Location: inventaire.php');
        exit;
    }

    $stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom");
    $stmt_stocks->execute(['lieu_id' => $lieu_id]);
    $stocks = $stmt_stocks->fetchAll();
    $stocks_par_categorie = [];
    foreach ($stocks as $stock) {
        $stocks_par_categorie[$stock['categorie_nom']][] = $stock;
    }
    ?>
    <div class="white-box"
        style="position: sticky; top: 0; z-index: 100; border-bottom: 3px solid #d32f2f; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="inventaire.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Annuler et retourner
                    au menu</a>
                <h2 style="margin: 5px 0 0 0; color: #333;">📋 Pointage : <?php echo htmlspecialchars($lieu['nom']); ?></h2>
            </div>
            <div style="display: flex; gap: 15px;">
                <div
                    style="background: #e8f5e9; padding: 10px 20px; border-radius: 8px; text-align: center; border: 1px solid #c8e6c9;">
                    <div style="font-size: 24px; font-weight: bold; color: #2e7d32;" id="compteur-valides">0</div>
                    <div style="font-size: 12px; color: #2e7d32; text-transform: uppercase;">Bons</div>
                </div>
                <div
                    style="background: #fff3e0; padding: 10px 20px; border-radius: 8px; text-align: center; border: 1px solid #ffe0b2;">
                    <div style="font-size: 24px; font-weight: bold; color: #ef6c00;" id="compteur-restants">
                        <?php echo count($stocks); ?></div>
                    <div style="font-size: 12px; color: #ef6c00; text-transform: uppercase;">À vérifier</div>
                </div>
            </div>
        </div>
    </div>

    <form id="form-inventaire" method="POST" action="inventaire.php?action=comptage&lieu_id=<?php echo $lieu_id; ?>">
        <input type="hidden" name="valider_lieu" value="1">
        <?php if (empty($stocks_par_categorie)): ?>
            <p style="text-align: center; color: #999;">Ce lieu est vide.</p>
        <?php else: ?>
            <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
                <div style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?></h3>

                    <table class="table-manager">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Matériel</th>
                                <th style="text-align: center; width: 20%;">Péremption</th>
                                <th style="text-align: center; width: 20%;">Théorique</th>
                                <th style="text-align: center; width: 20%;">Compté</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $art): ?>
                                <tr class="item-row" data-etat="vide" style="transition: background-color 0.3s;">
                                    <td style="font-weight: 500; color: #333;"><?php echo htmlspecialchars($art['materiel_nom']); ?>
                                    </td>
                                    <td style="text-align: center; color: #666; font-size: 13px;">
                                        <?php echo $art['date_peremption'] ? date('d/m/Y', strtotime($art['date_peremption'])) : '-'; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 18px; font-weight: bold; color: #999;">
                                        <?php echo $art['quantite']; ?></td>
                                    <td style="text-align: center;"><input type="number" min="0"
                                            name="comptage[<?php echo $art['stock_id']; ?>]" class="input-comptage"
                                            data-attendu="<?php echo $art['quantite']; ?>" oninput="verifierLigne(this)" placeholder="?"
                                            style="width: 80px; padding: 10px; font-size: 18px; text-align: center; border: 2px solid #ccc; border-radius: 6px; font-weight: bold; outline: none;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div style="text-align: center; margin: 40px 0; padding-bottom: 40px;"><button type="button"
                onclick="validerFormulaire()" class="carte-animee"
                style="background-color: #2e7d32; color: white; border: none; padding: 15px 40px; font-size: 20px; font-weight: bold; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 10px rgba(46,125,50,0.3);">✅
                Enregistrer ce sac</button></div>
    </form>

    <script>
        function verifierLigne(inputField) {
            const row = inputField.closest('.item-row');
            const valeurAttendue = parseInt(inputField.getAttribute('data-attendu'));
            const valeurSaisie = inputField.value;
            if (valeurSaisie === '') { row.style.backgroundColor = 'transparent'; inputField.style.borderColor = '#ccc'; row.setAttribute('data-etat', 'vide'); }
            else if (parseInt(valeurSaisie) === valeurAttendue) { row.style.backgroundColor = '#e8f5e9'; inputField.style.borderColor = '#4caf50'; row.setAttribute('data-etat', 'bon'); }
            else { row.style.backgroundColor = '#ffebee'; inputField.style.borderColor = '#f44336'; row.setAttribute('data-etat', 'erreur'); }
            recalculerCompteurs();
        }
        function recalculerCompteurs() {
            const toutesLesLignes = document.querySelectorAll('.item-row');
            let nbBons = 0;
            toutesLesLignes.forEach(row => { if (row.getAttribute('data-etat') === 'bon') nbBons++; });
            document.getElementById('compteur-valides').innerText = nbBons;
            document.getElementById('compteur-restants').innerText = (toutesLesLignes.length - nbBons);
        }
        function validerFormulaire() {
            const lignesVides = document.querySelectorAll('.item-row[data-etat="vide"]').length;
            if (lignesVides > 0) { if (!confirm(lignesVides + " articles n'ont pas été remplis. Leurs quantités théoriques seront conservées. Es-tu sûr de vouloir valider ce sac ?")) return; }
            else { if (!confirm("Tout a été pointé. Es-tu sûr de vouloir valider et clôturer l'inventaire de ce lieu ?")) return; }
            document.getElementById('form-inventaire').submit();
        }
    </script>
    <?php
}

// ==========================================
// VUE 2 : SÉLECTION DU LIEU (Inventaire Actif + TEMPS REEL)
// ==========================================
elseif ($inventaire_actif) {
    echo $message;
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY type, nom")->fetchAll();
    $stmt_faits = $pdo->prepare("SELECT lieu_id FROM inventaires_lieux WHERE inventaire_id = ?");
    $stmt_faits->execute([$inventaire_actif['id']]);
    $lieux_faits_bruts = $stmt_faits->fetchAll();
    $lieux_faits = array_column($lieux_faits_bruts, 'lieu_id');
    $tous_faits = (count($lieux_faits) === count($lieux) && count($lieux) > 0);
    ?>
    <div
        style="background: #fff3e0; padding: 20px; border-radius: 8px; border-left: 5px solid #ef6c00; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0 0 5px 0; color: #e65100;">INVENTAIRE EN COURS</h2>
            <p style="margin: 0; color: #666;">Cliquez sur un lieu de stockage pour l'inventorier. Une fois validé il ne
                sera plus possible de le modifier.</p>
        </div>
        <div style="font-size: 24px; font-weight: bold; color: #ef6c00;">
            <span id="compteur-faits"><?php echo count($lieux_faits); ?></span> / <span
                id="compteur-total"><?php echo count($lieux); ?></span> faits
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 40px;">
        <?php foreach ($lieux as $lieu):
            $est_fait = in_array($lieu['id'], $lieux_faits);
            $icone = !empty($lieu['icone']) ? $lieu['icone'] : '🎒';
            ?>
            <div id="lieu-container-<?php echo $lieu['id']; ?>" class="lieu-container" data-id="<?php echo $lieu['id']; ?>"
                data-nom="<?php echo htmlspecialchars($lieu['nom']); ?>" data-icone="<?php echo htmlspecialchars($icone); ?>">
                <?php if ($est_fait): ?>
                    <div
                        style="display: block; width: 200px; padding: 20px; background-color: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; color: #2e7d32; text-align: center; opacity: 0.7; box-sizing: border-box;">
                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                        <strong
                            style="font-size: 16px; display: block; text-decoration: line-through;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                        <span style="font-size: 12px; font-weight: bold; margin-top: 10px; display: block;">Déjà pointé</span>
                    </div>
                <?php else: ?>
                    <?php if ($peut_editer): ?>
                        <a href="inventaire.php?action=comptage&lieu_id=<?php echo $lieu['id']; ?>"
                            onclick="return confirm('Êtes-vous sûr de vouloir commencer l\'inventaire de ce lieu ?');"
                            class="carte-animee"
                            style="display: block; width: 200px; padding: 20px; background-color: white; border: 2px solid transparent; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-decoration: none; color: #333; text-align: center; box-sizing: border-box;">
                            <div style="font-size: 40px; margin-bottom: 10px;"><?php echo htmlspecialchars($icone); ?></div>
                            <strong style="font-size: 16px; display: block;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                            <span style="font-size: 12px; color: #d32f2f; font-weight: bold; margin-top: 10px; display: block;">👉 Faire
                                ce lieu</span>
                        </a>
                    <?php else: ?>
                        <div
                            style="display: block; width: 200px; padding: 20px; background-color: white; border: 2px solid transparent; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); color: #333; text-align: center; opacity: 0.6; box-sizing: border-box;">
                            <div style="font-size: 40px; margin-bottom: 10px;"><?php echo htmlspecialchars($icone); ?></div>
                            <strong style="font-size: 16px; display: block;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                            <span style="font-size: 12px; color: #999; font-weight: bold; margin-top: 10px; display: block;">En attente
                                de pointage...</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="zone-cloture"
        style="text-align: center; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: <?php echo $tous_faits ? 'block' : 'none'; ?>;">
        <h3 style="color: #2e7d32; margin-top: 0;">🎉 Tous les lieux ont été inventoriés !</h3>
        <?php if ($peut_editer): ?>
            <a href="inventaire.php?action=cloturer"
                onclick="return confirm('Êtes-vous sûr de vouloir clôturer définitivement cet inventaire global ?');"
                class="carte-animee"
                style="display: inline-block; background-color: #2e7d32; color: white; padding: 15px 40px; font-size: 20px; font-weight: bold; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 10px rgba(46,125,50,0.3);">
                🔒 CLÔTURER L'INVENTAIRE GLOBAL
            </a>
        <?php endif; ?>
    </div>

    <script>
        setInterval(function () {
            fetch('api_inventaire.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const lieuxFaits = data.lieux_faits;
                        const totalLieux = parseInt(document.getElementById('compteur-total').innerText);
                        let nombreFaitsActuel = 0;

                        document.querySelectorAll('.lieu-container').forEach(container => {
                            const id = parseInt(container.getAttribute('data-id'));
                            const nom = container.getAttribute('data-nom');
                            if (lieuxFaits.includes(id)) {
                                nombreFaitsActuel++;
                                if (!container.innerHTML.includes('✅')) {
                                    container.innerHTML = `
                                    <div style="display: block; width: 200px; padding: 20px; background-color: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; color: #2e7d32; text-align: center; opacity: 0.7; box-sizing: border-box;">
                                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                                        <strong style="font-size: 16px; display: block; text-decoration: line-through;">${nom}</strong>
                                        <span style="font-size: 12px; font-weight: bold; margin-top: 10px; display: block;">Déjà pointé</span>
                                    </div>
                                `;
                                }
                            }
                        });
                        document.getElementById('compteur-faits').innerText = nombreFaitsActuel;
                        if (nombreFaitsActuel >= totalLieux && totalLieux > 0) {
                            document.getElementById('zone-cloture').style.display = 'block';
                        }
                    }
                })
                .catch(error => console.error("Synchronisation...", error));
        }, 3000);
    </script>
    <?php
}

// ==========================================
// VUE 1 : VUE GLOBALE & RÉSUMÉ (Hors inventaire)
// ==========================================
else {
    echo $message;
    $nb_catalog = $pdo->query("SELECT COUNT(*) FROM materiels")->fetchColumn();
    $nb_objets_total = $pdo->query("SELECT SUM(quantite) FROM stocks")->fetchColumn() ?: 0;
    $stmt_dernier = $pdo->query("SELECT id, date_fin FROM inventaires WHERE statut = 'termine' ORDER BY date_fin DESC LIMIT 1");
    $dernier_inv = $stmt_dernier->fetch();
    $date_affichage = $dernier_inv ? date('d/m/Y à H:i', strtotime($dernier_inv['date_fin'])) : 'Jamais réalisé';

    $stmt_repartition = $pdo->query("SELECT m.nom AS materiel_nom, c.nom AS categorie_nom, l.nom AS lieu_nom, SUM(s.quantite) as qte FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id JOIN lieux_stockage l ON s.lieu_id = l.id GROUP BY m.id, l.id ORDER BY c.nom, m.nom, l.nom");
    $repartitions_brutes = $stmt_repartition->fetchAll();
    $repartition_triee = [];
    foreach ($repartitions_brutes as $rep) {
        $repartition_triee[$rep['categorie_nom']][$rep['materiel_nom']][] = ['lieu' => $rep['lieu_nom'], 'quantite' => $rep['qte']];
    }
    ?>
    <div style="display: flex; gap: 20px; margin-bottom: 25px;">
        <div class="white-box" style="flex: 1; text-align: center; border-bottom: 4px solid #3498db; margin-bottom: 0;">
            <div style="font-size: 14px; color: #666; text-transform: uppercase;">Dans le catalogue</div>
            <div style="font-size: 36px; font-weight: bold; color: #3498db; margin: 10px 0;"><?php echo $nb_catalog; ?>
            </div>
            <div style="font-size: 13px; color: #999;">Références distinctes</div>
        </div>
        <div class="white-box" style="flex: 1; text-align: center; border-bottom: 4px solid #9b59b6; margin-bottom: 0;">
            <div style="font-size: 14px; color: #666; text-transform: uppercase;">Total physique</div>
            <div style="font-size: 36px; font-weight: bold; color: #9b59b6; margin: 10px 0;"><?php echo $nb_objets_total; ?>
            </div>
            <div style="font-size: 13px; color: #999;">Objets en circulation</div>
        </div>
        <?php if ($dernier_inv): ?>
            <a href="inventaire.php?action=rapport&id=<?php echo $dernier_inv['id']; ?>" class="carte-animee white-box"
                style="flex: 1; text-decoration: none; text-align: center; border-bottom: 4px solid #f1c40f; display: block; margin-bottom: 0;">
                <div style="font-size: 14px; color: #666; text-transform: uppercase;">Dernier pointage</div>
                <div style="font-size: 24px; font-weight: bold; color: #f39c12; margin: 15px 0;"><?php echo $date_affichage; ?>
                </div>
                <div style="font-size: 13px; color: #f39c12; font-weight: bold;">👉 Voir le rapport</div>
            </a>
        <?php else: ?>
            <div class="white-box" style="flex: 1; text-align: center; border-bottom: 4px solid #f1c40f; margin-bottom: 0;">
                <div style="font-size: 14px; color: #666; text-transform: uppercase;">Dernier pointage</div>
                <div style="font-size: 24px; font-weight: bold; color: #f39c12; margin: 15px 0;">Jamais réalisé</div>
                <div style="font-size: 13px; color: #999;">Aucun historique</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($peut_editer): ?>
        <div style="text-align: center; margin-bottom: 30px;"><a href="inventaire.php?action=lancer"
                onclick="return confirm('Êtes-vous sûr de vouloir lancer un nouvel inventaire global ?');" class="carte-animee"
                style="display: inline-block; background-color: #d32f2f; color: white; padding: 20px 50px; font-size: 22px; font-weight: bold; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 10px rgba(211,47,47,0.3);">FAIRE
                L'INVENTAIRE</a></div>
    <?php endif; ?>

    <div class="white-box">
        <h2 style="margin: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">Répartition globale des
            stocks</h2>
        <?php if (empty($repartition_triee)): ?>
            <p style="text-align: center; color: #999; font-style: italic;">Aucun matériel en stock.</p>
        <?php else: ?>
            <?php foreach ($repartition_triee as $categorie => $materiels_liste): ?>
                <div style="margin-bottom: 30px;">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?></h3>
                    <table class="table-manager" style="border: 1px solid #eee;">
                        <thead>
                            <tr>
                                <th style="width: 40%; border-bottom: 1px solid #ddd;">Nom du matériel</th>
                                <th style="border-bottom: 1px solid #ddd;">Répartition par lieux (Sacs & Réserves)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiels_liste as $nom_mat => $lieux): ?>
                                <?php $somme_totale_objet = 0;
                                foreach ($lieux as $l) {
                                    $somme_totale_objet += $l['quantite'];
                                } ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="font-weight: 500; color: #333; border-right: 1px solid #eee;">
                                        <?php echo htmlspecialchars($nom_mat); ?>
                                        <div style="font-size: 11px; color: #999; margin-top: 5px;">Total dispo :
                                            <strong><?php echo $somme_totale_objet; ?></strong></div>
                                    </td>
                                    <td style="font-size: 14px; color: #555;">
                                        <div style="display: flex; gap: 10px; flex-wrap: wrap;"><?php foreach ($lieux as $l): ?><span
                                                    style="background: #f4f7f6; padding: 5px 10px; border-radius: 4px; border: 1px solid #e0e0e0;"><strong><?php echo htmlspecialchars($l['lieu']); ?></strong>
                                                    : <?php echo $l['quantite']; ?></span><?php endforeach; ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}
require_once 'includes/footer.php';
?>