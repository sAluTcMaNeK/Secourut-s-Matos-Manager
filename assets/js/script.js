// ==========================================
// 1. UTILITAIRES GLOBAUX & RECHERCHE
// ==========================================
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}

function checkMaxQty(inputElement) {
    const max = inputElement.getAttribute('max');
    if (max && parseInt(inputElement.value) > parseInt(max)) {
        inputElement.value = max;
        alert("Attention : Le lot sélectionné ne contient que " + max + " unités.");
    }
}

function filtrerInventaire() {
    let input = document.getElementById('searchBar');
    let filter = input ? input.value.toLowerCase() : '';
    let catSelect = document.getElementById('catFilter');
    let catFilter = catSelect ? catSelect.value.toLowerCase() : '';
    let expFilterCheckbox = document.getElementById('expFilter');
    let expFilter = expFilterCheckbox ? expFilterCheckbox.checked : false;

    let rows = document.querySelectorAll('.item-row');
    let today = new Date();
    let warningDate = new Date();
    warningDate.setMonth(today.getMonth() + 3);

    rows.forEach(row => {
        let nom = row.getAttribute('data-nom') || '';
        let datePerempStr = row.getAttribute('data-peremp') || '';
        let block = row.closest('.category-block');
        let cat = block ? (block.getAttribute('data-cat') || '').toLowerCase() : '';

        let textMatch = nom.includes(filter);
        let catMatch = (catFilter === "" || cat === catFilter);
        let expMatch = true;

        if (expFilter) {
            if (!datePerempStr) {
                expMatch = false;
            } else {
                let perempDate = new Date(datePerempStr);
                expMatch = (perempDate <= warningDate);
            }
        }

        row.style.display = (textMatch && catMatch && expMatch) ? '' : 'none';
    });

    document.querySelectorAll('.category-block').forEach(block => {
        let visibleRows = block.querySelectorAll('.item-row:not([style*="display: none"])');
        block.style.display = (visibleRows.length === 0) ? 'none' : 'block';
    });
}

function toggleEdit(id, isEditing) {
    let viewElems = document.querySelectorAll('.view-mode-' + id);
    let editElems = document.querySelectorAll('.edit-mode-' + id);

    if (isEditing) {
        viewElems.forEach(el => el.style.display = 'none');
        editElems.forEach(el => el.style.display = el.tagName.toLowerCase() === 'div' ? 'flex' : 'block');
    } else {
        viewElems.forEach(el => el.style.display = el.tagName.toLowerCase() === 'div' ? 'flex' : 'inline-block');
        editElems.forEach(el => el.style.display = 'none');

        // On cache la ligne de prélèvement de la réserve si on annule l'édition
        let refillRow = document.getElementById('edit-refill-' + id);
        if (refillRow) {
            refillRow.style.display = 'none';
            let selectLot = refillRow.querySelector('.input-reserve-lot');
            if (selectLot) selectLot.value = '';
        }

        // On réinitialise la quantité à son état d'origine
        let qtyInput = document.querySelector('.edit-mode-' + id + '.input-edit-qty');
        if (qtyInput) {
            qtyInput.value = qtyInput.getAttribute('data-old');
            qtyInput.style.borderColor = '#ccc';
        }
    }
}

// ==========================================
// 2. PAGE : VÉRIFICATION DPS (verification_sac.php)
// ==========================================
function checkDifferenceVerif(input) {
    const row = input.closest('.item-row');
    if (!row) return;
    const stockId = row.getAttribute('data-stock-id');
    const theo = parseInt(row.getAttribute('data-theo'));
    const estPerime = row.getAttribute('data-perime') === 'true';
    let counted = parseInt(input.value);
    if (isNaN(counted)) counted = 0;

    const refillRow = document.getElementById('refill-' + stockId);
    if (!refillRow) return;
    const missingDisplay = refillRow.querySelector('.missing-display');
    const selectLot = refillRow.querySelector('.input-reserve-lot');
    const optAcquitter = refillRow.querySelector('.opt-acquitter');

    if (counted < theo) {
        const missing = theo - counted;
        refillRow.style.display = 'table-row';
        if (missingDisplay) missingDisplay.textContent = missing;

        if (counted === 0 || estPerime) {
            if (optAcquitter) optAcquitter.style.display = 'none';
            if (selectLot && selectLot.value === 'acquitter') selectLot.value = '';
        } else {
            if (optAcquitter) optAcquitter.style.display = 'block';
            if (selectLot && !selectLot.value) selectLot.value = 'acquitter';
        }

        input.style.borderColor = '#ef6c00';
        if (selectLot) updateMaxQtyVerif(selectLot);

    } else {
        refillRow.style.display = 'none';
        if (selectLot) selectLot.value = 'acquitter';
        let addedQty = refillRow.querySelector('.input-added-qty');
        if (addedQty) addedQty.value = 0;
        input.style.borderColor = '#4caf50';
    }
}

