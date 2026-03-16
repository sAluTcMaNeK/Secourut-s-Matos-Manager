<?php
// create_admin.php
require_once 'config/db.php';

// Tes identifiants (tu peux changer le mot de passe ici avant d'envoyer le fichier)
$nom_utilisateur = 'admin';
$mot_de_passe_en_clair = 'secouruts60@mde'; 
$role = 'admin';

// Le chiffrage sécurisé du mot de passe
$mot_de_passe_hache = password_hash($mot_de_passe_en_clair, PASSWORD_DEFAULT);

try {
    // On prépare la requête d'insertion
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES (:nom, :mdp, :role)");
    
    // On exécute la requête avec nos variables
    $stmt->execute([
        'nom' => $nom_utilisateur,
        'mdp' => $mot_de_passe_hache,
        'role' => $role
    ]);
    
    echo "<div style='font-family: Arial; padding: 20px;'>";
    echo "<h1 style='color: #2e7d32;'>✅ Compte créé !</h1>";
    echo "<p>Le compte administrateur a été généré avec succès.</p>";
    echo "<ul><li>Identifiant : <strong>$nom_utilisateur</strong></li><li>Mot de passe : <strong>$mot_de_passe_en_clair</strong></li></ul>";
    echo "<p>⚠️ <strong>Très important :</strong> Supprime ce fichier <code>create_admin.php</code> immédiatement pour que personne ne recrée un compte par-dessus !</p>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #d32f2f; color: white; text-decoration: none; border-radius: 4px;'>Aller à la page de connexion</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='font-family: Arial; color: red; padding: 20px;'>Erreur (le compte existe peut-être déjà ?) : " . $e->getMessage() . "</div>";
}
?>