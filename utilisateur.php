<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connection.php');
    exit();
}

// Par défaut, on affiche le groupe de l'utilisateur, mais s'il clique sur un onglet, on affiche le groupe demandé
$view_group = $_GET['show'] ?? $_SESSION['user_groupe'];
$mesures = [];

try {
    if ($view_group === 'A') {
        $stmt = $pdo->query("SELECT * FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
    } elseif ($view_group === 'B') {
        $stmt = $pdo->query("SELECT * FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
    } elseif ($view_group === 'C') {
        // Pour la carte G7C, on extrait l'ensemble des points sans "LIMIT" strict pour alimenter l'historique du slider
        $stmt = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC");
        $mesures = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $db_error = "Erreur BDD : " . $e->getMessage();
}

include 'header.php';
?>

<div class="card">
    <h2>Espace d'Exploration Inter-Groupes</h2>
    <p>En tant que membre du Groupe <strong>G7<?= htmlspecialchars($_SESSION['user_groupe']) ?></strong>, vous disposez d'un droit de consultation sur l'ensemble des modules du réseau :</p>

    <div class="group-tabs">
        <a href="utilisateur.php?show=A" class="tab-btn <?= $view_group === 'A' ? 'active' : '' ?>">Groupe G7A (Gaz)</a>
        <a href="utilisateur.php?show=B" class="tab-btn <?= $view_group === 'B' ? 'active' : '' ?>">Groupe G7B (Recul)</a>
        <a href="utilisateur.php?show=C" class="tab-btn <?= $view_group === 'C' ? 'active' : '' ?>">Groupe G7C (Votre GPS/Ultrason)</a>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <?php if ($view_group === 'A'): ?>
        <h3>Données Capteur de Gaz (MQ135)</h3>
        <table>
            <tr><th>Date</th><th>Type Gaz</th><th>Valeur</th><th>Danger</th></tr>
            <?php foreach ($mesures as $m): ?>
            <tr>
                <td><?= $m['created_at'] ?></td>
                <td><?= htmlspecialchars($m['gas_type']) ?></td>
                <td><?= $m['gas_value'] ?> ppm</td>
                <td>
                    <span class="badge" style="background: <?= $m['danger_level'] == '0' ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $m['danger_level'] == '0' ? 'Normal' : 'Danger' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

    <?php if ($view_group === 'B'): ?>
        <h3>Flux Télémétrique — Capteur de Recul</h3>
        <table>
            <tr><th>Date</th><th>Valeur Brute</th><th>Distance</th><th>Statut</th></tr>
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

    <?php elseif ($view_group === 'C'): ?>
        <h3>Suivi Spatiotemporel et Analyse Environnementale</h3>
        
        <div id="map"></div>

        <div class="slider-container">
            <div class="slider-meta">
                <span>Historique Ancien</span>
                <span id="slider-date-label" style="font-weight: 700; color: var(--primary); font-size: 1rem;">Chargement...</span>
                <span>Mesure Récente</span>
            </div>
            <input type="range" id="time-slider" min="0" max="<?= count($mesures) - 1 ?>" value="<?= count($mesures) - 1 ?>">
        </div>

        <h3>Journal des Données Brut</h3>
        <table>
            <tr><th>Horodatage</th><th>Distance Ultrason</th><th>Humidité</th><th>Altitude</th></tr>
            <?php foreach (array_slice($mesures, 0, 15) as $m): ?>
            <tr>
                <td><?= $m['date_enregistrement'] ?></td>
                <td><strong><?= $m['distance_cm'] ?> cm</strong></td>
                <td><?= $m['humidite_pourcent'] ?> %</td>
                <td><?= $m['altitude'] ?> m</td>
            </tr>
            <?php endforeach; ?>
        </table>

        <script>
            // Transformation du tableau PHP en objet JSON exploitable par JS
            const rawData = <?= json_encode($mesures) ?>;

            // 1. Initialisation de la Carte centrée sur la dernière position connue (ou Paris par défaut)
            const defaultLat = rawData.length > 0 ? parseFloat(rawData[0].latitude) : 48.8566;
            const defaultLon = rawData.length > 0 ? parseFloat(rawData[0].longitude) : 2.3522;
            
            const map = L.map('map').setView([defaultLat, defaultLon], 14);

            // Ajout du fond de carte OpenStreetMap propre et moderne
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap contributors © CARTO'
            }).addTo(map);

            // Variable globale pour stocker le marqueur de carte actif
            let currentMarker = null;

            // 2. Fonction maîtresse : Affichage dynamique selon l'index du slider temporel
            function updateMapTracking(index) {
                // Inversion de l'index car rawData est trié du plus récent au plus ancien
                const actualIndex = (rawData.length - 1) - index;
                const selectedRecord = rawData[actualIndex];

                if (!selectedRecord) return;

                // Mise à jour de l'affichage textuel de la date du slider
                document.getElementById('slider-date-label').innerText = selectedRecord.date_enregistrement;

                // Filtrage de cohérence spatiale : Extraction de toutes les données antérieures ou égales à cette date 
                // pour trouver la valeur la plus récente *à cet endroit précis*
                const subHistory = rawData.slice(actualIndex);
                let uniquePoints = {};

                subHistory.forEach(item => {
                    const key = `${parseFloat(item.latitude).toFixed(5)},${parseFloat(item.longitude).toFixed(5)}`;
                    // Comme on descend du plus récent au plus ancien, le premier trouvé est le plus récent à cette coordonnée
                    if (!uniquePoints[key]) {
                        uniquePoints[key] = item;
                    }
                });

                // Récupération de la donnée filtrée la plus fraîche pour le point ciblé par le curseur
                const currentPointKey = `${parseFloat(selectedRecord.latitude).toFixed(5)},${parseFloat(selectedRecord.longitude).toFixed(5)}`;
                const activeData = uniquePoints[currentPointKey];

                // Suppression de l'ancien marqueur graphique s'il existe
                if (currentMarker) {
                    map.removeLayer(currentMarker);
                }

                // Construction de la bulle d'information (Popup HTML) avec vos variables
                const popupContent = `
                    <div style="font-family: sans-serif; min-width: 160px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--primary);">Mesure de l'Instant</h4>
                        <p style="margin: 4px 0;">📅 <b>Date :</b> ${activeData.date_enregistrement}</p>
                        <p style="margin: 4px 0;">📏 <b>Distance :</b> ${activeData.distance_cm} cm</p>
                        <p style="margin: 4px 0;">💧 <b>Humidité :</b> ${activeData.humidite_pourcent}%</p>
                        <p style="margin: 4px 0;">⛰️ <b>Altitude :</b> ${activeData.altitude} m</p>
                    </div>
                `;

                // Création et positionnement du nouveau marqueur
                currentMarker = L.marker([parseFloat(activeData.latitude), parseFloat(activeData.longitude)])
                    .addTo(map)
                    .bindPopup(popupContent)
                    .openPopup();

                // Recentrer doucement la carte sur le point actif
                map.panTo([parseFloat(activeData.latitude), parseFloat(activeData.longitude)]);
            }

            // 3. Écouteur d'événement sur le slider pour recalculer à chaque déplacement
            const slider = document.getElementById('time-slider');
            slider.addEventListener('input', function(e) {
                updateMapTracking(parseInt(e.target.value));
            });

            // Initialisation au démarrage avec la valeur maximale (donnée la plus récente)
            if (rawData.length > 0) {
                updateMapTracking(rawData.length - 1);
            }
        </script>
    <?php endif; ?>
</div>

<?php 
include 'footer.php'; 
?>