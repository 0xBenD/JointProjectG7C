<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '178.33.122.21';
$db = 'hangardb_bedi64240';
$user = 'bedi64240';
$pass = '4HG6UkdXvSgabKiuOFbmU107';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset;port=3306", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}