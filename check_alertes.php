<?php
// On s'assure que PHPMailer est chargé
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function verifierEtEnvoyerAlertes($pdo) {
    // 1. Analyser la dernière mesure G7C (Avant & Radiation)
    $stmt_c = $pdo->query("SELECT id, distance_cm, radiation_usv, date_enregistrement FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC LIMIT 1");
    $c = $stmt_c->fetch();

    if ($c) {
        $dist_avant = floatval($c['distance_cm']);
        $rad = floatval($c['radiation_usv']);

        if ($dist_avant <= 10) {
            envoyerAlerteSiNouvelle($pdo, "OBSTACLE AVANT", "Obstacle critique détecté à l'avant du rover : {$dist_avant} cm.", $c['date_enregistrement']);
        }
        if ($rad >= 400.0) {
            envoyerAlerteSiNouvelle($pdo, "DANGER DE MORT RADIOLOGIQUE", "Niveau de radiation extrêmement critique : {$rad} mSv/h.", $c['date_enregistrement']);
        }
    }

    // 2. Analyser la dernière mesure G7B (Arrière)
    $stmt_b = $pdo->query("SELECT id, distance_cm, date_evenement FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 1");
    $b = $stmt_b->fetch();

    if ($b) {
        // Nettoyage de la chaîne au cas où il y a des symboles "<" ou ">"
        $dist_arriere = floatval(str_replace(['>', '<'], '', $b['distance_cm']));
        if ($dist_arriere <= 10) {
            envoyerAlerteSiNouvelle($pdo, "OBSTACLE ARRIERE", "Obstacle critique détecté à l'arrière du rover : {$dist_arriere} cm.", $b['date_evenement']);
        }
    }
}

function envoyerAlerteSiNouvelle($pdo, $type_alerte, $message_body, $timestamp) {
    $subject = "🚨 ALERTE G7 : " . $type_alerte;

    // --- VERROU ANTI-SPAM ---
    $stmt = $pdo->prepare("SELECT id FROM event_notification_log WHERE subject_line LIKE ? AND sent_at = ?");
    $stmt->execute(["%{$type_alerte}%", $timestamp]);

    if ($stmt->rowCount() == 0) {
        // 1. On insère dans la BDD
        $insert = $pdo->prepare("INSERT INTO event_notification_log (subject_line, sent_at) VALUES (?, ?)");
        $insert->execute([$subject, $timestamp]);

        // 2. On récupère les utilisateurs
        $stmt_users = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
        $users = $stmt_users->fetchAll();

        if (count($users) > 0) {
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp-jointprojectg7.alwaysdata.net';
            $mail->SMTPAuth = true;
            $mail->Username = 'jointprojectg7@alwaysdata.net';
            $mail->Password = 'muggek-veXfi2-gijkuw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8'; // Important pour les accents
            
            $mail->setFrom('jointprojectg7@alwaysdata.net', 'Hangar G7 - Alertes');
            
            foreach ($users as $u) {
                $mail->addBCC($u['email']);
            }

            // --- ACTIVATION DU FORMAT HTML ---
            $mail->isHTML(true);
            $mail->Subject = $subject;

            // --- CONSTRUCTION DU GABARIT HTML ---
            $htmlContent = "
            <div style='font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; padding: 30px 10px; margin: 0;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                    
                    <div style='background-color: #ef4444; padding: 25px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;'>🚨 ALERTE CRITIQUE ROVER</h1>
                    </div>
                    
                    <div style='padding: 30px; color: #0f172a;'>
                        <h2 style='margin-top: 0; color: #1e293b; font-size: 18px;'>Notification de Télémétrie Automatique</h2>
                        <p style='font-size: 15px; line-height: 1.6; color: #475569;'>
                            Le système de surveillance du <strong>Hangar G7</strong> a détecté une anomalie nécessitant votre attention immédiate.
                        </p>
                        
                        <div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px 20px; margin: 25px 0; border-radius: 0 6px 6px 0;'>
                            <p style='margin: 0 0 10px 0; font-weight: 800; color: #991b1b; font-size: 14px; text-transform: uppercase;'>Détails de l'événement :</p>
                            <ul style='margin: 0; padding-left: 20px; color: #7f1d1d; line-height: 1.8; font-size: 15px;'>
                                <li><strong>Type d'alerte :</strong> " . htmlspecialchars($type_alerte) . "</li>
                                <li><strong>Mesure :</strong> " . htmlspecialchars($message_body) . "</li>
                                <li><strong>Heure système :</strong> " . htmlspecialchars($timestamp) . "</li>
                            </ul>
                        </div>
                        
                        <div style='text-align: center; margin-top: 35px; margin-bottom: 10px;'>
                            <a href='https://jointprojectg7.alwaysdata.net/utilisateur.php' style='display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px;'>Accéder au Rover Control</a>
                        </div>
                    </div>
                    
                    <div style='background-color: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
                        <p style='margin: 0; font-size: 12px; color: #64748b;'>
                            Ceci est un message généré automatiquement par l'infrastructure IoT.<br>
                            Merci de ne pas y répondre.
                        </p>
                    </div>

                </div>
            </div>
            ";

            $mail->Body = $htmlContent;
            
            // --- VERSION TEXTE BRUT (pour les clients mail archaïques ou les montres connectées) ---
            $mail->AltBody = "ALERTE CRITIQUE ROVER G7\n\n" .
                             "Type d'alerte : " . $type_alerte . "\n" .
                             "Mesure : " . $message_body . "\n" .
                             "Heure : " . $timestamp . "\n\n" .
                             "Connectez-vous immédiatement sur https://jointprojectg7.alwaysdata.net/utilisateur.php pour évaluer la situation.";

            @$mail->send();
        }
    }
}
?>