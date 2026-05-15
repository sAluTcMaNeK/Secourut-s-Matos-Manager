<?php
// includes/functions.php

/**
 * Vérifie si une chaîne (nom de sac, nom de matériel ou catégorie) concerne les Radios
 */
function estTypeRadio($chaine) {
    return (stripos($chaine, 'radio') !== false || stripos($chaine, 'talkie') !== false);
}

/**
 * Vérifie si une chaîne concerne les Défibrillateurs (DSA)
 */
function estTypeDSA($chaine) {
    return (stripos($chaine, 'dsa') !== false || 
            stripos($chaine, 'defibrillateur') !== false || 
            stripos($chaine, 'défibrillateur') !== false);
}
?>