function updateMaxQtyVerif(selectElement) {
    const container = selectElement.closest('.refill-row');
    if (!container) return;
    const qtyContainer = container.querySelector('.qty-container');
    const manualDateContainer = container.querySelector('.manual-date-container');
    const addedQtyInput = container.querySelector('.input-added-qty');

    const stockId = container.id.replace('refill-', '');
    const row = document.querySelector(`.item-row[data-stock-id="${stockId}"]`);
    if (!row) return;
    const theo = parseInt(row.getAttribute('data-theo'));
    const counted = parseInt(row.querySelector('.input-counted').value) || 0;
    const missing = theo - counted;

    if (selectElement.value === 'acquitter') {
        if (qtyContainer) qtyContainer.style.display = 'none';
        if (manualDateContainer) manualDateContainer.style.display = 'none';
        if (addedQtyInput) addedQtyInput.value = 0;
    } else if (selectElement.value === 'manual') {
        if (qtyContainer) qtyContainer.style.display = 'flex';
        if (manualDateContainer) manualDateContainer.style.display = 'flex';
        if (addedQtyInput) {
            addedQtyInput.removeAttribute('max');
            if (parseInt(addedQtyInput.value) === 0) addedQtyInput.value = missing;
        }
    } else if (selectElement.value === '') {
        if (qtyContainer) qtyContainer.style.display = 'none';
        if (manualDateContainer) manualDateContainer.style.display = 'none';
        if (addedQtyInput) addedQtyInput.value = 0;
    } else {
        if (qtyContainer) qtyContainer.style.display = 'flex';
        if (manualDateContainer) manualDateContainer.style.display = 'none';
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const max = selectedOption.getAttribute('data-max');
        if (addedQtyInput) {
            addedQtyInput.setAttribute('max', max);
            if (parseInt(addedQtyInput.value) === 0) addedQtyInput.value = missing;
            if (parseInt(addedQtyInput.value) > parseInt(max)) addedQtyInput.value = max;
        }
    }
    selectElement.style.borderColor = '#aaa';
}

function validerFormulaireVerification() {
    let sousEffectif = false;
    let nbLignesVides = 0;
    let erreurLotManquant = false;
    let blocageZero = false;
    let messageBlocage = "";

    document.querySelectorAll('.item-row').forEach(row => {
        const stockId = row.getAttribute('data-stock-id');
        const theo = parseInt(row.getAttribute('data-theo'));
        const nomMat = row.getAttribute('data-nom');
        const counted = parseInt(row.querySelector('.input-counted').value) || 0;

        const refillRow = document.getElementById('refill-' + stockId);
        if (!refillRow) return;
        const addedInput = refillRow.querySelector('.input-added-qty');
        const added = addedInput ? (parseInt(addedInput.value) || 0) : 0;
        const selectLot = refillRow.querySelector('.input-reserve-lot');

        if (counted === 0 && added === 0) {
            blocageZero = true;
            messageBlocage += `- ${nomMat}\n`;
        }

        if ((counted + added) < theo) {
            sousEffectif = true;
            nbLignesVides++;
        }

        if (added > 0 && selectLot && (selectLot.value === "" || selectLot.value === "acquitter")) {
            erreurLotManquant = true;
            selectLot.style.borderColor = '#f44336';
        }
    });

    if (blocageZero) {
        alert("⚠️ IMPOSSIBLE DE SCELLER.\nLes matériels suivants sont indispensables (manquants ou périmés) et n'ont pas été remplacés :\n\n" + messageBlocage + "\nVous devez obligatoirement sélectionner un lot pour les recompléter.");
        return;
    }
    if (erreurLotManquant) {
        alert("⚠️ Veuillez choisir un lot de réserve pour le matériel que vous rajoutez.");
        return;
    }
    if (sousEffectif) {
        if (!confirm(`⚠️ ATTENTION : Le sac n'est pas rempli à son niveau théorique (manque assumé sur ${nbLignesVides} ligne(s)).\n\nÊtes-vous sûr de vouloir sceller un sac incomplet pour ce DPS ?`)) return;
    } else {
        if (!confirm("Tout est en règle. Confirmez-vous le scellement définitif du sac ?")) return;
    }

    const form = document.getElementById('form-verification');
    if (form) form.submit();
}

