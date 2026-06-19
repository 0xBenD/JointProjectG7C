<?php
require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
// Utilise le chemin correct vers ton autoload PHPMailer
require 'vendor/autoload.php'; 

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // 1. Chercher l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Vérifier la fréquence (Seulement si l'utilisateur existe)
        // Vérifie si une ligne existe avec ce user_id depuis moins de 5 min
        $stmt_check = $pdo->prepare("SELECT id FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt_check->execute([$user['id']]);

        if ($stmt_check->rowCount() > 0) {
            $error = "Une demande a déjà été envoyée il y a moins de 5 minutes. Veuillez patienter.";
        } else {
            // 3. Procéder à la réinitialisation
            $token = bin2hex(random_bytes(32));
            
            // Nettoyage des anciennes demandes
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            // Insertion (Assure-toi que created_at est soit automatique, soit ajoute le ici)
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())");
            $stmt->execute([$user['id'], $token]);

            // SMTP
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
        // On affiche un message générique pour ne pas confirmer si l'email existe (Sécurité)
        $message = "Si cet email existe dans notre base, un lien a été envoyé.";
    }
}

include 'header.php';
?>

<div class="container" style="max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; border: 1px solid var(--border);">
    <h2>Mot de passe oublié</h2>
    <?php if($error) echo "<div style='color:red; margin-bottom:15px;'>$error</div>"; ?>
    <?php if($message) echo "<div style='color:green; margin-bottom:15px;'>$message</div>"; ?>
    
    <form method="POST">
        <div class="form-group" style="margin-bottom: 20px;">
            <label>Votre e-mail</label>
            <input type="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
        </div>
        <button type="submit" class="btn" style="width: 100%; padding: 10px;">Envoyer le lien</button>
    </form>
</div>

<?php include 'footer.php'; ?>