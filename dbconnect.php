<?php
// 1. CHARGEUR DE .ENV MAISON (Zéro dépendance, ultra-compatible)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // On ignore les commentaires
        if (strpos(trim($line), '#') === 0) continue;
        // On sépare la clé et la valeur
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', trim($name), trim($value)));
        }
    }
}

// 2. VÉRIFICATION ROBUSTE
$required_vars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'];
$missing_vars = [];

foreach ($required_vars as $var) {
    if (getenv($var) === false) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>
            <strong>Erreur de configuration :</strong> Il manque des variables d'environnement (" . implode(', ', $missing_vars) . ").<br>
            <em>Vérifiez votre fichier .env en local ou l'interface Hangar en production !</em>
         </div>");
}

// 3. RECUPÉRATION DES VARIABLES
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');

// 4. CONNEXION PDO SÉCURISÉE
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "Connexion réussie !"; // Optionnel pour tester en local
    
} catch (PDOException $e) {
    error_log("[BDD ERROR] " . $e->getMessage());
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>
            <strong>Erreur de connexion à la base de données.</strong><br>
            Le serveur refuse les identifiants ou est inaccessible.
         </div>");
}
