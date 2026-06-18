<?php 
require_once 'config.php'; 

// ==========================================
// LOGIQUE D'ALERTE GLOBALE (DÉPLACÉE ICI)
// ==========================================
$is_global_danger = false;
$global_danger_msg = [];

try {
    $stmt = $pdo->query("SELECT distance_cm, radiation_usv FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC LIMIT 1");
    $latest = $stmt->fetch();

    if ($latest) {
        if (floatval($latest['distance_cm']) < 10) { 
            $is_global_danger = true; 
            $global_danger_msg[] = "COLLISION IMMINENTE (" . $latest['distance_cm'] . " cm)"; 
        }
        if (isset($latest['radiation_usv']) && floatval($latest['radiation_usv']) >= 400.0) { 
            $is_global_danger = true; 
            $global_danger_msg[] = "RADIATION MORTELLE (" . $latest['radiation_usv'] . " mSv/h)"; 
        }
    }
} catch (\PDOException $e) { /* Silencieux si la table n'est pas prête */ }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangar G7 - Centre de Commandement Opérationnel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .flash-alert { background: #dc2626; color: white; padding: 15px; text-align: center; font-weight: 900; font-size: 1.2em; text-transform: uppercase; border-bottom: 2px solid #7f1d1d; box-shadow: 0 4px 20px rgba(220, 38, 38, 0.6); animation: flash 0.8s infinite; }
        @keyframes flash { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
    </style>
</head>
<body>
    
    <div id="header-flash-alert" class="flash-alert" style="<?= $is_global_danger ? 'display: block;' : 'display: none;' ?>">
        ⚠️ ALERTE CRITIQUE : <span id="header-flash-msg"><?= implode(" | ", $global_danger_msg) ?></span> ⚠️
    </div>

    <header>
        <h2>Hangar G7</h2>
        <nav>
            <a href="index.php">Accueil</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="utilisateur.php">Tableau de Bord</a>
                <a href="connection.php?logout=1" style="color: var(--danger);">Déconnexion</a>
            <?php else: ?>
                <a href="connection.php" class="btn">Espace IoT</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">