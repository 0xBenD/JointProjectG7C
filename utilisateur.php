<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connection.php');
    exit();
}

$view_group = $_GET['show'] ?? 'home';

$mesures = [];
$mesures_imu = [];
$distance_arriere = '--';
$all_g7b = []; // Pour stocker tout l'historique arrière

$home_gas = null;
$home_g7b = null;
$home_imu = null;
$home_g7c = null;
$home_g7e = null;
$home_logs = [];

try {
    // 1. DASHBOARD GLOBAL
    if ($view_group === 'home') {
        $stmt = $pdo->query("SELECT gas_value, danger_level FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 1");
        $home_gas = $stmt->fetch();

        $stmt = $pdo->query("SELECT distance_cm, statut FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 1");
        $home_g7b = $stmt->fetch();

        $stmt = $pdo->query("SELECT state FROM imu_readings_g7b ORDER BY timestamp DESC LIMIT 1");
        $home_imu = $stmt->fetch();

        $stmt = $pdo->query("SELECT distance_cm, latitude, longitude, date_enregistrement FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC LIMIT 1");
        $home_g7c = $stmt->fetch();

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM G7E_audiofiles");
        $home_g7e = $stmt->fetch();

        $stmt = $pdo->query("SELECT * FROM event_notification_log ORDER BY sent_at DESC LIMIT 4");
        $home_logs = $stmt->fetchAll();
    }
    
    // 2. VUES PAR GROUPE
    elseif ($view_group === 'A') {
        $stmt = $pdo->query("SELECT * FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
    } elseif ($view_group === 'B') {
        $stmt = $pdo->query("SELECT * FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
        $stmt_imu = $pdo->query("SELECT * FROM imu_readings_g7b ORDER BY timestamp DESC LIMIT 50");
        $mesures_imu = $stmt_imu->fetchAll();
    } elseif ($view_group === 'C') {
        // Vos données G7C
        $stmt = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC");
        $mesures = $stmt->fetchAll();
        
        // NOUVEAU : On récupère TOUT l'historique G7B pour que le slider puisse piocher dedans
        $stmt_b_all = $pdo->query("SELECT distance_cm, date_evenement FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC");
        $all_g7b = $stmt_b_all->fetchAll();
        
        if (count($all_g7b) > 0) { 
            $distance_arriere = $all_g7b[0]['distance_cm']; 
        }
    } elseif ($view_group === 'E') {
        $stmt = $pdo->query("SELECT * FROM G7E_audiofiles ORDER BY uploadedAt DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $db_error = "Erreur BDD : " . $e->getMessage();
}

include 'header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .ha-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .ha-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: var(--shadow); display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
    .ha-card-header { display: flex; align-items: center; gap: 12px; font-weight: bold; font-size: 1.05em; color: var(--text-main); margin-bottom: 15px; }
    .ha-icon { font-size: 1.5em; background: #f1f5f9; padding: 8px; border-radius: 8px; }
    .ha-state { font-size: 1.8em; font-weight: 800; margin: 10px 0; color: var(--text-main); }
    .ha-meta { font-size: 0.85em; color: var(--text-muted); }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; position: absolute; top: 20px; right: 20px; }
    
    .kpi-container { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
    .kpi-card { flex: 1; min-width: 200px; background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); text-align: center; }
    .kpi-title { font-size: 0.85em; color: var(--text-muted); text-transform: uppercase; font-weight: bold; }
    .kpi-value { font-size: 2em; font-weight: 800; margin-top: 10px; }
    .chart-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 25px; }
    .radar-car { display: flex; justify-content: center; align-items: center; gap: 30px; background: #0f172a; color: white; padding: 30px; border-radius: 12px; margin-bottom: 25px; }
    .radar-car .sensor-box { text-align: center; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 8px; min-width: 140px; }
    .logbook-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9em; }
</style>

<div class="container" style="max-width: 1280px; margin-top: 20px;">
    
    <div class="group-tabs" style="margin-bottom: 30px;">
        <a href="utilisateur.php?show=home" class="tab-btn <?= $view_group === 'home' ? 'active' : '' ?>">🏠 Vue d'ensemble</a>
        <a href="utilisateur.php?show=A" class="tab-btn <?= $view_group === 'A' ? 'active' : '' ?>">G7A (Gaz)</a>
        <a href="utilisateur.php?show=B" class="tab-btn <?= $view_group === 'B' ? 'active' : '' ?>">G7B (Recul & IMU)</a>
        <a href="utilisateur.php?show=C" class="tab-btn <?= $view_group === 'C' ? 'active' : '' ?>">G7C (Ultrason & GPS)</a>
        <a href="utilisateur.php?show=E" class="tab-btn <?= $view_group === 'E' ? 'active' : '' ?>">G7E (Audio MinIO)</a>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert" style="background: var(--danger); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <?php if ($view_group === 'home'): ?>
        <h2 style="margin-bottom: 20px;">Tableau de bord domotique Rover G7</h2>
        <div class="ha-grid">
            <div class="ha-card">
                <?php $gas_alert = ($home_gas && $home_gas['danger_level'] != '0'); ?>
                <div class="status-dot" style="background: <?= $gas_alert ? 'var(--danger)' : 'var(--success)' ?>;"></div>
                <div><div class="ha-card-header"><span class="ha-icon">💨</span> Capteur MQ135 (Gaz)</div><div class="ha-state"><?= $home_gas ? $home_gas['gas_value'] . ' ppm' : '--' ?></div></div>
                <div class="ha-meta">Statut : <?= $gas_alert ? '⚠️ Seuil Critique Dépassé' : 'Qualité de l\'air nominale' ?></div>
            </div>
            <div class="ha-card">
                <?php $collision_alert = ($home_g7b && $home_g7b['statut'] === 'alerte collision'); ?>
                <div class="status-dot" style="background: <?= $collision_alert ? 'var(--danger)' : 'var(--primary)' ?>;"></div>
                <div><div class="ha-card-header"><span class="ha-icon">🚨</span> Télémétrie Arrière</div><div class="ha-state"><?= $home_g7b ? htmlspecialchars($home_g7b['distance_cm']) . ' cm' : '--' ?></div></div>
                <div class="ha-meta">Centrale IMU : <strong><?= $home_imu ? htmlspecialchars($home_imu['state']) : '--' ?></strong></div>
            </div>
            <div class="ha-card">
                <div class="status-dot" style="background: var(--success);"></div>
                <div><div class="ha-card-header"><span class="ha-icon">🧭</span> Obstacles Avant & GPS</div><div class="ha-state"><?= $home_g7c ? htmlspecialchars($home_g7c['distance_cm']) . ' cm' : '--' ?></div></div>
                <div class="ha-meta">Coordonnées : <?= $home_g7c ? round($home_g7c['latitude'],4) . ', ' . round($home_g7c['longitude'],4) : 'Pas de signal GPS' ?></div>
            </div>
            <div class="ha-card">
                <div class="status-dot" style="background: #4f46e5;"></div>
                <div><div class="ha-card-header"><span class="ha-icon">🎵</span> Serveur Audio MinIO</div><div class="ha-state"><?= $home_g7e ? $home_g7e['total'] : '0' ?> fichiers</div></div>
                <div class="ha-meta">Instance de stockage distribuée active</div>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
            <div class="chart-container" style="padding: 10px;">
                <h3 style="margin: 10px;">Dernière géolocalisation</h3>
                <div id="homeMap" style="height: 250px; width: 100%; border-radius: 8px;"></div>
            </div>
            <div class="chart-container">
                <h3 style="margin-top: 0; margin-bottom: 15px;">📋 Journal de bord du Rover (Logbook)</h3>
                <?php if (count($home_logs) > 0): ?>
                    <?php foreach ($home_logs as $log): ?>
                        <div class="logbook-item">
                            <div><span style="color: var(--danger); font-weight: bold;">[ALERTE]</span> <?= htmlspecialchars($log['subject_line']) ?></div>
                            <div style="color: var(--text-muted); font-size: 0.85em;"><?= date('H:i:s', strtotime($log['sent_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted); text-align: center; padding-top: 20px;">Aucun événement critique enregistré.</p>
                <?php endif; ?>
            </div>
        </div>
        <script>
            const hLat = <?= $home_g7c ? floatval($home_g7c['latitude']) : 48.8566 ?>;
            const hLon = <?= $home_g7c ? floatval($home_g7c['longitude']) : 2.3522 ?>;
            const hMap = L.map('homeMap').setView([hLat, hLon], 15);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(hMap);
            L.marker([hLat, hLon]).addTo(hMap).bindPopup("Dernière position du Rover").openPopup();
        </script>

    <?php elseif ($view_group === 'A'): ?>
        <?php
        $chart_labels = []; $chart_data = [];
        foreach (array_reverse($mesures) as $m) { $chart_labels[] = date('H:i:s', strtotime($m['created_at'])); $chart_data[] = $m['gas_value']; }
        $latest_gas = $mesures[0] ?? null;
        ?>
        <div class="kpi-container">
            <div class="kpi-card"><div class="kpi-title">Dernier Relevé MQ135</div><div class="kpi-value"><?= $latest_gas ? $latest_gas['gas_value'] . ' ppm' : '--' ?></div></div>
            <div class="kpi-card"><div class="kpi-title">État du système</div><div class="kpi-value" style="color: <?= ($latest_gas && $latest_gas['danger_level'] != '0') ? 'var(--danger)' : 'var(--success)' ?>;"><?= ($latest_gas && $latest_gas['danger_level'] != '0') ? '⚠️ ALERTE' : '✅ NORMAL' ?></div></div>
        </div>
        <div class="chart-container"><canvas id="gasChart" height="80"></canvas></div>
        <script>
            new Chart(document.getElementById('gasChart'), { type: 'line', data: { labels: <?= json_encode($chart_labels) ?>, datasets: [{ label: 'Concentration de Gaz (ppm)', data: <?= json_encode($chart_data) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', borderWidth: 2, fill: true, tension: 0.3 }] } });
        </script>
        <div class="table-responsive">
            <table>
                <tr><th>Date</th><th>Type Gaz</th><th>Valeur</th><th>Danger</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr><td><?= $m['created_at'] ?></td><td><?= htmlspecialchars($m['gas_type']) ?></td><td><strong><?= $m['gas_value'] ?> ppm</strong></td><td><span class="badge" style="background: <?= $m['danger_level'] == '0' ? 'var(--success)' : 'var(--danger)' ?>;"><?= $m['danger_level'] == '0' ? 'Normal' : 'Danger' ?></span></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'B'): ?>
        <?php
        $chart_labels_b = []; $chart_data_b = [];
        foreach (array_reverse($mesures) as $m) { $chart_labels_b[] = date('H:i:s', strtotime($m['date_evenement'])); $chart_data_b[] = floatval(str_replace(['>', '<'], '', $m['distance_cm'])); }
        $latest_b = $mesures[0] ?? null;
        ?>
        <div class="kpi-container">
            <div class="kpi-card"><div class="kpi-title">Dernière Distance (Arrière)</div><div class="kpi-value"><?= $latest_b ? htmlspecialchars($latest_b['distance_cm']) . ' cm' : '--' ?></div></div>
            <div class="kpi-card"><div class="kpi-title">Statut Collision</div><div class="kpi-value" style="font-size: 1.5em; color: <?= ($latest_b && $latest_b['statut'] === 'alerte collision') ? 'var(--danger)' : 'var(--primary)' ?>;"><?= $latest_b ? strtoupper(htmlspecialchars($latest_b['statut'])) : '--' ?></div></div>
        </div>
        <div class="chart-container"><canvas id="reculChart" height="80"></canvas></div>
        <script>
            new Chart(document.getElementById('reculChart'), { type: 'bar', data: { labels: <?= json_encode($chart_labels_b) ?>, datasets: [{ label: 'Distance (cm)', data: <?= json_encode($chart_data_b) ?>, backgroundColor: '#3b82f6', borderRadius: 4 }] } });
        </script>
        <h3 style="margin-top: 40px;">Centrale Inertielle (IMU)</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Accélération (X, Y, Z)</th><th>Gyroscope (X, Y, Z)</th><th>État du Rover</th></tr>
                <?php foreach ($mesures_imu as $imu): ?>
                    <tr><td><?= date('H:i:s', strtotime($imu['timestamp'])) ?></td><td><span style="color: var(--text-muted);"><?= $imu['acc_x'] ?>, <?= $imu['acc_y'] ?>, <?= $imu['acc_z'] ?></span></td><td><span style="color: var(--text-muted);"><?= $imu['gyro_x'] ?>, <?= $imu['gyro_y'] ?>, <?= $imu['gyro_z'] ?></span></td><td><?php $sc = '#64748b'; if ($imu['state'] === 'COLLISION') $sc = 'var(--danger)'; elseif ($imu['state'] === 'VIBRATION') $sc = '#f59e0b'; elseif ($imu['state'] === 'ANGLE_CHANGE') $sc = 'var(--primary)'; ?><span class="badge" style="background: <?= $sc ?>;"><?= htmlspecialchars($imu['state']) ?></span></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'C'): ?>
        <h3 style="margin-top: 0;">Radar d'approche (Capteurs synchronisés)</h3>
        <div class="radar-car">
            <div class="sensor-box">
                <div style="font-size: 0.85em; color: #94a3b8; text-transform: uppercase;">Avant (G7C)</div>
                <div id="radar-avant" style="font-size: 2.5em; font-weight: bold; color: #10b981;"><?= isset($mesures[0]) ? htmlspecialchars($mesures[0]['distance_cm']) : '--' ?> cm</div>
            </div>
            
            <div style="font-size: 5em; transform: rotate(0deg);">🚙</div>
            
            <div class="sensor-box">
                <div style="font-size: 0.85em; color: #94a3b8; text-transform: uppercase;">Arrière (G7B)</div>
                <div id="radar-arriere" style="font-size: 2.5em; font-weight: bold; color: #ef4444;">
                    <?= htmlspecialchars($distance_arriere) ?> <?= $distance_arriere !== '--' && !str_contains($distance_arriere, '>') ? 'cm' : '' ?>
                </div>
            </div>
        </div>

        <div class="chart-container" style="padding: 0; overflow: hidden; border: none;"><div id="map" style="height: 400px; width: 100%; border-radius: 12px; z-index: 1;"></div></div>
        
        <div class="slider-container" style="background: white; border-radius: 12px;">
            <div class="slider-meta"><span>Historique Ancien</span><span id="slider-date-label" style="font-weight: 700; color: var(--primary); font-size: 1rem;">Chargement...</span><span>Mesure Récente</span></div>
            <input type="range" id="time-slider" min="0" max="<?= count($mesures) - 1 ?>" value="<?= count($mesures) - 1 ?>">
        </div>

        <h3>Journal des Données Brut</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Distance Ultrason</th><th>Humidité</th><th>Altitude</th></tr>
                <?php foreach (array_slice($mesures, 0, 15) as $m): ?>
                    <tr><td><?= $m['date_enregistrement'] ?></td><td><strong><?= $m['distance_cm'] ?> cm</strong></td><td><?= $m['humidite_pourcent'] ?> %</td><td><?= $m['altitude'] ?> m</td></tr>
                <?php endforeach; ?>
            </table>
        </div>

        <script>
            const rawData = <?= json_encode($mesures) ?>;
            const rawDataB = <?= json_encode($all_g7b) ?>; 
            
            const defaultLat = rawData.length > 0 ? parseFloat(rawData[0].latitude) : 48.8566;
            const defaultLon = rawData.length > 0 ? parseFloat(rawData[0].longitude) : 2.3522;
            const map = L.map('map').setView([defaultLat, defaultLon], 14);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);
            let currentMarker = null;

            // Algorithme de recherche du timestamp G7B le plus proche du timestamp G7C actuel
            function getClosestBDistance(targetDateStr) {
                if (!rawDataB || rawDataB.length === 0) return '--';
                const targetTime = new Date(targetDateStr.replace(' ', 'T')).getTime();
                let closestVal = rawDataB[0].distance_cm;
                let minDiff = Infinity;

                for (let i = 0; i < rawDataB.length; i++) {
                    const bTime = new Date(rawDataB[i].date_evenement.replace(' ', 'T')).getTime();
                    const diff = Math.abs(bTime - targetTime);
                    if (diff < minDiff) {
                        minDiff = diff;
                        closestVal = rawDataB[i].distance_cm;
                    }
                }
                return closestVal;
            }

            function updateMapTracking(index) {
                const actualIndex = (rawData.length - 1) - index; 
                const selectedRecord = rawData[actualIndex]; 
                if (!selectedRecord) return;
                
                // MAJ de la date du Slider
                document.getElementById('slider-date-label').innerText = selectedRecord.date_enregistrement;
                
                // MAJ Radar Avant (Valeur G7C)
                document.getElementById('radar-avant').innerText = selectedRecord.distance_cm + ' cm';
                
                // MAJ Radar Arrière (Valeur G7B synchronisée)
                const closestB = getClosestBDistance(selectedRecord.date_enregistrement);
                const unit = String(closestB).includes('>') ? '' : ' cm';
                document.getElementById('radar-arriere').innerText = closestB + unit;

                // MAJ de la Carte
                if (currentMarker) { map.removeLayer(currentMarker); }
                const popupContent = `<div style="font-family: sans-serif; min-width: 160px;"><h4 style="margin: 0 0 8px 0; color: var(--primary);">Mesure de l'Instant</h4><p style="margin: 4px 0;">📅 <b>Date :</b> ${selectedRecord.date_enregistrement}</p><p style="margin: 4px 0;">📏 <b>Distance :</b> ${selectedRecord.distance_cm} cm</p><p style="margin: 4px 0;">💧 <b>Humidité :</b> ${selectedRecord.humidite_pourcent}%</p><p style="margin: 4px 0;">⛰️ <b>Altitude :</b> ${selectedRecord.altitude} m</p></div>`;
                currentMarker = L.marker([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]).addTo(map).bindPopup(popupContent).openPopup();
                map.panTo([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]);
            }
            
            const slider = document.getElementById('time-slider');
            slider.addEventListener('input', function (e) { updateMapTracking(parseInt(e.target.value)); });
            if (rawData.length > 0) { updateMapTracking(rawData.length - 1); }
        </script>

    <?php elseif ($view_group === 'E'): ?>
        <h3>Fichiers Multimédias Archivés — Stockage Cloud MinIO</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Date d'envoi</th><th>Nom du Fichier</th><th>Lecture Audio</th><th>Bucket MinIO</th><th>Taille</th><th>Durée</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($m['uploadedAt'])) ?></td><td><strong>📁 <?= htmlspecialchars($m['filename']) ?></strong></td>
                        <td><?php $minioBaseUrl = "http://178.33.122.21:9000"; $audioUrl = $minioBaseUrl . "/" . $m['minioBucket'] . "/" . $m['minioPath']; ?><audio controls preload="none" style="height: 35px; width: 220px;"><source src="<?= htmlspecialchars($audioUrl) ?>" type="audio/wav"></audio></td>
                        <td><span class="badge" style="background: #4f46e5;"><?= htmlspecialchars($m['minioBucket']) ?></span></td><td><?= $m['fileSize'] ? round($m['fileSize'] / (1024 * 1024), 2) . " Mo" : "0 Mo" ?></td><td><?= $m['duration'] ? sprintf("%02d:%02d min", floor($m['duration'] / 60), $m['duration'] % 60) : "--" ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>