<?php
// 1. On importe le fichier de connexion existant (sans refaire de nouvelle connexion)
require_once __DIR__ . '/dbconnect.php';

// 2. Si dbconnect.php a rencontré une erreur, son script s'est arrêté (via die()).
// Donc, si PHP arrive à lire cette ligne, c'est que la connexion a RÉUSSI !
// On valide simplement que la variable $pdo existe et est bien fonctionnelle.
$compte_rendu_connexion = isset($pdo) && $pdo instanceof PDO;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification BDD - Projet Multi-Groupes</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #111319; /* Design sombre calqué sur l'interface de Hangar */
            color: #f4f5f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #1a1e28;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #fff;
        }
        p {
            color: #9ea7b3;
            font-size: 0.95rem;
            margin-bottom: 25px;
        }
        .badge {
            font-size: 1.1rem;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 30px;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .connected {
            background-color: rgba(0, 208, 132, 0.15);
            color: #00D084;
            border: 1px solid rgba(0, 208, 132, 0.3);
        }
        .disconnected {
            background-color: rgba(247, 8, 8, 0.15);
            color: #f70808;
            border: 1px solid rgba(247, 8, 8, 0.3);
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Statut de l'Hébergement</h1>
        <p>Test d'accès à la base de données MariaDB globale</p>

        <?php if ($compte_rendu_connexion): ?>
            <span class="badge connected">✔ BASE DE DONNÉES CONNECTÉE</span>
        <?php else: ?>
            <span class="badge disconnected">✖ CONNEXION IMPOSSIBLE</span>
        <?php endif; ?>
    </div>

</body>
</html>
