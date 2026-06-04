<?php
// index.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$stmt_peremption = $pdo->query("SELECT COUNT(*) as alertes FROM stocks WHERE date_peremption IS NOT NULL AND date_peremption != '' AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$nb_alertes_peremption = $stmt_peremption->fetch()['alertes'];

$stmt_stocks_faibles = $pdo->query("SELECT COUNT(*) as alertes FROM (SELECT m.id, m.seuil_alerte, SUM(IFNULL(s.quantite, 0)) as total_stock FROM materiels m LEFT JOIN stocks s ON m.id = s.materiel_id GROUP BY m.id HAVING total_stock <= m.seuil_alerte AND m.seuil_alerte > 0) AS sous_requete");
$nb_alertes_stock = $stmt_stocks_faibles->fetch()['alertes'];

$stmt_dernier_inv = $pdo->query("SELECT date_fin FROM inventaires WHERE statut = 'termine' ORDER BY date_fin DESC LIMIT 1");
$dernier_inv = $stmt_dernier_inv->fetch();
$date_affichage_inv = $dernier_inv ? date('d/m/Y à H:i', strtotime($dernier_inv['date_fin'])) : 'Jamais réalisé';

$stmt_historique = $pdo->query("SELECT * FROM historique_actions ORDER BY date_action DESC LIMIT 10");
$historique = $stmt_historique->fetchAll();

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
    $stmt_stats = $pdo->prepare("
        SELECT COUNT(lieu_id) as total_sacs,
               SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as sacs_valides
        FROM evenements_lieux WHERE evenement_id = ?
    ");
    $stmt_stats->execute([$dps['id']]);
    $stats = $stmt_stats->fetch();
    $dps_stats[$dps['id']] = [
        'total'   => (int) $stats['total_sacs'],
        'valides' => (int) $stats['sacs_valides'],
    ];
}

require_once 'includes/header.php';
?>

<div class="white-box">
    <h2 class="dashboard-title">
        Tableau de bord - Bienvenue <?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?>
    </h2>
    <p class="dashboard-subtitle">Voici le résumé de l'état actuel du matériel de l'association.</p>
</div>

<!-- Cartes KPI -->
<div class="kpi-row">
    <a href="alertes.php" class="carte-animee white-box kpi-card kpi-card--red">
        <h3>⚠️ Alertes Péremption</h3>
        <p class="kpi-value"><?php echo $nb_alertes_peremption; ?> matériel(s)</p>
        <p class="kpi-label">Périmés ou &lt; 30 jours.</p>
    </a>
    <a href="alertes.php" class="carte-animee white-box kpi-card kpi-card--orange">
        <h3>📉 Stocks Faibles</h3>
        <p class="kpi-value"><?php echo $nb_alertes_stock; ?> référence(s)</p>
        <p class="kpi-label">Sous le seuil d'alerte.</p>
    </a>
    <a href="inventaire.php" class="carte-animee white-box kpi-card kpi-card--green">
        <h3>📋 Dernier Inventaire</h3>
        <p class="kpi-value"><?php echo $date_affichage_inv; ?></p>
        <p class="kpi-label">Cliquez pour voir ou lancer ➔</p>
    </a>
</div>

<!-- Prochains DPS -->
<div class="white-box mb-30 dps-box">
    <h2 class="section-title mt-0 dps-box__title">🚑 Prochains DPS</h2>

    <?php if (empty($prochains_dps)): ?>
        <p class="text-muted font-italic text-center p-20">Aucun DPS n'est prévu pour le moment.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-manager">
                <thead>
                    <tr>
                        <th style="width:40%;">Nom de l'événement</th>
                        <th style="width:20%; text-align:center;">Date</th>
                        <th style="width:40%;">Préparation du matériel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prochains_dps as $dps):
                        $stats = $dps_stats[$dps['id']];
                        $pct   = $stats['total'] > 0 ? round(($stats['valides'] / $stats['total']) * 100) : 0;
                        if ($pct == 100)    $bar_color = '#4caf50';
                        elseif ($pct > 0)   $bar_color = '#ff9800';
                        else                $bar_color = '#f44336';
                    ?>
                    <tr>
                        <td class="font-bold text-dark text-lg">
                            <a href="remplissage?action=view_event&id=<?php echo $dps['id']; ?>" class="dps-event-link">
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
                                <div class="dps-progress-header">
                                    <span class="text-sm font-bold" style="color:<?php echo $bar_color; ?>;">
                                        <?php echo $stats['valides']; ?> / <?php echo $stats['total']; ?> sacs validés
                                    </span>
                                    <span class="text-sm font-bold text-muted"><?php echo $pct; ?>%</span>
                                </div>
                                <div class="dps-progress-track">
                                    <div class="dps-progress-bar" style="width:<?php echo $pct; ?>%; background-color:<?php echo $bar_color; ?>;"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-right mt-15">
            <a href="remplissage" class="btn btn-sm btn-dps-all">Voir tous les DPS ➔</a>
        </div>
    <?php endif; ?>
</div>

<!-- Historique -->
<div class="white-box">
    <h3 class="historique-title">🕒 Dernières modifications</h3>

    <?php if (empty($historique)): ?>
        <p class="text-muted font-italic text-center p-20">Aucune action récente n'a été enregistrée pour le moment.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-manager">
                <thead>
                    <tr>
                        <th style="width:20%;">Date & Heure</th>
                        <th style="width:20%;">Utilisateur</th>
                        <th style="width:60%;">Action effectuée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $log): ?>
                    <tr>
                        <td class="historique-date"><?php echo date('d/m/Y à H:i', strtotime($log['date_action'])); ?></td>
                        <td class="historique-user">👤 <?php echo htmlspecialchars($log['nom_utilisateur']); ?></td>
                        <td class="historique-action"><?php echo htmlspecialchars($log['action']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
