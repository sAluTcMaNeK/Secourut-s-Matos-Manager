<?php
// inventaire.php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';
$peut_editer = $peut_editer_matos;

$message = '';
$action = $_GET['action'] ?? 'resume';
$lieu_id = isset($_GET['lieu_id']) ? (int) $_GET['lieu_id'] : 0;

$stmt_actif = $pdo->query("SELECT * FROM inventaires WHERE statut = 'en_cours' ORDER BY id DESC LIMIT 1");
$inventaire_actif = $stmt_actif->fetch();

// ==========================================
// 2A. TRAITEMENT EXPORT EXCEL (ÉTAT ACTUEL)
// ==========================================
if ($action === 'export_xls') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Etat_Des_Stocks_" . date('Y-m-d') . ".xls");

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

    echo '  <Styles>
                <Style ss:ID="Default" ss:Name="Normal">
                    <Alignment ss:Vertical="Center"/>
                </Style>
                <Style ss:ID="Header">
                    <Font ss:Bold="1" ss:Color="#FFFFFF"/>
                    <Interior ss:Color="#2c3e50" ss:Pattern="Solid"/>
                    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                </Style>
            </Styles>' . "\n";

    $stmt_export = $pdo->query("
        SELECT l.nom AS lieu_nom, l.type AS lieu_type, c.nom AS categorie_nom, m.nom AS materiel_nom, s.poche, s.date_peremption, s.quantite 
        FROM stocks s 
        JOIN materiels m ON s.materiel_id = m.id 
        JOIN categories c ON m.categorie_id = c.id 
        JOIN lieux_stockage l ON s.lieu_id = l.id 
        ORDER BY l.type, l.nom, s.poche, c.nom, m.nom
    ");
    $all_stocks = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    $data_by_type = [];
    foreach ($all_stocks as $row) {
        $t = !empty($row['lieu_type']) ? $row['lieu_type'] : 'Autre';
        if ($t === 'sac_inter')
            $t = 'Sacs Intervention';
        elseif ($t === 'sac_log')
            $t = 'Sacs Logistique';
        elseif ($t === 'reserve')
            $t = 'Réserves';
        $data_by_type[$t][] = $row;
    }

    if (empty($data_by_type)) {
        echo '  <Worksheet ss:Name="Vide"><Table><Row><Cell><Data ss:Type="String">Aucun stock disponible</Data></Cell></Row></Table></Worksheet>' . "\n";
    } else {
        foreach ($data_by_type as $type => $rows) {
            $sheet_name = substr(str_replace(['/', '\\', '?', '*', ':', '[', ']'], '_', $type), 0, 31);
            echo '  <Worksheet ss:Name="' . htmlspecialchars($sheet_name) . '">' . "\n";
            echo '    <Table>' . "\n";
            echo '      <Row>' . "\n";
            echo '        <Cell ss:Index="1" ss:StyleID="Header"><Data ss:Type="String">Lieu de stockage</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="2" ss:StyleID="Header"><Data ss:Type="String">Emplacement / Poche</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="3" ss:StyleID="Header"><Data ss:Type="String">Catégorie Matériel</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="4" ss:StyleID="Header"><Data ss:Type="String">Matériel</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="5" ss:StyleID="Header"><Data ss:Type="String">Péremption</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="6" ss:StyleID="Header"><Data ss:Type="String">Quantité</Data></Cell>' . "\n";
            echo '      </Row>' . "\n";

            $spans = [];
            $n = count($rows);
            $colKeys = ['lieu_nom', 'poche', 'categorie_nom', 'materiel_nom'];
            for ($i = 0; $i < $n; $i++)
                $spans[$i] = ['lieu_nom' => 1, 'poche' => 1, 'categorie_nom' => 1, 'materiel_nom' => 1];

            for ($col = 0; $col < 4; $col++) {
                $key = $colKeys[$col];
                $startIdx = 0;
                while ($startIdx < $n) {
                    $parentVal = '';
                    for ($p = 0; $p <= $col; $p++)
                        $parentVal .= ($rows[$startIdx][$colKeys[$p]] ?? '') . '|';
                    $count = 1;
                    for ($j = $startIdx + 1; $j < $n; $j++) {
                        $currentParentVal = '';
                        for ($p = 0; $p <= $col; $p++)
                            $currentParentVal .= ($rows[$j][$colKeys[$p]] ?? '') . '|';
                        if ($currentParentVal === $parentVal) {
                            $count++;
                            $spans[$j][$key] = 0;
                        } else
                            break;
                    }
                    $spans[$startIdx][$key] = $count;
                    $startIdx += $count;
                }
            }

            foreach ($rows as $i => $r) {
                echo '      <Row>' . "\n";
                if ($spans[$i]['lieu_nom'] > 0) {
                    $merge = $spans[$i]['lieu_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['lieu_nom'] - 1) . '"' : '';
                    echo '        <Cell ss:Index="1"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['lieu_nom']) . '</Data></Cell>' . "\n";
                }
                if ($spans[$i]['poche'] > 0) {
                    $merge = $spans[$i]['poche'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['poche'] - 1) . '"' : '';
                    echo '        <Cell ss:Index="2"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['poche'] ?? '') . '</Data></Cell>' . "\n";
                }
                if ($spans[$i]['categorie_nom'] > 0) {
                    $merge = $spans[$i]['categorie_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['categorie_nom'] - 1) . '"' : '';
                    echo '        <Cell ss:Index="3"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['categorie_nom']) . '</Data></Cell>' . "\n";
                }
                if ($spans[$i]['materiel_nom'] > 0) {
                    $merge = $spans[$i]['materiel_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['materiel_nom'] - 1) . '"' : '';
                    echo '        <Cell ss:Index="4"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['materiel_nom']) . '</Data></Cell>' . "\n";
                }
                $date_p = $r['date_peremption'] ? date('d/m/Y', strtotime($r['date_peremption'])) : 'Aucune';
                echo '        <Cell ss:Index="5"><Data ss:Type="String">' . htmlspecialchars($date_p) . '</Data></Cell>' . "\n";
                echo '        <Cell ss:Index="6"><Data ss:Type="Number">' . htmlspecialchars($r['quantite']) . '</Data></Cell>' . "\n";
                echo '      </Row>' . "\n";
            }
            echo '    </Table>' . "\n";
            echo '  </Worksheet>' . "\n";
        }
    }
    echo '</Workbook>';
    exit;
}

// ==========================================
// 2B. TRAITEMENT EXPORT EXCEL (RAPPORT HISTORIQUE)
// ==========================================
if ($action === 'export_rapport_xls' && isset($_GET['id'])) {
    $inv_id = (int) $_GET['id'];
    $stmt_inv = $pdo->prepare("SELECT * FROM inventaires WHERE id = ?");
    $stmt_inv->execute([$inv_id]);
    $inv = $stmt_inv->fetch();

    if (!$inv)
        die("Rapport introuvable.");

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Rapport_Inventaire_" . date('Y-m-d', strtotime($inv['date_fin'])) . ".xls");

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

    echo '  <Styles>
                <Style ss:ID="Default" ss:Name="Normal">
                    <Alignment ss:Vertical="Center"/>
                </Style>
                <Style ss:ID="Header">
                    <Font ss:Bold="1" ss:Color="#FFFFFF"/>
                    <Interior ss:Color="#2c3e50" ss:Pattern="Solid"/>
                    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                </Style>
                <Style ss:ID="Bad"><Font ss:Bold="1" ss:Color="#c62828"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
                <Style ss:ID="Good"><Font ss:Bold="1" ss:Color="#2e7d32"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
                <Style ss:ID="Center"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
            </Styles>' . "\n";

    // --- ONGLET 1 : LES ÉCARTS CONSTATÉS ---
    $stmt_diffs = $pdo->prepare("SELECT h.*, l.nom AS lieu_nom, m.nom AS materiel_nom, c.nom AS categorie_nom FROM historique_comptages h JOIN lieux_stockage l ON h.lieu_id = l.id JOIN materiels m ON h.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE h.inventaire_id = ? ORDER BY l.nom, c.nom, m.nom");
    $stmt_diffs->execute([$inv_id]);
    $diffs = $stmt_diffs->fetchAll(PDO::FETCH_ASSOC);

    echo '  <Worksheet ss:Name="1 - Écarts Constatés">' . "\n";
    echo '    <Table>' . "\n";
    echo '      <Column ss:Width="150"/> <Column ss:Width="120"/> <Column ss:Width="200"/> <Column ss:Width="70"/> <Column ss:Width="70"/> <Column ss:Width="70"/> <Column ss:Width="250"/>' . "\n";
    echo '      <Row>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Lieu</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Catégorie</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Matériel</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Qté Avant</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Qté Après</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Écart</Data></Cell>' . "\n";
    echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">Détails / Action corrective</Data></Cell>' . "\n";
    echo '      </Row>' . "\n";

    if (empty($diffs)) {
        echo '      <Row><Cell><Data ss:Type="String">Aucun écart constaté lors de cet inventaire.</Data></Cell></Row>' . "\n";
    } else {
        foreach ($diffs as $d) {
            $ecart = $d['qte_apres'] - $d['qte_avant'];
            $styleEcart = $ecart > 0 ? 'Good' : 'Bad';
            $signe = $ecart > 0 ? '+' : '';
            echo '      <Row>' . "\n";
            echo '        <Cell><Data ss:Type="String">' . htmlspecialchars($d['lieu_nom']) . '</Data></Cell>' . "\n";
            echo '        <Cell><Data ss:Type="String">' . htmlspecialchars($d['categorie_nom']) . '</Data></Cell>' . "\n";
            echo '        <Cell><Data ss:Type="String">' . htmlspecialchars($d['materiel_nom']) . '</Data></Cell>' . "\n";
            echo '        <Cell ss:StyleID="Center"><Data ss:Type="Number">' . htmlspecialchars($d['qte_avant']) . '</Data></Cell>' . "\n";
            echo '        <Cell ss:StyleID="Center"><Data ss:Type="Number">' . htmlspecialchars($d['qte_apres']) . '</Data></Cell>' . "\n";
            echo '        <Cell ss:StyleID="' . $styleEcart . '"><Data ss:Type="String">' . $signe . $ecart . '</Data></Cell>' . "\n";
            echo '        <Cell><Data ss:Type="String">' . htmlspecialchars($d['action_corrective'] ?? 'Ajustement manuel') . '</Data></Cell>' . "\n";
            echo '      </Row>' . "\n";
        }
    }
    echo '    </Table>' . "\n";
    echo '  </Worksheet>' . "\n";

    // --- ONGLET 2+ : LE STOCK FINAL DU RAPPORT (Même DA que l'état actuel) ---
    $stmt_export = $pdo->query("SELECT l.nom AS lieu_nom, l.type AS lieu_type, c.nom AS categorie_nom, m.nom AS materiel_nom, s.poche, s.date_peremption, s.quantite FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id JOIN lieux_stockage l ON s.lieu_id = l.id ORDER BY l.type, l.nom, s.poche, c.nom, m.nom");
    $all_stocks = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    $data_by_type = [];
    foreach ($all_stocks as $row) {
        $t = !empty($row['lieu_type']) ? $row['lieu_type'] : 'Autre';
        if ($t === 'sac_inter')
            $t = 'Sacs Intervention';
        elseif ($t === 'sac_log')
            $t = 'Sacs Logistique';
        elseif ($t === 'reserve')
            $t = 'Réserves';
        $data_by_type[$t][] = $row;
    }

    foreach ($data_by_type as $type => $rows) {
        $sheet_name = substr(str_replace(['/', '\\', '?', '*', ':', '[', ']'], '_', $type), 0, 31);
        echo '  <Worksheet ss:Name="' . htmlspecialchars($sheet_name) . '">' . "\n";
        echo '    <Table>' . "\n";
        echo '      <Row>' . "\n";
        echo '        <Cell ss:Index="1" ss:StyleID="Header"><Data ss:Type="String">Lieu de stockage</Data></Cell>' . "\n";
        echo '        <Cell ss:Index="2" ss:StyleID="Header"><Data ss:Type="String">Emplacement / Poche</Data></Cell>' . "\n";
        echo '        <Cell ss:Index="3" ss:StyleID="Header"><Data ss:Type="String">Catégorie Matériel</Data></Cell>' . "\n";
        echo '        <Cell ss:Index="4" ss:StyleID="Header"><Data ss:Type="String">Matériel</Data></Cell>' . "\n";
        echo '        <Cell ss:Index="5" ss:StyleID="Header"><Data ss:Type="String">Péremption</Data></Cell>' . "\n";
        echo '        <Cell ss:Index="6" ss:StyleID="Header"><Data ss:Type="String">Quantité</Data></Cell>' . "\n";
        echo '      </Row>' . "\n";

        $spans = [];
        $n = count($rows);
        $colKeys = ['lieu_nom', 'poche', 'categorie_nom', 'materiel_nom'];
        for ($i = 0; $i < $n; $i++)
            $spans[$i] = ['lieu_nom' => 1, 'poche' => 1, 'categorie_nom' => 1, 'materiel_nom' => 1];

        for ($col = 0; $col < 4; $col++) {
            $key = $colKeys[$col];
            $startIdx = 0;
            while ($startIdx < $n) {
                $parentVal = '';
                for ($p = 0; $p <= $col; $p++)
                    $parentVal .= ($rows[$startIdx][$colKeys[$p]] ?? '') . '|';
                $count = 1;
                for ($j = $startIdx + 1; $j < $n; $j++) {
                    $currentParentVal = '';
                    for ($p = 0; $p <= $col; $p++)
                        $currentParentVal .= ($rows[$j][$colKeys[$p]] ?? '') . '|';
                    if ($currentParentVal === $parentVal) {
                        $count++;
                        $spans[$j][$key] = 0;
                    } else
                        break;
                }
                $spans[$startIdx][$key] = $count;
                $startIdx += $count;
            }
        }

        foreach ($rows as $i => $r) {
            echo '      <Row>' . "\n";
            if ($spans[$i]['lieu_nom'] > 0) {
                $merge = $spans[$i]['lieu_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['lieu_nom'] - 1) . '"' : '';
                echo '        <Cell ss:Index="1"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['lieu_nom']) . '</Data></Cell>' . "\n";
            }
            if ($spans[$i]['poche'] > 0) {
                $merge = $spans[$i]['poche'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['poche'] - 1) . '"' : '';
                echo '        <Cell ss:Index="2"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['poche'] ?? '') . '</Data></Cell>' . "\n";
            }
            if ($spans[$i]['categorie_nom'] > 0) {
                $merge = $spans[$i]['categorie_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['categorie_nom'] - 1) . '"' : '';
                echo '        <Cell ss:Index="3"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['categorie_nom']) . '</Data></Cell>' . "\n";
            }
            if ($spans[$i]['materiel_nom'] > 0) {
                $merge = $spans[$i]['materiel_nom'] > 1 ? ' ss:MergeDown="' . ($spans[$i]['materiel_nom'] - 1) . '"' : '';
                echo '        <Cell ss:Index="4"' . $merge . '><Data ss:Type="String">' . htmlspecialchars($r['materiel_nom']) . '</Data></Cell>' . "\n";
            }
            $date_p = $r['date_peremption'] ? date('d/m/Y', strtotime($r['date_peremption'])) : 'Aucune';
            echo '        <Cell ss:Index="5"><Data ss:Type="String">' . htmlspecialchars($date_p) . '</Data></Cell>' . "\n";
            echo '        <Cell ss:Index="6"><Data ss:Type="Number">' . htmlspecialchars($r['quantite']) . '</Data></Cell>' . "\n";
            echo '      </Row>' . "\n";
        }
        echo '    </Table>' . "\n";
        echo '  </Worksheet>' . "\n";
    }

    echo '</Workbook>';
    exit;
}

if ($action === 'lancer' && !$inventaire_actif && $peut_editer) {
    $pdo->exec("INSERT INTO inventaires (date_debut) VALUES (NOW())");
    logAction($pdo, "A lancé un nouvel inventaire global");
    header("Location: inventaire");
    exit;
}

if ($action === 'cloturer' && $inventaire_actif && $peut_editer) {
    $stmt = $pdo->prepare("UPDATE inventaires SET statut = 'termine', date_fin = NOW() WHERE id = ?");
    $stmt->execute([$inventaire_actif['id']]);
    logAction($pdo, "A clôturé l'inventaire global");
    header("Location: inventaire?msg=cloture");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_lieu']) && $inventaire_actif) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-weight: bold; border-radius: 5px; margin: 20px;'>🛑 Action bloquée : Erreur de sécurité (Jeton CSRF invalide ou expiré). Veuillez recharger la page.</div>");
    }

    if (!$peut_editer)
        die("🛑 Action bloquée.");
    $inv_id = $inventaire_actif['id'];
    $stmt_lieu = $pdo->prepare("SELECT est_reserve, nom FROM lieux_stockage WHERE id = ?");
    $stmt_lieu->execute([$lieu_id]);
    $lieu_info = $stmt_lieu->fetch();
    $est_reserve = $lieu_info['est_reserve'] == 1;

    if (isset($_POST['comptage']) && is_array($_POST['comptage'])) {
        try {
            $pdo->beginTransaction();
            foreach ($_POST['comptage'] as $stock_id => $data) {
                $stock_id = (int) $stock_id;
                if (!isset($data['counted']) || $data['counted'] === '')
                    continue;
                $counted = (int) $data['counted'];

                $stmt = $pdo->prepare("SELECT quantite, materiel_id, date_peremption FROM stocks WHERE id = ?");
                $stmt->execute([$stock_id]);
                $current = $stmt->fetch();
                if (!$current)
                    continue;

                $mat_id = $current['materiel_id'];
                $qte_avant = $current['quantite'];
                $motif = $data['motif'] ?? '';
                $added_qty = (int) ($data['added_qty'] ?? 0);
                $reserve_stock_id = $data['reserve_stock_id'] ?? '';
                $added_date = !empty($data['added_date']) ? $data['added_date'] : null;

                $qte_apres_totale = $counted + $added_qty;
                $action_log = null;

                if ($qte_avant != $counted || $added_qty > 0) {
                    if ($counted == 0) {
                        $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$stock_id]);
                    } else {
                        $pdo->prepare("UPDATE stocks SET quantite = ? WHERE id = ?")->execute([$counted, $stock_id]);
                    }

                    if ($added_qty > 0) {
                        if ($est_reserve) {
                            $action_log = "Appoint externe (+$added_qty)";
                        } else {
                            if (is_numeric($reserve_stock_id)) {
                                $stmt_res = $pdo->prepare("SELECT id, quantite, date_peremption, lieu_id FROM stocks WHERE id = ?");
                                $stmt_res->execute([$reserve_stock_id]);
                                $reserve_lot = $stmt_res->fetch();

                                if ($reserve_lot) {
                                    $added_date = $reserve_lot['date_peremption'];
                                    if ($reserve_lot['quantite'] <= $added_qty) {
                                        $pdo->prepare("DELETE FROM stocks WHERE id = ?")->execute([$reserve_lot['id']]);
                                    } else {
                                        $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE id = ?")->execute([$added_qty, $reserve_lot['id']]);
                                    }
                                    $nom_res = $pdo->query("SELECT nom FROM lieux_stockage WHERE id = " . $reserve_lot['lieu_id'])->fetchColumn() ?: 'Réserve';
                                    $action_log = "Recomplété (+$added_qty) depuis '$nom_res'";
                                }
                            } else {
                                $action_log = "Recomplété (+$added_qty) par saisie manuelle externe";
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

                    if ($qte_avant != $counted) {
                        $diff = $counted - $qte_avant;
                        $signe = $diff > 0 ? '+' : '';
                        $txt_base = ($est_reserve && $motif === 'keep_gap') ? "Ajustement de routine ($signe$diff)" : "Base corrigée ($signe$diff)";
                        $action_log = $action_log ? "$txt_base | $action_log" : $txt_base;
                    }

                    $stmt_hist = $pdo->prepare("INSERT INTO historique_comptages (inventaire_id, lieu_id, materiel_id, qte_avant, qte_apres, action_corrective) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$inv_id, $lieu_id, $mat_id, $qte_avant, $qte_apres_totale, $action_log]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erreur de base de données : " . $e->getMessage());
        }
    }
    $pdo->prepare("INSERT OR IGNORE INTO inventaires_lieux (inventaire_id, lieu_id) VALUES (?, ?)")->execute([$inv_id, $lieu_id]);
    logAction($pdo, "A inventorié le lieu : " . $lieu_info['nom']);
    header("Location: inventaire?msg=lieu_valide");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cloture')
        $message = '<div class="alert alert-success">🎉 Félicitations ! L\'inventaire est clôturé et enregistré.</div>';
    if ($_GET['msg'] === 'lieu_valide')
        $message = '<div class="alert alert-success">✅ Le lieu a été inventorié avec succès !</div>';
}

require_once 'includes/header.php';

// ==========================================
// VUE RAPPORT DES ÉCARTS
// ==========================================
if ($action === 'rapport') {
    $inv_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $stmt_inv = $pdo->prepare("SELECT * FROM inventaires WHERE id = ?");
    $stmt_inv->execute([$inv_id]);
    $inv = $stmt_inv->fetch();

    if (!$inv) {
        echo "<div class='alert alert-danger'>Rapport introuvable.</div>";
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
            .topbar,
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
        <div class="flex-between-start border-bottom pb-15 mb-20">
            <div>
                <a href="inventaire" class="no-print text-muted text-md" style="text-decoration: none;">⬅ Retour au
                    résumé</a>
                <h2 class="page-title mt-10">📊 Rapport d'inventaire</h2>
                <p class="mt-5 mb-0 text-muted font-italic">Clôturé le
                    <?php echo date('d/m/Y à H:i', strtotime($inv['date_fin'])); ?>
                </p>
            </div>
            <div class="no-print flex-center">
                <a href="inventaire?action=export_rapport_xls&id=<?php echo $inv_id; ?>" class="btn btn-success-dark">📥
                    Export Excel Complet du Rapport</a>
                <button onclick="window.print()" class="btn" style="background-color: #333; color: white;">🖨️ Imprimer /
                    PDF</button>
            </div>
        </div>

        <h3 style="color: #c62828;">Écarts de la base de données corrigés</h3>
        <?php if (empty($diffs)): ?>
            <div class="alert alert-success text-center">Aucune erreur d'inventaire n'a été constatée.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table-manager mb-30" style="border: 1px solid #ddd;">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Lieu</th>
                            <th style="width: 30%;">Matériel</th>
                            <th class="text-center" style="width: 10%;">Avant</th>
                            <th class="text-center" style="width: 10%;">Après</th>
                            <th class="text-left" style="width: 30%;">Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diffs as $d):
                            $ecart = $d['qte_apres'] - $d['qte_avant'];
                            $couleur_ecart = $ecart > 0 ? '#2e7d32' : '#c62828';
                            $signe = $ecart > 0 ? '+' : ''; ?>
                            <tr>
                                <td class="font-bold" style="border-right: 1px solid #eee;">
                                    <?php echo $d['lieu_icone'] . ' ' . htmlspecialchars($d['lieu_nom']); ?>
                                </td>
                                <td style="border-right: 1px solid #eee;"><?php echo htmlspecialchars($d['materiel_nom']); ?></td>
                                <td class="text-center text-muted" style="border-right: 1px solid #eee;">
                                    <?php echo $d['qte_avant']; ?>
                                </td>
                                <td class="text-center font-bold" style="border-right: 1px solid #eee;">
                                    <?php echo $d['qte_apres']; ?>
                                    <div class="text-sm" style="color: <?php echo $couleur_ecart; ?>;">
                                        (<?php echo $signe . $ecart; ?>)
                                    </div>
                                </td>
                                <td class="text-sm text-muted">
                                    <?php echo $d['action_corrective'] ? htmlspecialchars($d['action_corrective']) : 'Ajustement manuel'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="section-title mt-40">📋 Inventaire Détaillé Complet</h3>
        <?php if (empty($inventory_by_lieu)): ?>
            <p class="text-center text-muted font-italic">Le stock total est entièrement vide.</p>
        <?php else: ?>
            <?php foreach ($inventory_by_lieu as $lieu_nom => $articles): ?>
                <div style="page-break-inside: avoid; margin-bottom: 20px;">
                    <h4
                        style="background-color: #e0e0e0; color: #333; padding: 8px 15px; border-radius: 4px 4px 0 0; margin: 0; font-size: 15px; border: 1px solid #ccc; border-bottom: none;">
                        <?php echo htmlspecialchars($articles[0]['lieu_icone'] . ' ' . $lieu_nom); ?>
                    </h4>
                    <div class="table-responsive">
                        <table class="table-manager mb-10" style="border: 1px solid #ccc;">
                            <thead>
                                <tr>
                                    <th style="width: 25%; border-right: 1px solid #ccc;">Catégorie</th>
                                    <th style="width: 45%; border-right: 1px solid #ccc;">Matériel</th>
                                    <th class="text-center" style="width: 15%; border-right: 1px solid #ccc;">Péremption</th>
                                    <th class="text-center" style="width: 15%;">Quantité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $art): ?>
                                    <tr>
                                        <td class="text-sm" style="border-right: 1px solid #eee;">
                                            <?php echo htmlspecialchars($art['categorie_nom']); ?>
                                        </td>
                                        <td class="text-sm font-bold" style="border-right: 1px solid #eee;">
                                            <?php echo htmlspecialchars($art['materiel_nom']); ?>
                                        </td>
                                        <td class="text-center text-sm text-muted" style="border-right: 1px solid #eee;">
                                            <?php echo $art['date_peremption'] ? date('d/m/Y', strtotime($art['date_peremption'])) : '-'; ?>
                                        </td>
                                        <td class="text-center text-md font-bold"><?php echo $art['quantite']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// VUE INTERFACE DE COMPTAGE D'UN LIEU
// ==========================================
elseif ($inventaire_actif && $action === 'comptage' && $lieu_id > 0) {
    if (!$peut_editer) {
        header('Location: inventaire');
        exit;
    }

    $stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
    $stmt_lieu->execute(['id' => $lieu_id]);
    $lieu = $stmt_lieu->fetch();
    if (!$lieu) {
        header('Location: inventaire');
        exit;
    }

    $est_reserve = $lieu['est_reserve'] == 1;

    $est_malle_radio = estTypeRadio($lieu['nom']);
    $est_sac_dsa = estTypeDSA($lieu['nom']);

    $stmt_stocks = $pdo->prepare("
        SELECT s.id as stock_id, s.quantite, s.date_peremption, s.poche, 
               m.id AS materiel_id, m.nom AS materiel_nom, m.check_fonctionnel, c.nom AS categorie_nom 
        FROM stocks s 
        JOIN materiels m ON s.materiel_id = m.id 
        JOIN categories c ON m.categorie_id = c.id 
        WHERE s.lieu_id = :lieu_id 
        ORDER BY CASE WHEN s.poche = '' OR s.poche IS NULL THEN 1 ELSE 0 END, s.poche ASC, c.nom ASC, m.nom ASC
    ");
    $stmt_stocks->execute(['lieu_id' => $lieu_id]);
    $stocks = $stmt_stocks->fetchAll();

    $stocks_par_groupe = [];
    foreach ($stocks as $stock) {
        if (!empty($stock['poche'])) {
            $nom_groupe = "🎒 Poche : " . $stock['poche'];
        } else {
            $nom_groupe = "📦 " . $stock['categorie_nom'];
        }
        $stocks_par_groupe[$nom_groupe][] = $stock;
    }

    $reserves_par_materiel = [];
    if (!$est_reserve) {
        $stmt_reserves = $pdo->query("SELECT s.id as reserve_stock_id, s.materiel_id, s.quantite, s.date_peremption, l.nom as lieu_nom FROM stocks s JOIN lieux_stockage l ON s.lieu_id = l.id WHERE l.est_reserve = 1 AND s.quantite > 0 ORDER BY s.date_peremption ASC");
        foreach ($stmt_reserves->fetchAll() as $res) {
            $reserves_par_materiel[$res['materiel_id']][] = $res;
        }
    }
    ?>
    <script>window.reservesData = <?php echo json_encode($reserves_par_materiel); ?>;</script>
    <div class="white-box mb-20" style="position: sticky; top: 0; z-index: 100; border-bottom: 3px solid #d32f2f;">
        <div class="flex-between">
            <div>
                <a href="inventaire" class="text-muted text-md" style="text-decoration: none;">⬅ Annuler et retourner au
                    menu</a>
                <h2 class="page-title mt-5">📋 Pointage : <?php echo htmlspecialchars($lieu['nom']); ?>
                    <?php if ($est_reserve)
                        echo '<span class="badge badge-reserve ml-10">📦 RÉSERVE</span>'; ?>
                </h2>
            </div>
            <div class="flex-center">
                <div class="p-10 border-radius-4 text-center" style="background: #e8f5e9; border: 1px solid #c8e6c9;">
                    <div class="text-xl font-bold text-success" id="compteur-valides">0</div>
                    <div class="text-sm text-success" style="text-transform: uppercase;">Bons</div>
                </div>
                <div class="p-10 border-radius-4 text-center" style="background: #fff3e0; border: 1px solid #ffe0b2;">
                    <div class="text-xl font-bold text-warning" id="compteur-restants"><?php echo count($stocks); ?></div>
                    <div class="text-sm text-warning" style="text-transform: uppercase;">À vérifier</div>
                </div>
            </div>
        </div>
    </div>

    <form id="form-inventaire" method="POST" action="inventaire?action=comptage&lieu_id=<?php echo $lieu_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="valider_lieu" value="1">

        <div class="alert alert-warning mb-25">
            <strong class="text-danger">⚠️ AVERTISSEMENT :</strong> Le bouton de validation restera bloqué tant que <span
                class="text-danger font-bold">TOUTES</span> les vérifications obligatoires ne seront pas cochées.
        </div>

        <?php if (empty($stocks_par_groupe)): ?>
            <p class="text-center text-muted font-italic">Ce lieu est vide.</p>
        <?php else: ?>
            <?php foreach ($stocks_par_groupe as $nom_groupe => $articles): ?>
                <div class="category-block mb-30" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    <?php
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

                    <div class="table-responsive">
                        <table class="table-manager">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Matériel</th>
                                    <th class="text-center" style="width: 20%;">Péremption</th>
                                    <th class="text-center" style="width: 20%;">Théorique</th>
                                    <th class="text-center" style="width: 20%;">Compté</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $art):
                                    $sid = $art['stock_id'];
                                    $theo = $art['quantite'];
                                    $mat_id = $art['materiel_id'];

                                    $est_item_radio = estTypeRadio($art['materiel_nom']);
                                    $est_item_dsa = ($est_sac_dsa && estTypeDSA($art['materiel_nom']));
                                    $est_item_check = ($art['check_fonctionnel'] == 1);

                                    $est_item_special = ($est_item_radio || $est_item_dsa || $est_item_check);
                                    ?>
                                    <tr class="item-row" data-stock-id="<?php echo $sid; ?>" data-theo="<?php echo $theo; ?>"
                                        data-etat="vide" style="transition: background-color 0.3s;">
                                        <td class="font-bold text-dark"><?php echo htmlspecialchars($art['materiel_nom']); ?></td>
                                        <td class="text-center text-muted text-sm">
                                            <?php echo $art['date_peremption'] ? date('d/m/Y', strtotime($art['date_peremption'])) : '-'; ?>
                                        </td>
                                        <td class="text-center text-lg font-bold text-muted"><?php echo $theo; ?></td>
                                        <td class="text-center">
                                            <?php if ($est_item_radio): ?>
                                                <div class="text-left mt-10 mb-10"
                                                    style="display: inline-block; text-align: left; user-select: none;">
                                                    <label class="display-block mb-10 font-bold" style="cursor: pointer; font-size: 15px;">
                                                        <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                            onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>, 'inv', <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                            style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                        🔋 Batterie chargée
                                                    </label>
                                                    <label class="display-block mb-10 font-bold" style="cursor: pointer; font-size: 15px;">
                                                        <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                            onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>, 'inv', <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                            style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                        📻 Bon état de marche
                                                    </label>
                                                    <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                        <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                            onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>, 'inv', <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                            style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                        🔴 Appareil éteint
                                                    </label>
                                                </div>
                                                <input type="hidden" name="comptage[<?php echo $sid; ?>][counted]"
                                                    id="counted-<?php echo $sid; ?>" class="input-comptage"
                                                    data-attendu="<?php echo $theo; ?>" value="">
                                            <?php elseif ($est_item_dsa): ?>
                                                <div class="text-left mt-10 mb-10"
                                                    style="display: inline-block; text-align: left; user-select: none;">
                                                    <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                        <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                            onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>, 'inv', <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                            style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                        🔋 Niveau des piles OK
                                                    </label>
                                                </div>
                                                <input type="hidden" name="comptage[<?php echo $sid; ?>][counted]"
                                                    id="counted-<?php echo $sid; ?>" class="input-comptage"
                                                    data-attendu="<?php echo $theo; ?>" value="">
                                            <?php elseif ($est_item_check): ?>
                                                <div class="text-left mt-10 mb-10"
                                                    style="display: inline-block; text-align: left; user-select: none;">
                                                    <label class="display-block font-bold" style="cursor: pointer; font-size: 15px;">
                                                        <input type="checkbox" class="special-check-<?php echo $sid; ?>"
                                                            onchange="checkSpecial(<?php echo $sid; ?>, <?php echo $theo; ?>, 'inv', <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                            style="transform: scale(1.6); margin-right: 12px; cursor: pointer; vertical-align: middle;">
                                                        ⚙️ Fonctionnel.le
                                                    </label>
                                                </div>
                                                <input type="hidden" name="comptage[<?php echo $sid; ?>][counted]"
                                                    id="counted-<?php echo $sid; ?>" class="input-comptage"
                                                    data-attendu="<?php echo $theo; ?>" value="">
                                            <?php else: ?>
                                                <input type="number" min="0" name="comptage[<?php echo $sid; ?>][counted]"
                                                    class="input-comptage" data-attendu="<?php echo $theo; ?>"
                                                    oninput="checkDifferenceInv(this, <?php echo $est_reserve ? 'true' : 'false'; ?>)"
                                                    placeholder="?"
                                                    style="width: 80px; padding: 10px; font-size: 18px; text-align: center; border: 2px solid #ccc; border-radius: 6px; font-weight: bold; outline: none;">
                                            <?php endif; ?>
                                            <input type="hidden" name="comptage[<?php echo $sid; ?>][materiel_id]"
                                                value="<?php echo $mat_id; ?>">
                                        </td>
                                    </tr>

                                    <?php if (!$est_item_special): ?>
                                        <tr class="refill-row" id="refill-<?php echo $sid; ?>"
                                            style="display: none; background-color: #fdfaf6; border-bottom: 2px solid #ddd;">
                                            <td colspan="4" style="padding: 10px 10px 10px 40px; border-left: 4px solid #ef6c00;">
                                                <?php if ($est_reserve): ?>
                                                    <span class="missing-text-reserve text-warning font-bold text-md">↳ Écart constaté.</span>
                                                    <div class="reserve-tools flex-center mt-10 bg-white p-10 border-radius-4"
                                                        style="border: 1px solid #ccc; flex-wrap: wrap;">
                                                        <label class="text-sm font-bold text-dark">Action corrective :</label>
                                                        <select name="comptage[<?php echo $sid; ?>][motif]" class="select-motif-reserve"
                                                            style="padding: 6px; border: 1px solid #aaa; border-radius: 3px;"
                                                            onchange="toggleReserveMotif(this)">
                                                            <option value="keep_gap">Garder l'écart (Mise à jour de la base)</option>
                                                            <option value="appoint">Faire un appoint (Stock externe)</option>
                                                        </select>
                                                        <div class="reserve-extern-container flex-center" style="display: none;">
                                                            <label class="text-sm font-bold text-dark ml-10">Qté de l'appoint :</label>
                                                            <input type="number" name="comptage[<?php echo $sid; ?>][added_qty]"
                                                                class="input-added-qty" value="0" min="0"
                                                                style="width: 60px; padding: 6px; text-align: center; border: 1px solid #aaa; border-radius: 3px;">
                                                            <label class="text-sm font-bold text-dark ml-10">Nouv. Péremption :</label>
                                                            <input type="date" name="comptage[<?php echo $sid; ?>][added_date]"
                                                                class="input-added-date"
                                                                style="padding: 6px; border: 1px solid #aaa; border-radius: 3px;">
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="missing-text text-warning font-bold text-md">↳ Il manque unité(s).</span>
                                                    <div class="refill-tools flex-center mt-10 bg-white p-10 border-radius-4"
                                                        style="border: 1px solid #ccc; flex-wrap: wrap;">
                                                        <label class="text-sm font-bold text-dark">Choisir le lot :</label>
                                                        <select name="comptage[<?php echo $sid; ?>][reserve_stock_id]" class="input-reserve-lot"
                                                            style="padding: 6px; border: 1px solid #aaa; border-radius: 3px; max-width: 350px;"
                                                            onchange="updateMaxQtyInv(this)">
                                                            <option value="">-- Ne pas recompléter (Garder l'écart) --</option>
                                                            <?php
                                                            $lots = $reserves_par_materiel[$mat_id] ?? [];
                                                            foreach ($lots as $res):
                                                                $date_format = $res['date_peremption'] ? date('d/m/Y', strtotime($res['date_peremption'])) : 'Aucune';
                                                                $label = htmlspecialchars($res['lieu_nom']) . " | Pér: " . $date_format . " | Dispo: " . $res['quantite'];
                                                                ?>
                                                                <option value="<?php echo $res['reserve_stock_id']; ?>"
                                                                    data-max="<?php echo $res['quantite']; ?>"><?php echo $label; ?></option>
                                                            <?php endforeach; ?>
                                                            <option value="manual">Saisie manuelle externe</option>
                                                        </select>
                                                        <label class="text-sm font-bold text-dark ml-10">Qté ajoutée :</label>
                                                        <input type="number" name="comptage[<?php echo $sid; ?>][added_qty]"
                                                            class="input-added-qty" value="0" min="0" oninput="checkMaxQty(this)"
                                                            style="width: 60px; padding: 6px; text-align: center; border: 1px solid #aaa; border-radius: 3px;">
                                                        <div class="manual-date-container flex-center ml-10" style="display: none;">
                                                            <label class="text-sm font-bold text-dark">Nouv. Pér. :</label>
                                                            <input type="date" name="comptage[<?php echo $sid; ?>][added_date]"
                                                                class="input-added-date"
                                                                style="padding: 6px; border: 1px solid #aaa; border-radius: 3px;">
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="text-center mt-30 mb-30 pb-30">
            <button type="button" id="btn-valider-sac" onclick="validerFormulaireInventaire()"
                class="btn btn-success-dark btn-lg">✅ Valider ce lieu</button>
        </div>
    </form>

    <script>
        function checkSpecial(sid, theo, mode, isReserve = false) {
            let checks = document.querySelectorAll('.special-check-' + sid);
            let allChecked = true;
            checks.forEach(c => { if (!c.checked) allChecked = false; });
            let hiddenInput = document.getElementById('counted-' + sid);
            if (hiddenInput) {
                hiddenInput.value = allChecked ? theo : 0;
            }
            if (typeof checkDifferenceInv === 'function') {
                checkDifferenceInv(hiddenInput, isReserve);
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
        // ... ton code JS existant ...
        document.addEventListener('DOMContentLoaded', function () {
            checkGlobalSpecialState();
        });

        // --- NOUVEAU : Système de maintien de session (Ping) ---
        // Envoie une requête invisible au serveur toutes les 15 minutes (15 * 60 * 1000 millisecondes)
        setInterval(function() {
            fetch('ping.php')
                .then(response => {
                    if (!response.ok) {
                        console.warn("⚠️ Attention : Le maintien de session a échoué.");
                    } else {
                        console.log("🔄 Session d'inventaire prolongée avec succès.");
                    }
                })
                .catch(err => console.error("Erreur réseau lors du ping", err));
        }, 15 * 60 * 1000);
    </script>
    <?php
}
// ==========================================
// VUE SELECTION DES LIEUX
// ==========================================
elseif ($inventaire_actif) {
    echo $message;
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY est_reserve DESC, type, nom")->fetchAll();

    $stmt_faits = $pdo->prepare("SELECT lieu_id FROM inventaires_lieux WHERE inventaire_id = ?");
    $stmt_faits->execute([$inventaire_actif['id']]);
    $lieux_faits = $stmt_faits->fetchAll(PDO::FETCH_COLUMN);
    $tous_faits = (count($lieux_faits) === count($lieux) && count($lieux) > 0);

    $reserves_en_attente = false;
    foreach ($lieux as $l) {
        if ($l['est_reserve'] == 1 && !in_array($l['id'], $lieux_faits)) {
            $reserves_en_attente = true;
            break;
        }
    }
    ?>
    <div class="alert alert-warning flex-between">
        <div>
            <h2 class="mt-0 mb-5" style="color: #e65100;">INVENTAIRE EN COURS</h2>
            <p class="mt-0 mb-0 text-muted">Cliquez sur un lieu de stockage pour l'inventorier. <strong>L'inventaire des
                    réserves est obligatoire avant de pouvoir pointer les sacs.</strong></p>
        </div>
        <div class="text-xl font-bold text-warning">
            <span id="compteur-faits"><?php echo count($lieux_faits); ?></span> / <span
                id="compteur-total"><?php echo count($lieux); ?></span> faits
        </div>
    </div>

    <div class="flex-row mb-30 pb-30">
        <?php foreach ($lieux as $lieu):
            $est_fait = in_array($lieu['id'], $lieux_faits);
            $est_reserve = $lieu['est_reserve'] == 1;
            $verrouille = !$est_reserve && $reserves_en_attente;
            $icone = !empty($lieu['icone']) ? $lieu['icone'] : '🎒';
            ?>
            <div class="lieu-container" data-id="<?php echo $lieu['id']; ?>"
                data-nom="<?php echo htmlspecialchars($lieu['nom']); ?>">
                <?php if ($est_fait): ?>
                    <div class="card-sac text-success font-bold"
                        style="background-color: #e8f5e9; border-color: #4caf50; opacity: 0.7;">
                        <?php if ($est_reserve)
                            echo '<div class="badge-reserve-abs">📦 RÉSERVE</div>'; ?>
                        <div class="card-sac-icon">✅</div>
                        <strong class="text-lg display-block"
                            style="text-decoration: line-through;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                        <span class="text-sm mt-10 display-block">Déjà pointé</span>
                    </div>
                <?php elseif ($verrouille): ?>
                    <div class="card-sac text-muted" style="background-color: #f9f9f9; border-color: #ddd; opacity: 0.6;">
                        <div class="card-sac-icon">🔒</div>
                        <strong class="text-lg display-block"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                        <span class="text-sm font-bold text-danger mt-10 display-block">Veuillez inventorier les réserves en
                            premier</span>
                    </div>
                <?php else: ?>
                    <?php if ($peut_editer): ?>
                        <a href="inventaire?action=comptage&lieu_id=<?php echo $lieu['id']; ?>"
                            onclick="return confirm('Commencer l\'inventaire de ce lieu ?');" class="card-sac">
                            <?php if ($est_reserve)
                                echo '<div class="badge-reserve-abs">📦 RÉSERVE</div>'; ?>
                            <div class="card-sac-icon"><?php echo htmlspecialchars($icone); ?></div>
                            <strong class="text-lg display-block"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                            <span class="text-sm font-bold text-danger mt-10 display-block">👉 Faire ce lieu</span>
                        </a>
                    <?php else: ?>
                        <div class="card-sac text-muted" style="opacity: 0.6;">
                            <div class="card-sac-icon"><?php echo htmlspecialchars($icone); ?></div>
                            <strong class="text-lg display-block"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                            <span class="text-sm font-bold mt-10 display-block">En attente de pointage...</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center bg-white p-20 border-radius-4 mt-30"
        style="box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: <?php echo $tous_faits ? 'block' : 'none'; ?>;">
        <h3 class="text-success mt-0">🎉 Tous les lieux ont été inventoriés !</h3>
        <?php if ($peut_editer): ?>
            <a href="inventaire?action=cloturer"
                onclick="return confirm('Êtes-vous sûr de vouloir clôturer définitivement cet inventaire global ?');"
                class="btn btn-success-dark btn-lg">🔒 CLÔTURER L'INVENTAIRE GLOBAL</a>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// VUE RESUME
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
    <div class="flex-row mb-25">
        <div class="white-box flex-1 text-center mb-0" style="border-bottom: 4px solid #3498db;">
            <div class="text-sm text-muted" style="text-transform: uppercase;">Dans le catalogue</div>
            <div class="font-bold mt-10 mb-10" style="font-size: 36px; color: #3498db;"><?php echo $nb_catalog; ?></div>
            <div class="text-sm text-muted">Références distinctes</div>
        </div>
        <div class="white-box flex-1 text-center mb-0" style="border-bottom: 4px solid #9b59b6;">
            <div class="text-sm text-muted" style="text-transform: uppercase;">Total physique</div>
            <div class="font-bold mt-10 mb-10" style="font-size: 36px; color: #9b59b6;"><?php echo $nb_objets_total; ?>
            </div>
            <div class="text-sm text-muted">Objets en circulation</div>
        </div>
        <?php if ($dernier_inv): ?>
            <a href="inventaire.php?action=rapport&id=<?php echo $dernier_inv['id']; ?>"
                class="carte-animee white-box flex-1 text-center mb-0"
                style="text-decoration: none; border-bottom: 4px solid #f1c40f; display: block;">
                <div class="text-sm text-muted" style="text-transform: uppercase;">Dernier pointage</div>
                <div class="font-bold text-xl mt-15 mb-15 text-warning"><?php echo $date_affichage; ?></div>
                <div class="text-sm font-bold text-warning">👉 Voir le rapport</div>
            </a>
        <?php else: ?>
            <div class="white-box flex-1 text-center mb-0" style="border-bottom: 4px solid #f1c40f;">
                <div class="text-sm text-muted" style="text-transform: uppercase;">Dernier pointage</div>
                <div class="font-bold text-xl mt-15 mb-15 text-warning">Jamais réalisé</div>
                <div class="text-sm text-muted">Aucun historique</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center mb-30" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <?php if ($peut_editer): ?>
            <a href="inventaire.php?action=lancer"
                onclick="return confirm('Êtes-vous sûr de vouloir lancer un nouvel inventaire global ?');"
                class="btn btn-danger-dark btn-lg carte-animee">FAIRE L'INVENTAIRE</a>
        <?php endif; ?>
        <a href="inventaire.php?action=export_xls" class="btn btn-success-dark btn-lg carte-animee"
            style="background-color: #2e7d32; border-color: #1b5e20;">EXPORTER L'ÉTAT ACTUEL DES STOCKS (.xls)</a>
    </div>

    <div class="white-box">
        <h2 class="section-title">Répartition globale des stocks</h2>

        <div class="flex-row-sm align-center bg-white p-10 mb-20 border-radius-4" style="border: 1px solid #e0e0e0;">
            <strong class="text-dark">🔍 Chercher un matériel :</strong>
            <input type="text" id="searchRepartition" onkeyup="filtrerRepartition()"
                placeholder="Ex: Tensiomètre, compresses..." class="input-field flex-1 min-w-150 mb-0">
        </div>

        <?php if (empty($repartition_triee)): ?>
            <p class="text-center text-muted font-italic">Aucun matériel en stock.</p>
        <?php else: ?>
            <div id="liste-repartition">
                <?php foreach ($repartition_triee as $categorie => $materiels_liste): ?>
                    <div class="mb-30 repartition-category">
                        <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                        <h3 class="category-header"
                            style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                            <?php echo htmlspecialchars($categorie); ?>
                        </h3>
                        <div class="table-responsive">
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
                                        <tr class="repartition-row" style="border-bottom: 1px solid #eee;">
                                            <td class="font-bold text-dark mat-name" style="border-right: 1px solid #eee;">
                                                <?php echo htmlspecialchars($nom_mat); ?>
                                                <div class="text-sm text-muted mt-5">Total dispo :
                                                    <strong><?php echo $somme_totale_objet; ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-md text-muted">
                                                <div class="flex-row-sm">
                                                    <?php foreach ($lieux as $l): ?>
                                                        <span class="badge"
                                                            style="background: #f4f7f6; border: 1px solid #e0e0e0; color: #555;">
                                                            <strong><?php echo htmlspecialchars($l['lieu']); ?></strong> :
                                                            <?php echo $l['quantite']; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p id="noResultMsg" class="text-center text-muted font-italic" style="display: none;">Aucun matériel ne correspond à
                votre recherche.</p>
        <?php endif; ?>
    </div>

    <script>
        function filtrerRepartition() {
            let input = document.getElementById('searchRepartition').value.toLowerCase();
            let categories = document.querySelectorAll('.repartition-category');
            let hasGlobalResult = false;

            categories.forEach(cat => {
                let rows = cat.querySelectorAll('.repartition-row');
                let catHasVisibleRow = false;

                rows.forEach(row => {
                    let matName = row.querySelector('.mat-name').textContent.toLowerCase();

                    if (matName.includes(input)) {
                        row.style.display = '';
                        catHasVisibleRow = true;
                        hasGlobalResult = true;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // On cache la catégorie entière s'il n'y a aucun résultat dedans
                if (catHasVisibleRow) {
                    cat.style.display = 'block';
                } else {
                    cat.style.display = 'none';
                }
            });

            // Affichage du message si tout est caché
            let noResultMsg = document.getElementById('noResultMsg');
            if (noResultMsg) {
                noResultMsg.style.display = hasGlobalResult ? 'none' : 'block';
            }
        }
    </script>
    <?php
}
require_once 'includes/footer.php';
?>