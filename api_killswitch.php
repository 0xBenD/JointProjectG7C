<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

try {
    // Si on reçoit un POST, on insère un nouvel état
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['etat'])) {
            $etat = $data['etat'] == 1 ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO kill_switch_log (etat, date_action) VALUES (?, NOW())");
            $stmt->execute([$etat]);
            echo json_encode(['success' => true, 'etat' => $etat]);
            exit();
        }
    }

    // Sinon (GET), on lit le dernier état connu
    $stmt = $pdo->query("SELECT etat FROM kill_switch_log ORDER BY date_action DESC LIMIT 1");
    $result = $stmt->fetch();
    echo json_encode(['etat' => $result ? $result['etat'] : 0]);

} catch (\PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>