<?php
require_once 'config.php';
include 'header.php';

$message = '';
$error = '';
$valid_token = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Vérification de la validité du token
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $valid_token = true;
        
        // Traitement de la soumission du formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Mise à jour du mot de passe
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pass, $reset['user_id']]);
            
            // Suppression du token utilisé
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            
            $message = "Votre mot de passe a été mis à jour avec succès !";
            $valid_token = false; // On cache le formulaire
        }
    } else {
        $error = "Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.";
    }
} else {
    $error = "Aucun jeton de réinitialisation n'a été fourni.";
}
?>

<main style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px;">
    <div class="card" style="max-width: 450px; width: 100%; margin: 0 auto; text-align: center;">
        <h2 style="margin-top: 0; margin-bottom: 20px;">Réinitialisation du mot de passe</h2>
        
        <?php if($error): ?>
            <div style="color: var(--danger); background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= $error ?>
            </div>
            <a href="forgot_password.php" class="btn" style="background: var(--text-muted);">Refaire une demande</a>
        <?php endif; ?>
        
        <?php if($message): ?>
            <div style="color: var(--success); background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= $message ?>
            </div>
            <a href="connection.php" class="btn" style="width: 100%; box-sizing: border-box;">Aller à la page de connexion</a>
        <?php endif; ?>

        <?php if($valid_token): ?>
            <form method="POST" style="text-align: left;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" placeholder="Entrez un mot de passe sécurisé" required style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px;">
                </div>
                <button type="submit" class="btn" style="width: 100%; padding: 12px; font-size: 1.05em;">Confirmer le changement</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>