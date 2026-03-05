<?php
// alertes.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// --- 1. RÉCUPÉRATION DES PÉREMPTIONS ---
// On cherche ce qui est périmé ou qui expire dans moins de 30 jours
$stmt_peremption = $pdo->query("
    SELECT s.id as stock_id, s.quantite, s.date_peremption, 
           m.nom AS materiel_nom, c.nom AS categorie_nom, 
           l.nom AS lieu_nom, l.icone AS lieu_icone, l.id AS lieu_id
    FROM stocks s
    JOIN materiels m ON s.materiel_id = m.id
    JOIN categories c ON m.categorie_id = c.id
    JOIN lieux_stockage l ON s.lieu_id = l.id
    WHERE s.date_peremption IS NOT NULL 
      AND s.date_peremption != '' 
      AND s.date_peremption <= date('now', '+30 days')
    ORDER BY s.date_peremption ASC
");
$alertes_peremption = $stmt_peremption->fetchAll();

// --- 2. RÉCUPÉRATION DES STOCKS FAIBLES ---
// On additionne tout le stock d'un matériel et on compare avec son seuil
$stmt_stocks_faibles = $pdo->query("
    SELECT m.nom AS materiel_nom, c.nom AS categorie_nom, 
           m.seuil_alerte, IFNULL(SUM(s.quantite), 0) as total_stock
    FROM materiels m
    JOIN categories c ON m.categorie_id = c.id
    LEFT JOIN stocks s ON m.id = s.materiel_id
    WHERE m.seuil_alerte > 0
    GROUP BY m.id
    HAVING total_stock <= m.seuil_alerte
    ORDER BY total_stock ASC, c.nom, m.nom
");
$alertes_stock = $stmt_stocks_faibles->fetchAll();

require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d32f2f; padding-bottom: 10px; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #333;">🚨 Centre d'Alertes</h2>
    <a href="index.php" style="color: #666; text-decoration: none; font-size: 14px; background: white; padding: 8px 15px; border-radius: 4px; border: 1px solid #ccc;">⬅ Retour au tableau de bord</a>
</div>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">

    <div style="flex: 1; min-width: 350px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 5px solid #c62828;">
        <h3 style="margin-top: 0; color: #c62828;">⚠️ Alertes Péremption (<?php echo count($alertes_peremption); ?>)</h3>
        <p style="color: #666; font-size: 14px;">Matériel expiré ou expirant dans les 30 prochains jours.</p>

        <?php if (empty($alertes_peremption)): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; text-align: center;">✅ Aucun matériel proche de la péremption !</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8f9fa; font-size: 12px; color: #666; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #ddd;">Où ?</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd;">Matériel</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd; text-align: center;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $aujourdhui = new DateTime();
                    foreach ($alertes_peremption as $alerte): 
                        $date_peremp = new DateTime($alerte['date_peremption']);
                        $est_perime = $date_peremp < $aujourdhui;
                        $couleur_date = $est_perime ? '#c62828' : '#ef6c00'; // Rouge si dépassé, orange si approche
                        $badge_texte = $est_perime ? 'PÉRIMÉ' : 'Bientôt';
                    ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <a href="lieux.php?id=<?php echo $alerte['lieu_id']; ?>" style="text-decoration: none; color: #333; font-weight: bold;" title="Voir ce sac">
                                    <?php echo $alerte['lieu_icone'] . ' ' . htmlspecialchars($alerte['lieu_nom']); ?>
                                </a>
                            </td>
                            <td style="padding: 10px;">
                                <?php $coul = getCouleurCategorie($alerte['categorie_nom']); ?>
                                <span style="background-color: <?php echo $coul['bg']; ?>; color: <?php echo $coul['text']; ?>; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 5px;">
                                    <?php echo htmlspecialchars($alerte['categorie_nom']); ?>
                                </span>
                                <?php echo htmlspecialchars($alerte['materiel_nom']); ?> (x<?php echo $alerte['quantite']; ?>)
                            </td>
                            <td style="padding: 10px; text-align: center; color: <?php echo $couleur_date; ?>; font-weight: bold; font-size: 14px;">
                                <?php echo $date_peremp->format('d/m/Y'); ?><br>
                                <span style="font-size: 10px; background-color: <?php echo $couleur_date; ?>; color: white; padding: 2px 5px; border-radius: 4px;"><?php echo $badge_texte; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="flex: 1; min-width: 350px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 5px solid #ef6c00;">
        <h3 style="margin-top: 0; color: #e65100;">📉 Stocks Faibles (<?php echo count($alertes_stock); ?>)</h3>
        <p style="color: #666; font-size: 14px;">Matériel dont la quantité totale (réserve + sacs) est sous le seuil d'alerte.</p>

        <?php if (empty($alertes_stock)): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; text-align: center;">✅ Tous les stocks sont au-dessus de leurs seuils !</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8f9fa; font-size: 12px; color: #666; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #ddd;">Matériel</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd; text-align: center;">Stock actuel</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd; text-align: center;">Seuil visé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertes_stock as $alerte): 
                        $est_vide = ($alerte['total_stock'] == 0);
                        $couleur_stock = $est_vide ? '#c62828' : '#ef6c00';
                    ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <?php $coul = getCouleurCategorie($alerte['categorie_nom']); ?>
                                <span style="background-color: <?php echo $coul['bg']; ?>; color: <?php echo $coul['text']; ?>; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 5px;">
                                    <?php echo htmlspecialchars($alerte['categorie_nom']); ?>
                                </span>
                                <?php echo htmlspecialchars($alerte['materiel_nom']); ?>
                            </td>
                            <td style="padding: 10px; text-align: center; color: <?php echo $couleur_stock; ?>; font-weight: bold; font-size: 16px;">
                                <?php echo $alerte['total_stock']; ?>
                                <?php if ($est_vide): ?><br><span style="font-size: 10px; background-color: #c62828; color: white; padding: 2px 5px; border-radius: 4px;">RUPTURE</span><?php endif; ?>
                            </td>
                            <td style="padding: 10px; text-align: center; color: #666;">
                                <?php echo $alerte['seuil_alerte']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>