<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangar G7 - Projet Commun</title>
    <style>
        :root {
            --primary: #007bff;
            --dark: #343a40;
            --light: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f9;
            color: var(--dark);
        }

        header {
            background: var(--dark);
            color: white;
            padding: 15px 2%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: 500;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        th {
            background: #e9ecef;
        }

        .alert {
            padding: 15px;
            background: var(--danger);
            color: white;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-size: 0.85em;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <header>
        <h2>Hangar G7</h2>
        <nav>
            <a href="index.php">Accueil</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="utilisateur.php">Mon Tableau de Bord (Groupe <?= htmlspecialchars($_SESSION['user_groupe']) ?>)</a>
                <a href="connection.php?logout=1" style="color: var(--danger);">Déconnexion</a>
            <?php else: ?>
                <a href="connection.php" class="btn">Connexion / Inscription</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container"></div>