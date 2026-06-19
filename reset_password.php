<?php
require_once 'config.php';

$message = '';
$error = '';
$validToken = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $validToken = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pass, $reset['user_id']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            $message = "Mot de passe mis à jour avec succès ! Vous pouvez fermer cette page.";
            $validToken = false;
        }
    } else {
        $error = "Ce jeton est invalide ou a expiré.";
    }
} else {
    $error = "Aucun jeton fourni.";
}

include 'header.php';
?>

<div class="kpi-card" style="max-width: 450px; margin: 60px auto;">
    <h2 style="margin-top:0;">Nouveau mot de passe</h2>
    <?php if($error) echo "<div style='color:var(--danger); margin-bottom:15px; font-weight:bold;'>$error</div>"; ?>
    <?php if($message) echo "<div style='color:var(--success); margin-bottom:15px; font-weight:bold;'>$message</div>"; ?>
    
    <?php if($validToken): ?>
        <form method="POST">
            <div class="form-section-group">
                <label class="form-label-style">Saisissez votre nouveau mot de passe</label>
                <input type="password" name="password" class="form-input-style" placeholder="••••••••" required>
            </div>
            <button type="submit" class="form-submit-button" style="width:100%;">Confirmer le changement</button>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>