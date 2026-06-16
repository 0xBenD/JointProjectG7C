<?php
require_once 'config.php';

// Blocage de sécurité si non connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connection.php');
    exit();
}

$groupe = $_SESSION['user_groupe'];
$mesures = [];

// Requête SQL adaptative selon le groupe
try {
    if ($groupe === 'A') {
        // Chargement de la table G7A
        $stmt = $pdo->query("SELECT * FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 15");
        $mesures = $stmt->fetchAll();
    } elseif ($groupe === 'B') {
        // Chargement de la table G7B
        $stmt = $pdo->query("SELECT * FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 15");
        $mesures = $stmt->fetchAll();
    } elseif ($groupe === 'C') {
        // Chargement de votre table G7C
        $stmt = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC LIMIT 15");
        $mesures = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $db_error = "Erreur lors du chargement des données de l'équipe G7" . $groupe . " : " . $e->getMessage();
}

include 'header.php';
?>

<h2>Tableau de Bord — Équipe G7<?= htmlspecialchars($groupe) ?></h2>
<p>Bonjour <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>. Voici les dernières mesures capturées par les modules de votre groupe :</p>

<?php if (isset($db_error)): ?>
    <div class="alert"><?= htmlspecialchars($db_error) ?></div>
<?php endif; ?>

<?php if ($groupe === 'A'): ?>
    <h3>Données Capteur de Gaz (MQ135)</h3>
    <table>
        <tr>
            <th>Date</th>
            <th>Type Gaz</th>
            <th>Valeur brute</th>
            <th>Dangerosité</th>
        </tr>
        <?php foreach ($mesures as $m): ?>
        <tr>
            <td><?= $m['created_at'] ?></td>
            <td><?= htmlspecialchars($m['gas_type']) ?></td>
            <td><?= $m['gas_value'] ?> ppm</td>
            <td>
                <?php if ($m['danger_level'] == '0'): ?>
                    <span class="badge" style="background: var(--success);">Normal</span>
                <?php else: ?>
                    <span class="badge" style="background: var(--danger);">Alerte</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

<?php elseif ($groupe === 'B'): ?>
    <h3>Données Capteur de Recul</h3>
    <table>
        <tr>
            <th>Date</th>
            <th>Valeur Brute</th>
            <th>Distance</th>
            <th>Statut</th>
        </tr>
        <?php foreach ($mesures as $m): ?>
        <tr>
            <td><?= $m['date_evenement'] ?></td>
            <td><?= $m['valeur_brute'] ?></td>
            <td><?= htmlspecialchars($m['distance_cm']) ?> cm</td>
            <td>
                <span class="badge" style="background: <?= $m['statut'] === 'alerte collision' ? 'var(--danger)' : 'var(--primary)' ?>;">
                    <?= htmlspecialchars($m['statut']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

<?php elseif ($groupe === 'C'): ?>
    <h3>Données Votre Module (Ultrason & Coordonnées GNSS)</h3>
    <table>
        <tr>
            <th>Horodatage</th>
            <th>Distance</th>
            <th>Humidité Sol</th>
            <th>Position (Lat, Lon)</th>
            <th>Altitude</th>
        </tr>
        <?php foreach ($mesures as $m): ?>
        <tr>
            <td><?= $m['date_enregistrement'] ?></td>
            <td><strong><?= $m['distance_cm'] ?> cm</strong></td>
            <td><?= $m['humidite_pourcent'] ?> %</td>
            <td><a href="https://www.google.com/maps?q=<?= $m['latitude'] ?>,<?= $m['longitude'] ?>" target="_blank" style="color: var(--primary); font-weight: 500;">📍 <?= var_export($m['latitude'], true) ?>, <?= var_export($m['longitude'], true) ?></a></td>
            <td><?= $m['altitude'] ?> m</td>
        </tr>
        <?php endforeach; ?>
    </table>

<?php else: ?>
    <div style="background: #e9ecef; padding: 30px; text-align: center; border-radius: 6px; color: #6c757d;">
        <h3>Espace En Attente</h3>
        <p>Les structures de tables pour l'équipe G7<?= htmlspecialchars($groupe) ?> ne sont pas encore raccordées au serveur d'affichage global.</p>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>