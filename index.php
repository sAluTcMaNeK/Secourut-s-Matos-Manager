<?php
// index.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// --- 1. CRÉATION DE LA TABLE HISTORIQUE (Si elle n'existe pas) ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS historique_actions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom_utilisateur TEXT,
        action TEXT,
        date_action DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
}

// --- 2. CALCUL DES ALERTES ---
$stmt_peremption = $pdo->query("SELECT COUNT(*) as alertes FROM stocks WHERE date_peremption IS NOT NULL AND date_peremption != '' AND date_peremption <= date('now', '+30 days')");
$nb_alertes_peremption = $stmt_peremption->fetch()['alertes'];

$stmt_stocks_faibles = $pdo->query("SELECT COUNT(*) as alertes FROM (SELECT m.id, m.seuil_alerte, SUM(IFNULL(s.quantite, 0)) as total_stock FROM materiels m LEFT JOIN stocks s ON m.id = s.materiel_id GROUP BY m.id HAVING total_stock <= m.seuil_alerte AND m.seuil_alerte > 0)");
$nb_alertes_stock = $stmt_stocks_faibles->fetch()['alertes'];

// --- 3. RECHERCHE DU DERNIER INVENTAIRE TERMINÉ ---
$stmt_dernier_inv = $pdo->query("SELECT date_fin FROM inventaires WHERE statut = 'termine' ORDER BY date_fin DESC LIMIT 1");
$dernier_inv = $stmt_dernier_inv->fetch();
$date_affichage_inv = $dernier_inv ? date('d/m/Y à H:i', strtotime($dernier_inv['date_fin'])) : 'Jamais réalisé';

// --- 4. RÉCUPÉRATION DES DERNIÈRES MODIFICATIONS ---
// On prend les 10 dernières actions enregistrées
$stmt_historique = $pdo->query("SELECT * FROM historique_actions ORDER BY date_action DESC LIMIT 10");
$historique = $stmt_historique->fetchAll();

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

<div class="white-box">
    <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">🕒 Dernières
        modifications</h3>

    <?php if (empty($historique)): ?>
        <p style="color: #999; font-style: italic; text-align: center; padding: 20px;">Aucune action récente n'a été
            enregistrée pour le moment.</p>
    <?php else: ?>
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
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>