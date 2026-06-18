<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

try {
    $data = [];

    // Dernière mesure G7C (Avant, GPS, Radiation)
    $stmt = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC LIMIT 1");
    $data['g7c'] = $stmt->fetch();

    // Dernière mesure G7B (Arrière)
    $stmt = $pdo->query("SELECT * FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 1");
    $data['g7b'] = $stmt->fetch();

    echo json_encode($data);
} catch (\PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>