// ==========================================
// 3. INITIALISATEUR AU CHARGEMENT DE LA PAGE
// ==========================================
document.addEventListener('DOMContentLoaded', function () {
    // Si on est sur la page de vérification DPS
    document.querySelectorAll('.input-counted').forEach(input => {
        checkDifferenceVerif(input);
    });
});

// ==========================================
// 4. PAGE : GESTION SAC (gestion_sac.php)
// ==========================================
function updateReserveOptions(matId) {
    const reserveSelect = document.getElementById('select-reserve-lot');
    if (!reserveSelect) return;

    reserveSelect.innerHTML = '';
    const qtyInput = document.getElementById('input-add-qty');
    if (qtyInput) qtyInput.removeAttribute('max');
    const dateContainer = document.getElementById('container-date-peremption');
    if (dateContainer) dateContainer.style.display = 'none';

    if (!matId) {
        reserveSelect.innerHTML = '<option value="">Sélectionnez d\'abord un matériel</option>';
        reserveSelect.disabled = true;
        return;
    }

    const lots = window.reservesData ? (window.reservesData[matId] || []) : [];
    reserveSelect.disabled = false;

    let optionsHtml = '';
    if (lots.length > 0) {
        lots.forEach(lot => {
            let dateSplit = lot.date_peremption ? lot.date_peremption.split('-') : null;
            let dateFormatee = dateSplit ? `${dateSplit[2]}/${dateSplit[1]}/${dateSplit[0]}` : 'Aucune';
            optionsHtml += `<option value="${lot.reserve_stock_id}" data-max="${lot.quantite}">${lot.lieu_nom} | Pér: ${dateFormatee} | Dispo: ${lot.quantite}</option>`;
        });
    }
    optionsHtml += '<option value="manual">Saisie manuelle externe (Hors base)</option>';
    reserveSelect.innerHTML = optionsHtml;
    toggleManualDate(reserveSelect.value);
}

function toggleManualDate(val) {
    const dateContainer = document.getElementById('container-date-peremption');
    const qtyInput = document.getElementById('input-add-qty');
    const reserveSelect = document.getElementById('select-reserve-lot');

    if (val === 'manual' || !val) {
        if (dateContainer) dateContainer.style.display = 'block';
        if (qtyInput) qtyInput.removeAttribute('max');
    } else {
        if (dateContainer) dateContainer.style.display = 'none';
        const selectedOption = reserveSelect.options[reserveSelect.selectedIndex];
        const max = selectedOption.getAttribute('data-max');
        if (max && qtyInput) {
            qtyInput.setAttribute('max', max);
            if (parseInt(qtyInput.value) > parseInt(max)) {
                qtyInput.value = max;
            }
        }
    }
}

// Nouvelles fonctions pour l'édition "en ligne" dans gestion_sac.php
function checkEditDifference(input, id, isReserve) {
    if (isReserve) return;

    let oldQty = parseInt(input.getAttribute('data-old'));
    let newQty = parseInt(input.value);
    if (isNaN(newQty)) newQty = 0;

    let refillRow = document.getElementById('edit-refill-' + id);
    if (!refillRow) return;

    let diffDisplay = refillRow.querySelector('.diff-display');
    let selectLot = refillRow.querySelector('.input-reserve-lot');

    if (newQty > oldQty) {
        refillRow.style.display = 'table-row';
        if (diffDisplay) diffDisplay.textContent = newQty - oldQty;
    } else {
        refillRow.style.display = 'none';
        if (selectLot) {
            selectLot.value = '';
            selectLot.style.borderColor = '#ccc';
        }
    }
}

