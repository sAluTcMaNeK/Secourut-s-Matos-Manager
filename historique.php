<?php
// historique.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// Sécurité : Réservé aux administrateurs
if (!$est_admin) {
    $_SESSION['flash_error'] = "🛑 Accès refusé. Réservé aux administrateurs.";
    header("Location: index.php");
    exit;
}

// Vidage de l'historique
if (isset($_POST['clear_history']) && $_POST['clear_history'] === 'CONFIRM') {
    $pdo->exec("DELETE FROM historique_actions");
    $_SESSION['flash_success'] = "🗑️ L'historique a été vidé avec succès.";
    header("Location: historique.php");
    exit;
}

$limit = 500;
$stmt = $pdo->query("SELECT * FROM historique_actions ORDER BY date_action DESC LIMIT $limit");
$logs = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="flex-between mb-20">
    <h2 class="page-title text-dark mb-0">📜 Historique des Actions</h2>
    <span class="badge badge-pill btn-danger-dark">Espace Administrateur</span>
</div>

<div class="white-box">
    <div class="flex-between-start border-bottom pb-15 mb-20">
        <div>
            <h3 class="mt-0 text-primary">Trace d'audit et Traçabilité</h3>
            <p class="text-muted text-sm mb-0">Affichage des <?php echo $limit; ?> dernières actions réalisées sur la base de données.</p>
        </div>
        <form method="POST" action="historique.php" onsubmit="return confirm('Êtes-vous sûr de vouloir vider TOUT l\'historique ? Cette action est irréversible.');" class="mb-0 flex-center">
            <input type="hidden" name="clear_history" value="CONFIRM">
            <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Vider l'historique</button>
        </form>
    </div>

    <table class="table-manager" style="border: 1px solid #eee;">
        <thead>
            <tr>
                <th style="width: 15%; border-right: 1px solid #eee;">Date & Heure</th>
                <th style="width: 20%; border-right: 1px solid #eee;">Utilisateur</th>
                <th style="width: 65%;">Action réalisée</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="3" class="text-center text-muted font-italic p-20">Aucune action enregistrée pour le moment.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td class="text-sm text-muted" style="border-right: 1px solid #eee;"><?php echo date('d/m/Y - H:i', strtotime($log['date_action'])); ?></td>
                        <td class="font-bold text-dark" style="border-right: 1px solid #eee;"><?php echo htmlspecialchars($log['nom_utilisateur']); ?></td>
                        <td class="text-md text-dark"><?php echo htmlspecialchars($log['action']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>