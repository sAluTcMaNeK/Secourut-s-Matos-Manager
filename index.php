<?php
// index.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// --- CALCUL DES ALERTES ---
$stmt_peremption = $pdo->query("SELECT COUNT(*) as alertes FROM stocks WHERE date_peremption IS NOT NULL AND date_peremption != '' AND date_peremption <= date('now', '+30 days')");
$nb_alertes_peremption = $stmt_peremption->fetch()['alertes'];

$stmt_stocks_faibles = $pdo->query("SELECT COUNT(*) as alertes FROM (SELECT m.id, m.seuil_alerte, SUM(IFNULL(s.quantite, 0)) as total_stock FROM materiels m LEFT JOIN stocks s ON m.id = s.materiel_id GROUP BY m.id HAVING total_stock <= m.seuil_alerte AND m.seuil_alerte > 0)");
$nb_alertes_stock = $stmt_stocks_faibles->fetch()['alertes'];

require_once 'includes/header.php';
?>

<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">

    <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #d32f2f; padding-bottom: 10px;">
        Tableau de bord - Bienvenue <?php echo htmlspecialchars($_SESSION['username']); ?>
    </h2>

    <p>Navigue via le menu de gauche pour accéder aux différentes fonctionnalités.</p>

    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <a href="alertes.php" class="carte-animee" style="flex: 1; padding: 15px; background-color: #ffebee; border: 1px solid #ffebee; border-left: 5px solid #d32f2f; border-radius: 4px; text-decoration: none; display: block;">
            <h3 style="margin: 0 0 10px 0; color: #c62828;">⚠️ Alertes Péremption</h3>
            <p style="font-size: 18px; font-weight: bold; margin: 0; color: #d32f2f;"><?php echo $nb_alertes_peremption; ?> matériel(s)</p>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #c62828;">expiré(s) ou expirant dans moins de 30 jours.</p>
            <div style="margin-top: 10px; font-size: 12px; color: #c62828; text-align: right;">Voir les détails</div>
        </a>

        <a href="alertes.php" class="carte-animee" style="flex: 1; padding: 15px; background-color: #fff3e0; border: 1px solid #fff3e0; border-left: 5px solid #ef6c00; border-radius: 4px; text-decoration: none; display: block;">
            <h3 style="margin: 0 0 10px 0; color: #ef6c00;">📉 Stocks Faibles</h3>
            <p style="font-size: 18px; font-weight: bold; margin: 0; color: #e65100;"><?php echo $nb_alertes_stock; ?> référence(s)</p>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #e65100;">sous le seuil d'alerte configuré.</p>
            <div style="margin-top: 10px; font-size: 12px; color: #e65100; text-align: right;">Voir les détails</div>
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>