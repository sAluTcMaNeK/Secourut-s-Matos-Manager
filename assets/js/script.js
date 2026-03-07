// assets/js/script.js

// ==========================================
// 1. FONCTIONS GLOBALES (Appelées par le HTML)
// ==========================================

// Menu Mobile
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('ouvert');
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
    }
}

// --- Page : MATERIEL.PHP ---
function ouvrirEdition(id, nom, categorie_id, fournisseur, seuil) {
    document.getElementById('bloc-ajout').style.display = 'none';
    document.getElementById('bloc-edition').style.display = 'block';

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nom').value = nom;
    document.getElementById('edit_categorie_id').value = categorie_id;
    document.getElementById('edit_fournisseur').value = fournisseur;
    document.getElementById('edit_seuil_alerte').value = seuil;

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function fermerEdition() {
    document.getElementById('bloc-edition').style.display = 'none';
    document.getElementById('bloc-ajout').style.display = 'block';
}

// --- Page : REMPLISSAGE.PHP ---
function toggleEdit(id, showEdit) {
    document.querySelectorAll('.view-mode-' + id).forEach(el => el.style.display = showEdit ? 'none' : '');
    document.querySelectorAll('.edit-mode-' + id).forEach(el => el.style.display = showEdit ? 'inline-block' : 'none');
}

function filtrerInventaire() {
    const searchInput = document.getElementById('searchBar');
    if (!searchInput) return; // Sécurité si on n'est pas sur la bonne page

    const search = searchInput.value.toLowerCase();
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
                else {
                    const pDate = new Date(peremp);
                    if (pDate > limitDate) show = false;
                }
            }
            row.style.display = show ? '' : 'none';
            if (show) hasVisibleRow = true;
        });
        block.style.display = hasVisibleRow ? '' : 'none';
    });
}

// --- Page : INVENTAIRE.PHP (Vue Comptage) ---
function verifierLigne(inputField) {
    const row = inputField.closest('.item-row');
    const valeurAttendue = parseInt(inputField.getAttribute('data-attendu'));
    const valeurSaisie = inputField.value;
    if (valeurSaisie === '') {
        row.style.backgroundColor = 'transparent';
        inputField.style.borderColor = '#ccc';
        row.setAttribute('data-etat', 'vide');
    }
    else if (parseInt(valeurSaisie) === valeurAttendue) {
        row.style.backgroundColor = '#e8f5e9';
        inputField.style.borderColor = '#4caf50';
        row.setAttribute('data-etat', 'bon');
    }
    else {
        row.style.backgroundColor = '#ffebee';
        inputField.style.borderColor = '#f44336';
        row.setAttribute('data-etat', 'erreur');
    }
    recalculerCompteurs();
}

function recalculerCompteurs() {
    const toutesLesLignes = document.querySelectorAll('.item-row');
    let nbBons = 0;
    toutesLesLignes.forEach(row => { if (row.getAttribute('data-etat') === 'bon') nbBons++; });
    const compteurValides = document.getElementById('compteur-valides');
    const compteurRestants = document.getElementById('compteur-restants');

    if (compteurValides) compteurValides.innerText = nbBons;
    if (compteurRestants) compteurRestants.innerText = (toutesLesLignes.length - nbBons);
}

function validerFormulaire() {
    const lignesVides = document.querySelectorAll('.item-row[data-etat="vide"]').length;
    if (lignesVides > 0) {
        if (!confirm(lignesVides + " articles n'ont pas été remplis. Leurs quantités théoriques seront conservées. Es-tu sûr de vouloir valider ce sac ?")) return;
    }
    else {
        if (!confirm("Tout a été pointé. Es-tu sûr de vouloir valider et clôturer l'inventaire de ce lieu ?")) return;
    }
    document.getElementById('form-inventaire').submit();
}

// ==========================================
// 2. SCRIPTS AUTOMATIQUES (Au chargement de la page)
// ==========================================
document.addEventListener('DOMContentLoaded', function () {

    // --- A. DÉCONNEXION AUTOMATIQUE (5 minutes) ---
    let inactivityTimer;
    const INACTIVITY_TIME = 300000; // 5 minutes en millisecondes

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(function () {
            window.location.href = 'logout.php';
        }, INACTIVITY_TIME);
    }

    // On ne lance la déconnexion automatique que si on n'est pas déjà sur la page login
    if (!document.body.classList.contains('login-page')) {
        document.onmousemove = resetTimer;
        document.onkeypress = resetTimer;
        document.onscroll = resetTimer;
        document.onclick = resetTimer;
        document.ontouchstart = resetTimer;
        resetTimer();
    }

    // --- B. SYNCHRONISATION EN TEMPS RÉEL (inventaire.php) ---
    const compteurTotalElement = document.getElementById('compteur-total');
    // On vérifie qu'on est bien sur la page d'inventaire global avant de lancer l'intervalle
    if (compteurTotalElement) {
        setInterval(function () {
            fetch('api_inventaire.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const lieuxFaits = data.lieux_faits;
                        const totalLieux = parseInt(compteurTotalElement.innerText);
                        let nombreFaitsActuel = 0;

                        document.querySelectorAll('.lieu-container').forEach(container => {
                            const id = parseInt(container.getAttribute('data-id'));
                            const nom = container.getAttribute('data-nom');
                            if (lieuxFaits.includes(id)) {
                                nombreFaitsActuel++;
                                if (!container.innerHTML.includes('✅')) {
                                    container.innerHTML = `
                                    <div style="display: block; width: 200px; padding: 20px; background-color: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; color: #2e7d32; text-align: center; opacity: 0.7; box-sizing: border-box;">
                                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                                        <strong style="font-size: 16px; display: block; text-decoration: line-through;">${nom}</strong>
                                        <span style="font-size: 12px; font-weight: bold; margin-top: 10px; display: block;">Déjà pointé</span>
                                    </div>
                                `;
                                }
                            }
                        });
                        document.getElementById('compteur-faits').innerText = nombreFaitsActuel;
                        if (nombreFaitsActuel >= totalLieux && totalLieux > 0) {
                            document.getElementById('zone-cloture').style.display = 'block';
                        }
                    }
                })
                .catch(error => console.error("Synchronisation...", error));
        }, 3000); // Toutes les 3 secondes
    }
});