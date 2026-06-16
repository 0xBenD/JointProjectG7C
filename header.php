<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangar G7 - Centre de Contrôle IoT</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
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