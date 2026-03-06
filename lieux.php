<?php
// lieux.php
require_once 'includes/auth.php';
require_once 'config/db.php';

$peut_editer = ($_SESSION['can_edit'] === 1);

try {
    $pdo->exec("ALTER TABLE lieux_stockage ADD COLUMN icone TEXT DEFAULT '🎒'");
} catch (PDOException $e) {
}

// ==========================================
// TRAITEMENT DES ACTIONS (PATTERN PRG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_editer) {
        $_SESSION['flash_error'] = "🛑 Action bloquée : Vous n'avez pas les droits de modification.";
        header("Location: lieux.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer_lieu') {
        $id_a_supprimer = (int) $_POST['lieu_id'];
        $confirmation = trim($_POST['confirmation_text']);
        if ($confirmation === 'CONFIRMER') {
            try {
                $pdo->prepare("DELETE FROM lieux_stockage WHERE id = :id")->execute(['id' => $id_a_supprimer]);
                $_SESSION['flash_success'] = "🗑️ Le stockage a été définitivement supprimé.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "❌ Erreur lors de la suppression.";
            }
        } else {
            $_SESSION['flash_error'] = "⚠️ Le mot 'CONFIRMER' est obligatoire pour valider la suppression.";
        }
        header("Location: lieux.php");
        exit;
    }

    if ($action === 'creer_lieu') {
        $nom = trim($_POST['nom']);
        $type = $_POST['type'];
        $icone = $_POST['icone'];
        if (!empty($nom) && !empty($type)) {
            try {
                $pdo->prepare("INSERT INTO lieux_stockage (nom, type, icone) VALUES (:nom, :type, :icone)")->execute(['nom' => $nom, 'type' => $type, 'icone' => $icone]);
                $_SESSION['flash_success'] = "✅ Le nouveau stockage a été créé !";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "❌ Erreur : Ce nom existe déjà.";
            }
        }
        header("Location: lieux.php");
        exit;
    }

    if ($action === 'modifier_lieu') {
        $id = (int) $_POST['lieu_id'];
        $nom = trim($_POST['nom']);
        $type = $_POST['type'];
        $icone = $_POST['icone'];
        if ($id > 0 && !empty($nom) && !empty($type)) {
            try {
                $pdo->prepare("UPDATE lieux_stockage SET nom = :nom, type = :type, icone = :icone WHERE id = :id")->execute(['nom' => $nom, 'type' => $type, 'icone' => $icone, 'id' => $id]);
                $_SESSION['flash_success'] = "✅ Paramètres mis à jour !";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "❌ Erreur lors de la modification.";
            }
        }
        header("Location: lieux.php?id=" . $id);
        exit;
    }
}

$lieu_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
require_once 'includes/header.php';

// ... SUITE DU CODE HTML

