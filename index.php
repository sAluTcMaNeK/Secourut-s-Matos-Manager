<?php
// index.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// --- 2. CALCUL DES ALERTES ---
$stmt_peremption = $pdo->query("SELECT COUNT(*) as alertes FROM stocks WHERE date_peremption IS NOT NULL AND date_peremption != '' AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$nb_alertes_peremption = $stmt_peremption->fetch()['alertes'];

$stmt_stocks_faibles = $pdo->query("SELECT COUNT(*) as alertes FROM (SELECT m.id, m.seuil_alerte, SUM(IFNULL(s.quantite, 0)) as total_stock FROM materiels m LEFT JOIN stocks s ON m.id = s.materiel_id GROUP BY m.id HAVING total_stock <= m.seuil_alerte AND m.seuil_alerte > 0) AS sous_requete");
$nb_alertes_stock = $stmt_stocks_faibles->fetch()['alertes'];

// --- 3. RECHERCHE DU DERNIER INVENTAIRE TERMINÉ ---
$stmt_dernier_inv = $pdo->query("SELECT date_fin FROM inventaires WHERE statut = 'termine' ORDER BY date_fin DESC LIMIT 1");
$dernier_inv = $stmt_dernier_inv->fetch();
$date_affichage_inv = $dernier_inv ? date('d/m/Y à H:i', strtotime($dernier_inv['date_fin'])) : 'Jamais réalisé';

// --- 4. RÉCUPÉRATION DES DERNIÈRES MODIFICATIONS ---
// On prend les 10 dernières actions enregistrées
$stmt_historique = $pdo->query("SELECT * FROM historique_actions ORDER BY date_action DESC LIMIT 10");
$historique = $stmt_historique->fetchAll();
// ==========================================
// RÉCUPÉRATION DES PROCHAINS DPS
// ==========================================
$stmt_dps = $pdo->query("
    SELECT id, nom, date_evenement 
    FROM evenements 
    WHERE DATE(date_evenement) >= CURRENT_DATE() 
    ORDER BY date_evenement ASC 
    LIMIT 5
");
$prochains_dps = $stmt_dps->fetchAll();

$dps_stats = [];
foreach ($prochains_dps as $dps) {
    // On compte le nombre total de sacs assignés et ceux qui sont scellés ("valide")
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(lieu_id) as total_sacs,
            SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as sacs_valides
        FROM evenements_lieux 
        WHERE evenement_id = ?
    ");
    $stmt_stats->execute([$dps['id']]);
    $stats = $stmt_stats->fetch();

    $dps_stats[$dps['id']] = [
        'total' => (int) $stats['total_sacs'],
        'valides' => (int) $stats['sacs_valides']
    ];
}

require_once 'includes/header.php';
?>

<div class="white-box">
    <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #d32f2f; padding-bottom: 10px;">
        Tableau de bord - Bienvenue <?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?>
    </h2>
    <p style="color: #666; margin-bottom: 0;">Voici le résumé de l'état actuel du matériel de l'association.</p>
</div>

<div style="display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap;">

    <a href="alertes.php" class="carte-animee white-box"
        style="flex: 1; min-width: 200px; margin-bottom: 0; border-left: 5px solid #d32f2f; text-decoration: none; display: block; padding: 15px;">
        <h3 style="margin: 0 0 10px 0; color: #c62828;">⚠️ Alertes Péremption</h3>
        <p style="font-size: 22px; font-weight: bold; margin: 0; color: #d32f2f;"><?php echo $nb_alertes_peremption; ?>
            matériel(s)</p>
        <p style="margin: 5px 0 0 0; font-size: 13px; color: #c62828;">Périmés ou < 30 jours.</p>
    </a>

    <a href="alertes.php" class="carte-animee white-box"
        style="flex: 1; min-width: 200px; margin-bottom: 0; border-left: 5px solid #ef6c00; text-decoration: none; display: block; padding: 15px;">
        <h3 style="margin: 0 0 10px 0; color: #ef6c00;">📉 Stocks Faibles</h3>
        <p style="font-size: 22px; font-weight: bold; margin: 0; color: #e65100;"><?php echo $nb_alertes_stock; ?>
            référence(s)</p>
        <p style="margin: 5px 0 0 0; font-size: 13px; color: #e65100;">Sous le seuil d'alerte.</p>
    </a>

    <a href="inventaire.php" class="carte-animee white-box"
        style="flex: 1; min-width: 200px; margin-bottom: 0; border-left: 5px solid #2e7d32; text-decoration: none; display: block; padding: 15px;">
        <h3 style="margin: 0 0 10px 0; color: #2e7d32;">📋 Dernier Inventaire</h3>
        <p style="font-size: 22px; font-weight: bold; margin: 0; color: #2e7d32;"><?php echo $date_affichage_inv; ?></p>
        <p style="margin: 5px 0 0 0; font-size: 13px; color: #2e7d32;">Cliquez pour voir ou lancer ➔</p>
    </a>
</div>
<div class="white-box mb-30" style="border-left: 5px solid #2196F3;">
    <h2 class="section-title mt-0" style="color: #1976D2; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">🚑
        Prochains DPS</h2>

    <?php if (empty($prochains_dps)): ?>
        <p class="text-muted font-italic text-center p-20">Aucun DPS n'est prévu pour le moment.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-manager" style="border: 1px solid #eee;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th style="width: 40%; text-align: left;">Nom de l'événement</th>
                        <th style="width: 20%; text-align: center;">Date</th>
                        <th style="width: 40%; text-align: left;">Préparation du matériel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prochains_dps as $dps):
                        $stats = $dps_stats[$dps['id']];
                        $pourcentage = $stats['total'] > 0 ? round(($stats['valides'] / $stats['total']) * 100) : 0;

                        // Déterminer la couleur de la barre de progression
                        $bar_color = '#f44336'; // Rouge (Rien n'est prêt)
                        if ($pourcentage == 100) {
                            $bar_color = '#4caf50'; // Vert (Tout est prêt)
                        } elseif ($pourcentage > 0) {
                            $bar_color = '#ff9800'; // Orange (En cours)
                        }
                        ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td class="font-bold text-dark" style="font-size: 16px;">
                                <a href="remplissage?action=view_event&id=<?php echo $dps['id']; ?>"
                                    style="text-decoration: none; color: #333;">
                                    <?php echo htmlspecialchars($dps['nom']); ?>
                                </a>
                            </td>

                            <td class="text-center text-muted font-bold">
                                <?php echo date('d/m/Y', strtotime($dps['date_evenement'])); ?>
                            </td>

                            <td>
                                <?php if ($stats['total'] == 0): ?>
                                    <span class="text-muted text-sm font-italic">Aucun sac assigné à ce poste</span>
                                <?php else: ?>
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <span class="text-sm font-bold" style="color: <?php echo $bar_color; ?>;">
                                            <?php echo $stats['valides']; ?> /
                                            <?php echo $stats['total']; ?> sacs validés
                                        </span>
                                        <span class="text-sm font-bold text-muted">
                                            <?php echo $pourcentage; ?>%
                                        </span>
                                    </div>
                                    <div
                                        style="width: 100%; background-color: #e0e0e0; border-radius: 4px; height: 10px; overflow: hidden;">
                                        <div
                                            style="width: <?php echo $pourcentage; ?>%; background-color: <?php echo $bar_color; ?>; height: 100%; transition: width 0.3s ease;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-right mt-15">
            <a href="remplissage" class="btn btn-sm"
                style="background-color: #2196F3; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">Voir
                tous les DPS ➔</a>
        </div>
    <?php endif; ?>
</div>

<div class="white-box">
    <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🕒 Dernières
        modifications</h3>

    <?php if (empty($historique)): ?>
        <p style="color: #999; font-style: italic; text-align: center; padding: 20px;">Aucune action récente n'a été
            enregistrée pour le moment.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-manager">
                <thead>
                    <tr>
                        <th style="width: 20%;">Date & Heure</th>
                        <th style="width: 20%;">Utilisateur</th>
                        <th style="width: 60%;">Action effectuée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $log): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="color: #666; font-size: 13px;">
                                <?php echo date('d/m/Y à H:i', strtotime($log['date_action'])); ?>
                            </td>
                            <td style="font-weight: bold; color: #2c3e50; font-size: 14px;">
                                👤 <?php echo htmlspecialchars($log['nom_utilisateur']); ?>
                            </td>
                            <td style="color: #444; font-size: 14px;">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>