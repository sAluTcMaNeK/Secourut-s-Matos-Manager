<?php
// alertes.php
require_once 'includes/auth.php';
require_once 'config/db.php';

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
      AND s.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY s.date_peremption ASC
");
$alertes_peremption = $stmt_peremption->fetchAll();

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

<div class="alertes-header">
    <h2>🚨 Centre d'Alertes</h2>
    <a href="index.php" class="btn-back">⬅ Retour au tableau de bord</a>
</div>

<div class="alertes-grid">

    <!-- Panneau Péremption -->
    <div class="alerte-panel alerte-panel--red">
        <h3>⚠️ Alertes Péremption (<?php echo count($alertes_peremption); ?>)</h3>
        <p class="desc">Matériel expiré ou expirant dans les 30 prochains jours.</p>

        <?php if (empty($alertes_peremption)): ?>
            <div class="alerte-ok">✅ Aucun matériel proche de la péremption !</div>
        <?php else: ?>
            <table class="alerte-table">
                <thead>
                    <tr>
                        <th>Où ?</th>
                        <th>Matériel</th>
                        <th class="center">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $aujourdhui = new DateTime();
                    foreach ($alertes_peremption as $alerte):
                        $date_peremp  = new DateTime($alerte['date_peremption']);
                        $est_perime   = $date_peremp < $aujourdhui;
                        $couleur_date = $est_perime ? '#c62828' : '#ef6c00';
                        $badge_class  = $est_perime ? 'badge-alerte--red' : 'badge-alerte--orange';
                        $badge_texte  = $est_perime ? 'PÉRIMÉ' : 'Bientôt';
                    ?>
                    <tr>
                        <td>
                            <a href="lieux.php?id=<?php echo $alerte['lieu_id']; ?>" class="alerte-lieu-link" title="Voir ce sac">
                                <?php echo $alerte['lieu_icone'] . ' ' . htmlspecialchars($alerte['lieu_nom']); ?>
                            </a>
                        </td>
                        <td>
                            <?php $coul = getCouleurCategorie($alerte['categorie_nom']); ?>
                            <span style="background-color:<?php echo $coul['bg']; ?>; color:<?php echo $coul['text']; ?>; padding:2px 6px; border-radius:4px; font-size:10px; margin-right:5px;">
                                <?php echo htmlspecialchars($alerte['categorie_nom']); ?>
                            </span>
                            <?php echo htmlspecialchars($alerte['materiel_nom']); ?> (x<?php echo $alerte['quantite']; ?>)
                        </td>
                        <td class="center font-bold text-md" style="color:<?php echo $couleur_date; ?>;">
                            <?php echo $date_peremp->format('d/m/Y'); ?><br>
                            <span class="badge-alerte <?php echo $badge_class; ?>"><?php echo $badge_texte; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Panneau Stocks Faibles -->
    <div class="alerte-panel alerte-panel--orange">
        <h3>📉 Stocks Faibles (<?php echo count($alertes_stock); ?>)</h3>
        <p class="desc">Matériel dont la quantité totale (réserve + sacs) est sous le seuil d'alerte.</p>

        <?php if (empty($alertes_stock)): ?>
            <div class="alerte-ok">✅ Tous les stocks sont au-dessus de leurs seuils !</div>
        <?php else: ?>
            <table class="alerte-table">
                <thead>
                    <tr>
                        <th>Matériel</th>
                        <th class="center">Stock actuel</th>
                        <th class="center">Seuil visé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertes_stock as $alerte):
                        $est_vide      = ($alerte['total_stock'] == 0);
                        $couleur_stock = $est_vide ? '#c62828' : '#ef6c00';
                    ?>
                    <tr>
                        <td>
                            <?php $coul = getCouleurCategorie($alerte['categorie_nom']); ?>
                            <span style="background-color:<?php echo $coul['bg']; ?>; color:<?php echo $coul['text']; ?>; padding:2px 6px; border-radius:4px; font-size:10px; margin-right:5px;">
                                <?php echo htmlspecialchars($alerte['categorie_nom']); ?>
                            </span>
                            <?php echo htmlspecialchars($alerte['materiel_nom']); ?>
                        </td>
                        <td class="center alerte-stock-value" style="color:<?php echo $couleur_stock; ?>;">
                            <?php echo $alerte['total_stock']; ?>
                            <?php if ($est_vide): ?>
                                <br><span class="badge-alerte badge-alerte--red">RUPTURE</span>
                            <?php endif; ?>
                        </td>
                        <td class="center text-muted"><?php echo $alerte['seuil_alerte']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