// ==========================================
// MODE 1 : AFFICHAGE DU CONTENU D'UN SAC
// ==========================================
if ($lieu_id > 0) {
    $stmt_lieu = $pdo->prepare("SELECT * FROM lieux_stockage WHERE id = :id");
    $stmt_lieu->execute(['id' => $lieu_id]);
    $lieu = $stmt_lieu->fetch();

    if (!$lieu) {
        echo "<div style='padding: 20px; color: red;'>Lieu introuvable.</div>";
        require_once 'includes/footer.php';
        exit;
    }

    $stmt_stocks = $pdo->prepare("SELECT s.id as stock_id, s.quantite, s.date_peremption, m.nom AS materiel_nom, c.nom AS categorie_nom FROM stocks s JOIN materiels m ON s.materiel_id = m.id JOIN categories c ON m.categorie_id = c.id WHERE s.lieu_id = :lieu_id ORDER BY c.nom, m.nom, s.date_peremption");
    $stmt_stocks->execute(['lieu_id' => $lieu_id]);
    $stocks = $stmt_stocks->fetchAll();

    $stocks_par_categorie = [];
    foreach ($stocks as $stock) {
        $stocks_par_categorie[$stock['categorie_nom']][] = $stock;
    }
    $icone_affichage = !empty($lieu['icone']) ? $lieu['icone'] : ($lieu['type'] === 'reserve' ? '🏢' : '🎒');
    ?>

    <div class="white-box">

        <div
            style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">
            <div>
                <a href="lieux.php" style="color: #666; text-decoration: none; font-size: 14px;">⬅ Retour aux stockages</a>
                <h2 style="margin: 10px 0 0 0; color: #d32f2f;">
                    <?php echo $icone_affichage; ?> Contenu : <?php echo htmlspecialchars($lieu['nom']); ?>
                </h2>
            </div>

            <div style="display: flex; gap: 10px;">
                <?php if ($peut_editer): ?>
                    <button
                        onclick="document.getElementById('zone-edition-lieu').style.display = 'block'; document.getElementById('zone-suppression').style.display = 'none';"
                        style="background-color: #fff; color: #2c3e50; border: 1px solid #2c3e50; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                        ⚙️ Paramètres
                    </button>
                    <button
                        onclick="document.getElementById('zone-suppression').style.display = 'block'; document.getElementById('zone-edition-lieu').style.display = 'none';"
                        style="background-color: #fff; color: #d32f2f; border: 1px solid #d32f2f; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                        🗑️ Supprimer
                    </button>
                <?php endif; ?>
                <a href="remplissage.php?lieu_id=<?php echo $lieu_id; ?>"
                    style="background-color: #d32f2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                    <?php echo $peut_editer ? '✏️ Éditer / Remplir' : '👁️ Consulter le sac'; ?>
                </a>
            </div>
        </div>

        <?php if ($peut_editer): ?>
            <div id="zone-edition-lieu"
                style="display: none; background-color: #f8f9fa; border: 1px solid #ccc; border-left: 5px solid #2c3e50; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3 style="margin-top: 0; color: #2c3e50;">⚙️ Modifier les informations de ce stockage</h3>
                <form action="lieux.php?id=<?php echo $lieu_id; ?>" method="POST" class="form-group-inline">
                    <input type="hidden" name="action" value="modifier_lieu">
                    <input type="hidden" name="lieu_id" value="<?php echo $lieu_id; ?>">

                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Catégorie</label>
                        <select name="type" required class="input-field">
                            <option value="sac_inter" <?php if ($lieu['type'] === 'sac_inter')
                                echo 'selected'; ?>>Sac
                                d'intervention</option>
                            <option value="sac_log" <?php if ($lieu['type'] === 'sac_log')
                                echo 'selected'; ?>>Sac Logistique
                            </option>
                            <option value="armoire" <?php if ($lieu['type'] === 'armoire')
                                echo 'selected'; ?>>Armoire / Réserve
                            </option>
                            <option value="boite" <?php if ($lieu['type'] === 'boite')
                                echo 'selected'; ?>>Boite</option>
                        </select>
                    </div>

                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Nom</label>
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($lieu['nom']); ?>" required
                            class="input-field">
                    </div>

                    <div style="flex: 1; min-width: 100px;">
                        <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 5px;">Icône</label>
                        <select name="icone" class="input-field">
                            <?php
                            $liste_icones = ['🎒', '🧳', '🚑', '🏥', '🏢', '💊', '🧊', '📦'];
                            foreach ($liste_icones as $ico) {
                                $selected = ($lieu['icone'] === $ico) ? 'selected' : '';
                                echo "<option value=\"$ico\" $selected>$ico</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit"
                            style="padding: 10px 20px; background-color: #2c3e50; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Enregistrer</button>
                        <button type="button" onclick="document.getElementById('zone-edition-lieu').style.display = 'none';"
                            style="padding: 10px 15px; background-color: #ccc; border: none; border-radius: 4px; cursor: pointer;">Annuler</button>
                    </div>
                </form>
            </div>

            <div id="zone-suppression"
                style="display: none; background-color: #fff3e0; border: 2px solid #ef6c00; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3 style="color: #e65100; margin-top: 0;">⚠️ Action irréversible</h3>
                <p>Saisissez <strong>CONFIRMER</strong> ci-dessous pour supprimer ce stockage :</p>
                <form action="lieux.php" method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="action" value="supprimer_lieu">
                    <input type="hidden" name="lieu_id" value="<?php echo $lieu_id; ?>">
                    <input type="text" name="confirmation_text" required placeholder="Tapez ici..." class="input-field"
                        style="flex: 1;">
                    <button type="submit"
                        style="background-color: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer;">Valider</button>
                    <button type="button" onclick="document.getElementById('zone-suppression').style.display = 'none';"
                        style="background: #ccc; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">Annuler</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="form-container"
            style="background: #fff; border: 1px solid #e0e0e0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <strong style="color: #333;">🔍 Filtrer :</strong>
            <input type="text" id="searchBar" onkeyup="filtrerInventaire()" placeholder="Rechercher un matériel..."
                class="input-field" style="flex: 1; min-width: 150px;">
            <select id="catFilter" onchange="filtrerInventaire()" class="input-field"
                style="min-width: 150px; flex: initial;">
                <option value="">Toutes les catégories</option>
                <?php foreach (array_keys($stocks_par_categorie) as $cat_nom): ?>
                    <option value="<?php echo htmlspecialchars($cat_nom); ?>"><?php echo htmlspecialchars($cat_nom); ?></option>
                <?php endforeach; ?>
            </select>
            <label
                style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #d32f2f; font-weight: bold;">
                <input type="checkbox" id="expFilter" onchange="filtrerInventaire()"> ⚠️ Périme bientôt
            </label>
        </div>

        <?php if (empty($stocks_par_categorie)): ?>
            <p style="color: #666; font-style: italic; text-align: center; padding: 20px;">Ce stockage est actuellement vide.
            </p>
        <?php else: ?>
            <?php foreach ($stocks_par_categorie as $categorie => $articles): ?>

                <div class="category-block" data-cat="<?php echo htmlspecialchars($categorie); ?>"
                    style="margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                    <?php $couleur = function_exists('getCouleurCategorie') ? getCouleurCategorie($categorie) : ['bg' => '#2c3e50', 'text' => 'white']; ?>
                    <h3 class="category-header"
                        style="background-color: <?php echo $couleur['bg']; ?>; color: <?php echo $couleur['text']; ?>;">
                        <?php echo htmlspecialchars($categorie); ?>
                    </h3>

                    <table class="table-manager">
                        <thead>
                            <tr>
                                <th style="width: 50%;">NOM DU MATÉRIEL</th>
                                <th style="text-align: center; width: 25%;">PÉREMPTION</th>
                                <th style="text-align: center; width: 25%;">QUANTITÉ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article):
                                $raw_date = $article['date_peremption'];
                                $affichage_date = $raw_date ? date('d/m/Y', strtotime($raw_date)) : '-';
                                ?>
                                <tr class="item-row" data-nom="<?php echo htmlspecialchars(strtolower($article['materiel_nom'])); ?>"
                                    data-peremp="<?php echo $raw_date; ?>" style="transition: background 0.2s;">
                                    <td style="font-weight: 500; color: #444;">
                                        <?php echo htmlspecialchars($article['materiel_nom']); ?>
                                    </td>
                                    <td style="text-align: center; color: #666;">
                                        <?php echo $affichage_date; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 16px; font-weight: bold;">
                                        <?php echo $article['quantite']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function filtrerInventaire() {
            const search = document.getElementById('searchBar').value.toLowerCase();
            const catFilter = document.getElementById('catFilter').value;
            const expFilter = document.getElementById('expFilter').checked;
            const limitDate = new Date();
            limitDate.setDate(limitDate.getDate() + 30);

            document.querySelectorAll('.category-block').forEach(block => {
                const blockCat = block.getAttribute('data-cat');
                let hasVisibleRow = false;
                block.querySelectorAll('.item-row').forEach(row => {
                    const nom = row.getAttribute('data-nom');
                    const peremp = row.getAttribute('data-peremp');
                    let show = true;
                    if (search && !nom.includes(search)) show = false;
                    if (catFilter && blockCat !== catFilter) show = false;
                    if (expFilter) {
                        if (!peremp) show = false;
                        else { const pDate = new Date(peremp); if (pDate > limitDate) show = false; }
                    }
                    row.style.display = show ? '' : 'none';
                    if (show) hasVisibleRow = true;
                });
                block.style.display = hasVisibleRow ? '' : 'none';
            });
        }
    </script>
    <?php
}
// ==========================================
// MODE 2 : SÉLECTION DU LIEU ET CRÉATION
// ==========================================
else {
    $lieux = $pdo->query("SELECT * FROM lieux_stockage ORDER BY type, nom")->fetchAll();
    ?>
    <div
        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d32f2f; padding-bottom: 10px; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #333;">🎒 Lieux de stockage</h2>
        <?php if ($peut_editer): ?>
            <button onclick="document.getElementById('form-nouveau-lieu').style.display = 'block';"
                style="background-color: #2c3e50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">➕
                Ajouter un stockage</button>
        <?php endif; ?>
    </div>

    <?php if ($peut_editer): ?>
        <div id="form-nouveau-lieu"
            style="display: none; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 30px; border-left: 5px solid #2c3e50;">
            <h3 style="margin-top: 0;">Créer un nouveau stockage</h3>
            <form action="lieux.php" method="POST" class="form-group-inline">
                <input type="hidden" name="action" value="creer_lieu">
                <div style="flex: 1; min-width: 150px;"><label
                        style="display: block; font-weight: bold;">Catégorie</label><select name="type" required
                        class="input-field">
                        <option value="sac_inter">Sac d'intervention</option>
                        <option value="sac_log">Sac Logistique</option>
                        <option value="armoire">Armoire</option>
                        <option value="boite">Boite</option>
                    </select></div>
                <div style="flex: 2; min-width: 200px;"><label style="display: block; font-weight: bold;">Nom du
                        stockage</label><input type="text" name="nom" required class="input-field"></div>
                <div style="flex: 1; min-width: 100px;"><label style="display: block; font-weight: bold;">Icône</label><select
                        name="icone" class="input-field">
                        <option value="🎒">🎒</option>
                        <option value="🧳">🧳</option>
                        <option value="🚑">🚑</option>
                        <option value="🏥">🏥</option>
                        <option value="🏢">🏢</option>
                        <option value="💊">💊</option>
                        <option value="🧊">🧊</option>
                        <option value="📦">📦</option>
                    </select></div>
                <div><button type="submit"
                        style="padding: 11px 20px; background-color: #d32f2f; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Valider</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <?php foreach ($lieux as $lieu): ?>
            <?php
            $type_affichage = ($lieu['type'] === 'reserve') ? 'Réserve' : (($lieu['type'] === 'sac_inter') ? 'Sac Intervention' : (($lieu['type'] === 'armoire') ? 'Armoire' : 'Sac Logistique'));
            $icone = !empty($lieu['icone']) ? $lieu['icone'] : '🎒';
            ?>
            <a href="lieux.php?id=<?php echo $lieu['id']; ?>" class="carte-animee"
                style="display: block; width: 200px; padding: 20px; background-color: white; border: 1px solid transparent; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-decoration: none; color: #333; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 10px;"><?php echo htmlspecialchars($icone); ?></div>
                <strong style="font-size: 16px; display: block;"><?php echo htmlspecialchars($lieu['nom']); ?></strong>
                <span style="font-size: 12px; color: #999; text-transform: uppercase;"><?php echo $type_affichage; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
require_once 'includes/footer.php';
?>