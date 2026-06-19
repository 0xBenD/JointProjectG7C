<?php
require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
require 'vendor/autoload.php'; 

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt_check = $pdo->prepare("SELECT id FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt_check->execute([$user['id']]);

        if ($stmt_check->rowCount() > 0) {
            $error = "Une demande a déjà été envoyée il y a moins de 5 minutes. Veuillez patienter.";
        } else {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())");
            $stmt->execute([$user['id'], $token]);

            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp-jointprojectg7.alwaysdata.net';
            $mail->SMTPAuth = true;
            $mail->Username = 'jointprojectg7@alwaysdata.net';
            $mail->Password = 'muggek-veXfi2-gijkuw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('jointprojectg7@alwaysdata.net', 'Hangar G7 Support');
            $mail->addAddress($email);
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            $mail->Body = "Cliquez sur ce lien pour réinitialiser : https://jointprojectg7.alwaysdata.net/reset_password.php?token=$token";

            if($mail->send()) {
                $message = "Un email de réinitialisation a été envoyé à votre adresse.";
            } else {
                $error = "Erreur lors de l'envoi de l'email.";
            }
        }
    } else {
        $message = "Si cet email existe dans notre base, un lien a été envoyé.";
    }
}

include 'header.php';
?>

<div class="kpi-card" style="max-width: 450px; margin: 60px auto;">
    <h2 style="margin-top:0;">Mot de passe oublié</h2>
    <?php if($error) echo "<div style='color:var(--danger); margin-bottom:15px; font-weight:bold;'>$error</div>"; ?>
    <?php if($message) echo "<div style='color:var(--success); margin-bottom:15px; font-weight:bold;'>$message</div>"; ?>
    
    <form method="POST">
        <div class="form-section-group">
            <label class="form-label-style">Votre adresse e-mail</label>
            <input type="email" name="email" class="form-input-style" required>
        </div>
        <button type="submit" class="form-submit-button" style="width:100%;">Générer la clé de réinitialisation</button>
    </form>
</div>

<?php include 'footer.php'; ?>