function updateEditMaxQty(selectElement, id) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const max = selectedOption.getAttribute('data-max');
    let qtyInput = document.querySelector('.edit-mode-' + id + '.input-edit-qty');
    let oldQty = parseInt(qtyInput.getAttribute('data-old'));

    if (selectElement.value !== 'manual' && selectElement.value !== '') {
        let diff = parseInt(qtyInput.value) - oldQty;
        if (max && diff > parseInt(max)) {
            qtyInput.value = oldQty + parseInt(max);
            alert("Attention : Le lot sélectionné ne contient que " + max + " unités.");
            checkEditDifference(qtyInput, id, false);
        }
    }
    selectElement.style.borderColor = '#ccc';
}

function validateEdit(id, isReserve) {
    if (isReserve) return true;
    let qtyInput = document.querySelector('.edit-mode-' + id + '.input-edit-qty');
    let oldQty = parseInt(qtyInput.getAttribute('data-old'));
    let newQty = parseInt(qtyInput.value);

    if (newQty > oldQty) {
        let selectLot = document.querySelector('#edit-refill-' + id + ' .input-reserve-lot');
        if (selectLot && selectLot.value === "") {
            selectLot.style.borderColor = '#f44336';
            alert("⚠️ Veuillez sélectionner une réserve pour justifier d'où provient l'ajout de matériel.");
            return false;
        }
    }
    return true;
}

// ==========================================
// 5. PAGE : INVENTAIRE (inventaire.php)
// ==========================================
function checkDifferenceInv(input, isReserve) {
    const row = input.closest('.item-row');
    if (!row) return;
    const stockId = row.getAttribute('data-stock-id');
    const theo = parseInt(row.getAttribute('data-theo'));
    let counted = parseInt(input.value);

    if (isNaN(counted)) {
        row.style.backgroundColor = 'transparent';
        input.style.borderColor = '#ccc';
        row.setAttribute('data-etat', 'vide');
        const refillRow = document.getElementById('refill-' + stockId);
        if (refillRow) refillRow.style.display = 'none';
        recalculerCompteurs();
        return;
    }

    const refillRow = document.getElementById('refill-' + stockId);
    if (!refillRow) return;

    if (counted === theo) {
        row.style.backgroundColor = '#e8f5e9';
        input.style.borderColor = '#4caf50';
        row.setAttribute('data-etat', 'bon');
        refillRow.style.display = 'none';
        if (!isReserve) {
            const addedQty = refillRow.querySelector('.input-added-qty');
            if (addedQty) addedQty.value = 0;
        } else {
            const externContainer = refillRow.querySelector('.reserve-extern-container');
            if (externContainer) externContainer.style.display = 'none';
        }
    } else {
        row.style.backgroundColor = '#ffebee';
        input.style.borderColor = '#f44336';
        row.setAttribute('data-etat', 'erreur');
        refillRow.style.display = 'table-row';

        const diff = counted - theo;

        if (isReserve) {
            const motifSelect = refillRow.querySelector('.select-motif-reserve');
            const missingText = refillRow.querySelector('.missing-text-reserve');
            const diffQtyInput = refillRow.querySelector('.input-diff-qty-reserve');
            if (diffQtyInput) diffQtyInput.value = Math.abs(diff);

            if (diff > 0) {
                if (missingText) missingText.innerHTML = `↳ Vous avez trouvé <strong>${diff}</strong> unité(s) supplémentaire(s). Action corrective :`;
                if (motifSelect) {
                    motifSelect.innerHTML = `<option value="Ajustement de routine">Ajustement de routine (Même lot)</option><option value="Ajout de stock externe">Création d'un nouveau lot (Stock externe)</option>`;
                }
            } else {
                if (missingText) missingText.innerHTML = `↳ Il manque <strong>${Math.abs(diff)}</strong> unité(s). Action corrective :`;
                if (motifSelect) {
                    motifSelect.innerHTML = `<option value="Ajustement de routine">Ajustement de routine (Erreur de base)</option><option value="Matériel perdu, jeté ou périmé">Matériel perdu, jeté ou périmé</option>`;
                }
            }
            if (motifSelect) toggleReserveMotif(motifSelect);
        } else {
            const refillTools = refillRow.querySelector('.refill-tools');
            const missingText = refillRow.querySelector('.missing-text');
            const addedQtyInput = refillRow.querySelector('.input-added-qty');

            if (counted < theo) {
                if (missingText) missingText.innerHTML = `↳ Il manque <strong>${theo - counted}</strong> unité(s).`;
                if (refillTools) refillTools.style.display = 'flex';
            } else {
                if (missingText) missingText.innerHTML = `↳ Vous avez trouvé <strong>${counted - theo}</strong> unité(s) supplémentaire(s). L'inventaire sera mis à jour (+${counted - theo}).`;
                if (refillTools) refillTools.style.display = 'none';
                if (addedQtyInput) addedQtyInput.value = 0;
            }
        }
    }
    recalculerCompteurs();
}

