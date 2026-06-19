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
    $subject = "🚨 " . $type_alerte;

    // --- VERROU ANTI-SPAM ---
    // On vérifie si CETTE alerte précise (même sujet, même date d'événement) a déjà été envoyée
    $stmt = $pdo->prepare("SELECT id FROM event_notification_log WHERE subject_line LIKE ? AND sent_at = ?");
    $stmt->execute(["%{$type_alerte}%", $timestamp]);

    if ($stmt->rowCount() == 0) {
        // 1. On insère dans la BDD en premier pour bloquer immédiatement les autres appels concurrents
        $insert = $pdo->prepare("INSERT INTO event_notification_log (subject_line, sent_at) VALUES (?, ?)");
        $insert->execute([$subject, $timestamp]);

        // 2. On récupère tous les e-mails des utilisateurs
        $stmt_users = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
        $users = $stmt_users->fetchAll();

        if (count($users) > 0) {
            // 3. Configuration et envoi via PHPMailer
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp-jointprojectg7.alwaysdata.net';
            $mail->SMTPAuth = true;
            $mail->Username = 'jointprojectg7@alwaysdata.net';
            $mail->Password = 'muggek-veXfi2-gijkuw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('jointprojectg7@alwaysdata.net', 'Système Alerte G7');
            $mail->Subject = $subject;
            $mail->Body = "Ceci est une alerte automatique du Hangar G7.\n\n" . 
                          $message_body . "\n\n" .
                          "Heure de l'événement : " . $timestamp . "\n" .
                          "Veuillez vous connecter au tableau de bord pour évaluer la situation.";

            // On ajoute les utilisateurs en BCC (Copie Cachée) pour la confidentialité
            foreach ($users as $u) {
                $mail->addBCC($u['email']);
            }

            // On envoie (on ignore silencieusement s'il y a une erreur d'envoi pour ne pas crasher l'API)
            @$mail->send();
        }
    }
}
?>