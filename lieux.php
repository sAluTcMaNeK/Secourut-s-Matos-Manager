<?php
// lieux.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$peut_editer = ($_SESSION['can_edit'] === 1);

// RECUPERATION DYNAMIQUE DES PARAMETRES
$liste_types = [];
$liste_icones = [];
try {
    $liste_types = $pdo->query("SELECT nom FROM types_lieux ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
    $liste_icones = $pdo->query("SELECT icone FROM icones_lieux ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
}

if (empty($liste_types))
    $liste_types = ["Sac d'intervention", "Sac logistique", "Réserve"];
if (empty($liste_icones))
    $liste_icones = ['🎒', '🧳', '🚑', '🏥', '🏢', '💊', '🧊', '📦'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_editer) {
        $_SESSION['flash_error'] = "🛑 Action bloquée.";
        header("Location: lieux.php");
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer_lieu') {
        if (trim($_POST['confirmation_text']) === 'CONFIRMER') {
            $pdo->prepare("DELETE FROM lieux_stockage WHERE id = :id")->execute(['id' => (int) $_POST['lieu_id']]);
            $_SESSION['flash_success'] = "🗑️ Le stockage a été définitivement supprimé.";
        } else {
            $_SESSION['flash_error'] = "⚠️ Confirmation invalide.";
        }
        header("Location: lieux.php");
        exit;
    }

    if ($action === 'creer_lieu') {
        $nom = trim($_POST['nom']);
        if (!empty($nom) && !empty($_POST['type'])) {
            $pdo->prepare("INSERT INTO lieux_stockage (nom, type, icone, est_reserve) VALUES (?, ?, ?, ?)")
                ->execute([$nom, $_POST['type'], $_POST['icone'], isset($_POST['est_reserve']) ? 1 : 0]);
            $_SESSION['flash_success'] = "✅ Le nouveau stockage a été créé !";
        }
        header("Location: lieux.php");
        exit;
    }

    if ($action === 'modifier_lieu') {
        $id = (int) $_POST['lieu_id'];
        $nom = trim($_POST['nom']);
        if ($id > 0 && !empty($nom)) {
            $pdo->prepare("UPDATE lieux_stockage SET nom = ?, type = ?, icone = ?, est_reserve = ? WHERE id = ?")
                ->execute([$nom, $_POST['type'], $_POST['icone'], isset($_POST['est_reserve']) ? 1 : 0, $id]);
            $_SESSION['flash_success'] = "✅ Paramètres mis à jour !";
        }
        header("Location: lieux.php?id=" . $id);
        exit;
    }
}

$lieu_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
require_once 'includes/header.php';

// ==========================================
// MODE 1 : AFFICHAGE DU CONTENU D'UN SAC
// ==========================================
if ($lieu_id > 0) {
    $stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
    $stmt_lieu->execute(['id' => $lieu_id]);
    $lieu = $stmt_lieu->fetch();

    if (!$lieu)
        die("<div class='alert alert-danger'>Lieu introuvable.</div>");

    $stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom, s.date_peremption");
    $stmt_stocks->execute(['lieu_id' => $lieu_id]);
    $stocks = $stmt_stocks->fetchAll();

    $stocks_par_categorie = [];
    foreach ($stocks as $stock) {
        $stocks_par_categorie[$stock['categorie_nom']][] = $stock;
    }
    $icone_affichage = !empty($lieu['icone']) ? $lieu['icone'] : '🎒';
    ?>

    <div class="white-box">
        <div class="flex-between-start border-bottom pb-15 mb-20">
            <div>
                <a href="lieux.php" class="text-muted text-md" style="text-decoration: none;">⬅ Retour aux stockages</a>
                <h2 class="page-title mt-10">
                    <?php echo htmlspecialchars($icone_affichage); ?> Contenu :
                    <?php echo htmlspecialchars($lieu['nom']); ?>
                    <?php if ($lieu['est_reserve'] == 1): ?><span class="badge badge-reserve ml-10">📦
                            RÉSERVE</span><?php endif; ?>
                </h2>
            </div>
            <div class="flex-center">
                <?php if ($peut_editer): ?>
                    <button
                        onclick="document.getElementById('zone-edition-lieu').style.display = 'block'; document.getElementById('zone-suppression').style.display = 'none';"
                        class="btn btn-outline-primary">⚙️ Paramètres</button>
                    <button
                        onclick="document.getElementById('zone-suppression').style.display = 'block'; document.getElementById('zone-edition-lieu').style.display = 'none';"
                        class="btn btn-outline-danger">🗑️ Supprimer</button>
                <?php endif; ?>
                <a href="gestion_sac.php?lieu_id=<?php echo $lieu_id; ?>" class="btn btn-danger-dark">
                    <?php echo $peut_editer ? '✏️ Éditer / Remplir' : '👁️ Consulter le sac'; ?>
                </a>
            </div>
        </div>

        <?php if ($peut_editer): ?>
            <div id="zone-edition-lieu" class="form-box-edit" style="display: none;">
                <h3 class="mt-0 text-primary">⚙️ Modifier les informations de ce stockage</h3>
                <form action="lieux.php?id=<?php echo $lieu_id; ?>" method="POST" class="flex-row align-center">
                    <input type="hidden" name="action" value="modifier_lieu">
                    <input type="hidden" name="lieu_id" value="<?php echo $lieu_id; ?>">

                    <div class="flex-1 min-w-150">
                        <label class="font-bold text-md mb-5 display-block">Type</label>
                        <select name="type" required class="input-field mb-0">
                            <?php foreach ($liste_types as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($lieu['type'] === $t)
                                       echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-2 min-w-200">
                        <label class="font-bold text-md mb-5 display-block">Nom</label>
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($lieu['nom']); ?>" required
                            class="input-field mb-0">
                    </div>

                    <div class="flex-1 min-w-100">
                        <label class="font-bold text-md mb-5 display-block">Icône</label>
                        <select name="icone" class="input-field mb-0">
                            <?php foreach ($liste_icones as $ico): ?>
                                <option value="<?php echo htmlspecialchars($ico); ?>" <?php if ($lieu['icone'] === $ico)
                                       echo 'selected'; ?>><?php echo htmlspecialchars($ico); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-1 min-w-150 mt-10">
                        <label class="flex-center font-bold text-md p-10 bg-white border-radius-4"
                            style="border:1px solid #ccc; cursor:pointer;">
                            <input type="checkbox" name="est_reserve" value="1" <?php if ($lieu['est_reserve'] == 1)
                                echo 'checked'; ?>> 📦 Est une réserve
                        </label>
                    </div>

                    <div class="flex-center min-w-100" style="width: 100%;">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <button type="button" onclick="document.getElementById('zone-edition-lieu').style.display = 'none';"
                            class="btn btn-secondary">Annuler</button>
                    </div>
                </form>
            </div>

            <div id="zone-suppression" class="alert alert-warning-box p-20 mb-20" style="display: none;">
                <h3 class="text-warning mt-0">⚠️ Action irréversible</h3>
                <p class="font-normal text-dark">Saisissez <strong>CONFIRMER</strong> ci-dessous pour supprimer ce stockage :
                </p>
                <form action="lieux.php" method="POST" class="flex-center">
                    <input type="hidden" name="action" value="supprimer_lieu">
                    <input type="hidden" name="lieu_id" value="<?php echo $lieu_id; ?>">
                    <input type="text" name="confirmation_text" required placeholder="Tapez ici..."
                        class="input-field flex-1 mb-0">
                    <button type="submit" class="btn btn-danger-dark">Valider</button>
                    <button type="button" onclick="document.getElementById('zone-suppression').style.display = 'none';"
                        class="btn btn-secondary">Annuler</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="flex-row-sm align-center bg-white p-10 mb-20 border-radius-4" style="border: 1px solid #e0e0e0;">
            <strong class="text-dark">🔍 Filtrer :</strong>
            <input type="text" id="searchBar" onkeyup="filtrerInventaire()" placeholder="Rechercher un matériel..."
                class="input-field flex-1 min-w-150 mb-0">
            <select id="catFilter" onchange="filtrerInventaire()" class="input-field min-w-150 mb-0" style="flex: initial;">
                <option value="">Toutes les catégories</option>
                <?php foreach (array_keys($stocks_par_categorie) as $cat_nom): ?>
                    <option value="<?php echo htmlspecialchars($cat_nom); ?>"><?php echo htmlspecialchars($cat_nom); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex-center font-bold text-danger" style="cursor: pointer;">
                <input type="checkbox" id="expFilter" onchange="filtrerInventaire()"> ⚠️ Périme bientôt
            </label>
        </div>

        <?php if (empty($stocks_par_categorie)): ?>
            <p class="text-center text-muted font-italic p-20">Ce stockage est actuellement vide.</p>
        <?php else: ?>
            <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>
                <div class="category-block mb-30" data-cat="<?php echo htmlspecialchars($categorie); ?>"
                    style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?>
                    </h3>

                    <table class="table-manager">
                        <thead>
                            <tr>
                                <th style="width: 50%;">NOM DU MATÉRIEL</th>
                                <th class="text-center" style="width: 25%;">PÉREMPTION</th>
                                <th class="text-center" style="width: 25%;">QUANTITÉ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article):
                                $raw_date = $article['date_peremption'];
                                $affichage_date = $raw_date ? date('d/m/Y', strtotime($raw_date)) : '-';
                                ?>
                                <tr class="item-row" data-nom="<?php echo htmlspecialchars(strtolower($article['materiel_nom'])); ?>"
                                    data-peremp="<?php echo $raw_date; ?>">
                                    <td class="font-bold text-dark"><?php echo htmlspecialchars($article['materiel_nom']); ?></td>
                                    <td class="text-center text-muted"><?php echo $affichage_date; ?></td>
                                    <td class="text-center font-bold text-lg"><?php echo $article['quantite']; ?></td>
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
// MODE 2 : SÉLECTION DU LIEU ET CRÉATION
// ==========================================
else {
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY type, nom")->fetchAll();
    ?>
    <div class="flex-between border-bottom pb-10 mb-20">
        <h2 class="text-dark mt-0 mb-0">🎒 Lieux de stockage</h2>
        <?php if ($peut_editer): ?>
            <button onclick="document.getElementById('form-nouveau-lieu').style.display = 'block';" class="btn btn-primary">➕
                Ajouter un stockage</button>
        <?php endif; ?>
    </div>

    <?php if ($peut_editer): ?>
        <div id="form-nouveau-lieu" class="form-box-edit" style="display: none;">
            <h3 class="mt-0">Créer un nouveau stockage</h3>
            <form action="lieux.php" method="POST" class="flex-row align-center">
                <input type="hidden" name="action" value="creer_lieu">

                <div class="flex-1 min-w-150">
                    <label class="font-bold mb-5 display-block">Type</label>
                    <select name="type" required class="input-field mb-0">
                        <?php foreach ($liste_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-2 min-w-200">
                    <label class="font-bold mb-5 display-block">Nom du stockage</label>
                    <input type="text" name="nom" required class="input-field mb-0">
                </div>

                <div class="flex-1 min-w-100">
                    <label class="font-bold mb-5 display-block">Icône</label>
                    <select name="icone" class="input-field mb-0">
                        <?php foreach ($liste_icones as $ico): ?>
                            <option value="<?php echo htmlspecialchars($ico); ?>"><?php echo htmlspecialchars($ico); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-1 min-w-150 mt-10">
                    <label class="flex-center font-bold text-md p-10 bg-white border-radius-4"
                        style="border:1px solid #ccc; cursor:pointer;">
                        <input type="checkbox" name="est_reserve" value="1"> 📦 Réserve
                    </label>
                </div>

                <div class="flex-center" style="width: 100%;">
                    <button type="submit" class="btn btn-danger-dark">Valider</button>
                    <button type="button" onclick="document.getElementById('form-nouveau-lieu').style.display = 'none';"
                        class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="flex-row">
        <?php foreach ($lieux as $lieu):
            $type_affichage = $lieu['type'];
            if ($type_affichage === 'sac_inter')
                $type_affichage = 'Sac Intervention';
            elseif ($type_affichage === 'sac_log')
                $type_affichage = 'Sac Logistique';
            elseif ($type_affichage === 'reserve')
                $type_affichage = 'Réserve';
            $icone = !empty($lieu['icone']) ? $lieu['icone'] : '🎒';
            ?>
            <a href="lieux.php?id=<?php echo $lieu['id']; ?>"
                class="card-sac <?php echo ($lieu['est_reserve'] == 1) ? 'border-blue' : ''; ?>">
                <?php if ($lieu['est_reserve'] == 1): ?>
                    <div class="badge-reserve-abs">📦 RÉSERVE</div>
                <?php endif; ?>

                <div class="card-sac-icon"><?php echo htmlspecialchars($icone); ?></div>
                <strong class="text-lg display-block"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                <span class="text-sm text-muted"
                    style="text-transform: uppercase;"><?php echo htmlspecialchars($type_affichage); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
require_once 'includes/footer.php';
?>