function toggleReserveMotif(selectElement) {
    const container = selectElement.closest('.refill-row');
    if (!container) return;
    const externContainer = container.querySelector('.reserve-extern-container');
    if (selectElement.value === 'Ajout de stock externe') {
        if (externContainer) externContainer.style.display = 'flex';
    } else {
        if (externContainer) externContainer.style.display = 'none';
    }
}

function updateMaxQtyInv(selectElement) {
    const container = selectElement.closest('.refill-row');
    if (!container) return;
    const manualDateContainer = container.querySelector('.manual-date-container');
    const addedQtyInput = container.querySelector('.input-added-qty');

    if (selectElement.value === 'manual') {
        if (manualDateContainer) manualDateContainer.style.display = 'flex';
        if (addedQtyInput) addedQtyInput.removeAttribute('max');
    } else if (selectElement.value === '') {
        if (manualDateContainer) manualDateContainer.style.display = 'none';
        if (addedQtyInput) { addedQtyInput.removeAttribute('max'); addedQtyInput.value = 0; }
    } else {
        if (manualDateContainer) manualDateContainer.style.display = 'none';
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const max = selectedOption.getAttribute('data-max');
        if (addedQtyInput) {
            addedQtyInput.setAttribute('max', max);
            if (parseInt(addedQtyInput.value) > parseInt(max)) addedQtyInput.value = max;
        }
    }
    selectElement.style.borderColor = '#aaa';
}

function recalculerCompteurs() {
    const total = document.querySelectorAll('.item-row').length;
    const bons = document.querySelectorAll('.item-row[data-etat="bon"]').length;
    const erreurs = document.querySelectorAll('.item-row[data-etat="erreur"]').length;
    const restants = total - bons - erreurs;

    const validesEl = document.getElementById('compteur-valides');
    const restantsEl = document.getElementById('compteur-restants');

    if (validesEl) validesEl.textContent = bons + erreurs;
    if (restantsEl) restantsEl.textContent = restants;
}

function validerFormulaireInventaire() {
    let erreurLotManquant = false;
    document.querySelectorAll('.refill-row').forEach(refillRow => {
        if (refillRow.style.display !== 'none') {
            const selectLot = refillRow.querySelector('.input-reserve-lot');
            const addedQty = refillRow.querySelector('.input-added-qty');
            if (selectLot && addedQty) {
                if (parseInt(addedQty.value) > 0 && selectLot.value === "") {
                    erreurLotManquant = true;
                    selectLot.style.borderColor = '#f44336';
                } else if (selectLot) {
                    selectLot.style.borderColor = '#aaa';
                }
            }
        }
    });

    if (erreurLotManquant) {
        alert("⚠️ Veuillez choisir un lot de réserve pour le matériel que vous rajoutez (ou remettez la quantité ajoutée à 0).");
        return;
    }
    const lignesVides = document.querySelectorAll('.item-row[data-etat="vide"]').length;
    if (lignesVides > 0) {
        if (!confirm(lignesVides + " articles n'ont pas été remplis. Leurs quantités théoriques seront conservées. Valider ce lieu ?")) return;
    } else {
        if (!confirm("Tout a été pointé. Es-tu sûr de vouloir valider ce lieu ?")) return;
    }
    const form = document.getElementById('form-inventaire');
    if (form) form.submit();
}