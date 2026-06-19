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
            $mail->CharSet = 'UTF-8'; // Important pour les accents

            $mail->setFrom('jointprojectg7@alwaysdata.net', 'Hangar G7 Support');
            $mail->addAddress($email);
            
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            
            // --- ACTIVATION DU FORMAT HTML ---
            $mail->isHTML(true);

            $resetLink = "https://jointprojectg7.alwaysdata.net/reset_password.php?token=$token";

            // --- CONSTRUCTION DU GABARIT HTML ---
            $htmlContent = "
            <div style='font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; padding: 30px 10px; margin: 0;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                    
                    <div style='background-color: #2563eb; padding: 25px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;'>🔐 Hangar G7</h1>
                    </div>
                    
                    <div style='padding: 30px; color: #0f172a;'>
                        <h2 style='margin-top: 0; color: #1e293b; font-size: 18px;'>Réinitialisation de mot de passe</h2>
                        <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                            Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte sur l'infrastructure du <strong>Hangar G7</strong>.
                        </p>
                        
                        <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                            Cliquez sur le bouton ci-dessous pour configurer un nouveau mot de passe. Ce lien sécurisé est valide pendant <strong>1 heure</strong>.
                        </p>
                        
                        <div style='text-align: center; margin-top: 35px; margin-bottom: 30px;'>
                            <a href='$resetLink' style='display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px;'>Réinitialiser mon mot de passe</a>
                        </div>
                        
                        <div style='background-color: #f1f5f9; border-left: 4px solid #94a3b8; padding: 15px 20px; border-radius: 0 6px 6px 0;'>
                            <p style='margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5;'>
                                <strong>Vous n'avez pas fait cette demande ?</strong><br>
                                Vous pouvez ignorer cet e-mail en toute sécurité. Votre mot de passe actuel restera actif et votre compte est sécurisé.
                            </p>
                        </div>
                    </div>
                    
                    <div style='background-color: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
                        <p style='margin: 0; font-size: 12px; color: #64748b;'>
                            Ceci est un message automatisé du système Hangar G7.<br>
                            Merci de ne pas y répondre.
                        </p>
                    </div>

                </div>
            </div>
            ";

            $mail->Body = $htmlContent;
            
            // --- VERSION TEXTE BRUT ---
            $mail->AltBody = "Hangar G7 : Réinitialisation de votre mot de passe\n\n" .
                             "Nous avons reçu une demande pour réinitialiser votre mot de passe.\n\n" .
                             "Veuillez copier et coller le lien suivant dans votre navigateur (valide 1 heure) :\n" .
                             $resetLink . "\n\n" .
                             "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement ce message.";

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