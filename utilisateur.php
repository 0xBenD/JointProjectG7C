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
$all_g7b = []; 

$home_gas = null;
$home_imu = null;
$home_g7e = null;
$home_g7d = null; // Nouveau G7D
$home_logs = [];

try {
    // ==========================================
    // 1. DASHBOARD GLOBAL (HOME)
    // ==========================================
    if ($view_group === 'home') {
        $stmt = $pdo->query("SELECT gas_value, danger_level FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 1");
        $home_gas = $stmt->fetch();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM G7E_audiofiles");
        $home_g7e = $stmt->fetch();

        // Récupération IMU (G7B) pour la page d'accueil
        $stmt = $pdo->query("SELECT state FROM imu_readings_g7b ORDER BY timestamp DESC LIMIT 1");
        $home_imu = $stmt->fetch();

        // Récupération G7D (Try/Catch séparé au cas où la table n'est pas encore créée)
        try {
            $stmt = $pdo->query("SELECT temperature, humidity, timestamp FROM mesures_dht11_g7d ORDER BY timestamp DESC LIMIT 1");
            $home_g7d = $stmt->fetch();
        } catch (\PDOException $e) { $home_g7d = null; }

        $stmt = $pdo->query("SELECT * FROM event_notification_log ORDER BY sent_at DESC LIMIT 4");
        $home_logs = $stmt->fetchAll();

        $stmt_c_all = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC");
        $all_g7c = $stmt_c_all->fetchAll();
        
        $stmt_b_all = $pdo->query("SELECT distance_cm, date_evenement FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC");
        $all_g7b = $stmt_b_all->fetchAll();

        $stmt_gas_all = $pdo->query("SELECT gas_value, created_at FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 20");
        $hist_gas = array_reverse($stmt_gas_all->fetchAll());

        // ALERTE OBSTACLE (< 10cm) INJECTÉE DANS LE LOGBOOK
        if (count($all_g7c) > 0 && floatval($all_g7c[0]['distance_cm']) <= 10) {
            array_unshift($home_logs, [
                'subject_line' => '🚨 URGENCE : Obstacle imminent à l\'avant détecté (<= 10 cm)',
                'sent_at' => $all_g7c[0]['date_enregistrement']
            ]);
        }
    }
    
    // ==========================================
    // 2. VUES DÉTAILLÉES PAR GROUPE
    // ==========================================
    elseif ($view_group === 'A') {
        $stmt = $pdo->query("SELECT * FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
    } elseif ($view_group === 'B') {
        $stmt = $pdo->query("SELECT * FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC LIMIT 50");
        $mesures = $stmt->fetchAll();
        $stmt_imu = $pdo->query("SELECT * FROM imu_readings_g7b ORDER BY timestamp DESC LIMIT 50");
        $mesures_imu = $stmt_imu->fetchAll();
    } elseif ($view_group === 'C') {
        $stmt = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC");
        $mesures = $stmt->fetchAll();
    } elseif ($view_group === 'D') {
        // NOUVEAU GROUPE G7D
        try {
            $stmt = $pdo->query("SELECT * FROM mesures_dht11_g7d ORDER BY timestamp DESC LIMIT 50");
            $mesures = $stmt->fetchAll();
        } catch (\PDOException $e) {
            $db_error = "La table G7D n'existe pas encore. Exécutez la requête SQL fournie.";
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
    
    /* MODIFICATION: Les cartes sont devenues cliquables avec effet de survol */
    .ha-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: var(--shadow); position: relative; text-decoration: none; color: inherit; display: block; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
    .ha-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-color: var(--primary); }
    
    .ha-card-header { display: flex; align-items: center; gap: 12px; font-weight: bold; font-size: 1.05em; margin-bottom: 15px; }
    .ha-icon { font-size: 1.5em; background: #f1f5f9; padding: 8px; border-radius: 8px; }
    .ha-state { font-size: 1.8em; font-weight: 800; margin: 10px 0; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; position: absolute; top: 20px; right: 20px; }
    
    .radar-car { display: flex; justify-content: center; align-items: center; gap: 40px; background: #0f172a; color: white; padding: 40px 20px; border-radius: 12px; margin-bottom: 10px; position: relative; }
    .radar-car .sensor-box { text-align: center; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 8px; min-width: 160px; z-index: 2; }
    .chart-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); }
    .logbook-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9em; }
</style>

<div class="container" style="max-width: 1280px; margin-top: 20px;">
    
    <div class="group-tabs" style="margin-bottom: 30px;">
        <a href="utilisateur.php?show=home" class="tab-btn <?= $view_group === 'home' ? 'active' : '' ?>">🏠 Vue Générale</a>
        <a href="utilisateur.php?show=A" class="tab-btn <?= $view_group === 'A' ? 'active' : '' ?>">G7A (Gaz)</a>
        <a href="utilisateur.php?show=B" class="tab-btn <?= $view_group === 'B' ? 'active' : '' ?>">G7B (Recul/IMU)</a>
        <a href="utilisateur.php?show=C" class="tab-btn <?= $view_group === 'C' ? 'active' : '' ?>">G7C (Ultrason)</a>
        <a href="utilisateur.php?show=D" class="tab-btn <?= $view_group === 'D' ? 'active' : '' ?>">G7D (Temp/Hum)</a>
        <a href="utilisateur.php?show=E" class="tab-btn <?= $view_group === 'E' ? 'active' : '' ?>">G7E (Audio)</a>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert" style="background: var(--danger); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <?php if ($view_group === 'home'): ?>
        
        <div class="ha-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            
            <a href="utilisateur.php?show=A" class="ha-card">
                <?php $gas_alert = ($home_gas && $home_gas['danger_level'] != '0'); ?>
                <div class="status-dot" style="background: <?= $gas_alert ? 'var(--danger)' : 'var(--success)' ?>;"></div>
                <div class="ha-card-header"><span class="ha-icon">💨</span> Groupe A (MQ135)</div>
                <div class="ha-state"><?= $home_gas ? $home_gas['gas_value'] . ' ppm' : '--' ?></div>
                <div style="font-size: 0.85em; color: var(--text-muted);">Cliquez pour l'historique gaz</div>
            </a>

            <a href="utilisateur.php?show=D" class="ha-card">
                <div class="status-dot" style="background: var(--primary);"></div>
                <div class="ha-card-header"><span class="ha-icon">🌡️</span> Groupe D (DHT11)</div>
                <div class="ha-state"><?= $home_g7d ? htmlspecialchars($home_g7d['temperature']) . '°C' : '--' ?></div>
                <div style="font-size: 0.85em; color: var(--text-muted);">Humidité : <?= $home_g7d ? htmlspecialchars($home_g7d['humidity']) . '%' : '--' ?></div>
            </a>

            <a href="utilisateur.php?show=E" class="ha-card">
                <div class="status-dot" style="background: #4f46e5;"></div>
                <div class="ha-card-header"><span class="ha-icon">🎵</span> Groupe E (MinIO)</div>
                <div class="ha-state"><?= $home_g7e ? $home_g7e['total'] : '0' ?> fichiers</div>
                <div style="font-size: 0.85em; color: var(--text-muted);">Accéder au stockage audio</div>
            </a>
        </div>

        <h3 style="margin-top: 20px;">Radar d'approche & État Inertiel (G7B & G7C)</h3>
        <div class="radar-car">
            <div style="position: absolute; top: 15px; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 20px; font-size: 0.85em; z-index: 10;">
                IMU (Centrale Inertielle) : 
                <span style="color: <?= ($home_imu && $home_imu['state'] === 'COLLISION') ? 'var(--danger)' : '#10b981' ?>; font-weight: bold;">
                    <?= $home_imu ? htmlspecialchars($home_imu['state']) : 'INCONNU' ?>
                </span>
            </div>

            <div class="sensor-box">
                <div style="font-size: 0.85em; color: #94a3b8; text-transform: uppercase;">Avant (G7C)</div>
                <div id="home-radar-avant" style="font-size: 2.2em; font-weight: bold; color: #10b981; transition: 0.3s;">-- cm</div>
            </div>
            
            <div style="font-size: 5.5em; transform: rotate(0deg); filter: drop-shadow(0 0 10px rgba(255,255,255,0.2)); z-index: 2;">🚙</div>
            
            <div class="sensor-box">
                <div style="font-size: 0.85em; color: #94a3b8; text-transform: uppercase;">Arrière (G7B)</div>
                <div id="home-radar-arriere" style="font-size: 2.2em; font-weight: bold; color: #ef4444; transition: 0.3s;">--</div>
            </div>
        </div>

        <div class="slider-container" style="background: white; border-radius: 12px; margin-bottom: 30px; padding: 15px; border: 1px solid var(--border);">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); font-weight: bold;">
                <span>Ancien</span>
                <span id="home-slider-date" style="color: var(--primary); font-size: 1rem;">Chargement...</span>
                <span>Récent</span>
            </div>
            <input type="range" id="home-time-slider" min="0" max="<?= count($all_g7c) - 1 ?>" value="<?= count($all_g7c) - 1 ?>" style="width: 100%; margin-top: 10px;">
        </div>

        <h3 style="margin-top: 30px;">Évolution des Constantes Environnementales</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="chart-container"><canvas id="chartHumid" height="150"></canvas></div>
            <div class="chart-container"><canvas id="chartGas" height="150"></canvas></div>
            <div class="chart-container"><canvas id="chartRecul" height="150"></canvas></div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
            <div class="chart-container" style="padding: 10px; border: none; overflow: hidden;">
                <h3 style="margin: 10px;">Géolocalisation Synchronisée</h3>
                <div id="homeMap" style="height: 300px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>

            <div class="chart-container" style="overflow-y: auto; max-height: 350px;">
                <h3 style="margin-top: 0; margin-bottom: 15px;">📋 Alertes & Logbook</h3>
                <?php if (count($home_logs) > 0): ?>
                    <?php foreach ($home_logs as $log): ?>
                        <div class="logbook-item">
                            <div>
                                <?php if (str_contains(strtolower($log['subject_line']), 'danger') || str_contains(strtolower($log['subject_line']), 'urgence')): ?>
                                    <span style="color: var(--danger); font-weight: bold;">[DANGER]</span> 
                                <?php else: ?>
                                    <span style="color: var(--primary); font-weight: bold;">[INFO]</span> 
                                <?php endif; ?>
                                <?= htmlspecialchars($log['subject_line']) ?>
                            </div>
                            <div style="color: var(--text-muted); font-size: 0.85em; white-space: nowrap; margin-left: 10px;">
                                <?= date('H:i:s', strtotime($log['sent_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted); text-align: center; padding-top: 20px;">Aucune alerte enregistrée.</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Script Radar G7C / G7B
            const rawDataHome = <?= json_encode($all_g7c) ?>;
            const rawDataBHome = <?= json_encode($all_g7b) ?>;
            
            const defaultLat = rawDataHome.length > 0 ? parseFloat(rawDataHome[0].latitude) : 48.8566;
            const defaultLon = rawDataHome.length > 0 ? parseFloat(rawDataHome[0].longitude) : 2.3522;
            const mapHome = L.map('homeMap').setView([defaultLat, defaultLon], 15);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(mapHome);
            let currentHomeMarker = null;

            function getClosestBDistance(targetDateStr) {
                if (!rawDataBHome || rawDataBHome.length === 0) return '--';
                const targetTime = new Date(targetDateStr.replace(' ', 'T')).getTime();
                let closestVal = rawDataBHome[0].distance_cm;
                let minDiff = Infinity;
                for (let i = 0; i < rawDataBHome.length; i++) {
                    const bTime = new Date(rawDataBHome[i].date_evenement.replace(' ', 'T')).getTime();
                    const diff = Math.abs(bTime - targetTime);
                    if (diff < minDiff) { minDiff = diff; closestVal = rawDataBHome[i].distance_cm; }
                }
                return closestVal;
            }

            function updateHomeDash(index) {
                const actualIndex = (rawDataHome.length - 1) - index; 
                const selectedRecord = rawDataHome[actualIndex]; 
                if (!selectedRecord) return;
                
                document.getElementById('home-slider-date').innerText = selectedRecord.date_enregistrement;
                
                const distAvant = parseFloat(selectedRecord.distance_cm);
                const radarAvantEl = document.getElementById('home-radar-avant');
                
                // ALERTE VISUELLE < 10cm
                if (distAvant <= 10) {
                    radarAvantEl.style.color = 'var(--danger)';
                    radarAvantEl.innerHTML = distAvant + ' cm <br><span style="font-size:0.4em; display:block; margin-top:5px; color:var(--danger);">⚠️ OBSTACLE !</span>';
                } else {
                    radarAvantEl.style.color = '#10b981';
                    radarAvantEl.innerHTML = distAvant + ' cm';
                }
                
                const closestB = getClosestBDistance(selectedRecord.date_enregistrement);
                const unit = String(closestB).includes('>') ? '' : ' cm';
                document.getElementById('home-radar-arriere').innerText = closestB + unit;

                if (currentHomeMarker) { mapHome.removeLayer(currentHomeMarker); }
                const popup = `<b>Date:</b> ${selectedRecord.date_enregistrement}<br><b>Humidité:</b> ${selectedRecord.humidite_pourcent}%`;
                currentHomeMarker = L.marker([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]).addTo(mapHome).bindPopup(popup).openPopup();
                mapHome.panTo([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]);
            }
            
            const hSlider = document.getElementById('home-time-slider');
            hSlider.addEventListener('input', function(e) { updateHomeDash(parseInt(e.target.value)); });
            if (rawDataHome.length > 0) { updateHomeDash(rawDataHome.length - 1); }

            <?php
            $hist_c = array_reverse(array_slice($all_g7c, 0, 20));
            $lbl_hum = []; $dat_hum = [];
            foreach($hist_c as $c) { $lbl_hum[] = date('H:i', strtotime($c['date_enregistrement'])); $dat_hum[] = $c['humidite_pourcent']; }
            $lbl_gas = []; $dat_gas = [];
            foreach($hist_gas as $g) { $lbl_gas[] = date('H:i', strtotime($g['created_at'])); $dat_gas[] = $g['gas_value']; }
            $hist_b = array_reverse(array_slice($all_g7b, 0, 20));
            $lbl_rec = []; $dat_rec = [];
            foreach($hist_b as $b) { $lbl_rec[] = date('H:i', strtotime($b['date_evenement'])); $dat_rec[] = floatval(str_replace(['>','<'], '', $b['distance_cm'])); }
            ?>

            new Chart(document.getElementById('chartHumid'), { type: 'line', data: { labels: <?= json_encode($lbl_hum) ?>, datasets: [{ label: 'Humidité du sol (%)', data: <?= json_encode($dat_hum) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.4 }] }, options: { plugins: { legend: { display: true } } } });
            new Chart(document.getElementById('chartGas'), { type: 'line', data: { labels: <?= json_encode($lbl_gas) ?>, datasets: [{ label: 'Taux de Gaz (ppm)', data: <?= json_encode($dat_gas) ?>, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.1)', fill: true, tension: 0.4 }] } });
            new Chart(document.getElementById('chartRecul'), { type: 'bar', data: { labels: <?= json_encode($lbl_rec) ?>, datasets: [{ label: 'Distance Arrière (cm)', data: <?= json_encode($dat_rec) ?>, backgroundColor: '#f59e0b', borderRadius: 4 }] } });
        </script>

    <?php elseif ($view_group === 'A'): ?>
        <h3>Historique Complet des Gaz</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Date</th><th>Type Gaz</th><th>Valeur</th><th>Danger</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr><td><?= $m['created_at'] ?></td><td><?= htmlspecialchars($m['gas_type']) ?></td><td><strong><?= $m['gas_value'] ?> ppm</strong></td><td><span class="badge" style="background: <?= $m['danger_level'] == '0' ? 'var(--success)' : 'var(--danger)' ?>;"><?= $m['danger_level'] == '0' ? 'Normal' : 'Danger' ?></span></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'B'): ?>
        <h3>Historique Recul & IMU Brut</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Date</th><th>Valeur Brute</th><th>Distance</th><th>Statut</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr><td><?= $m['date_evenement'] ?></td><td><?= $m['valeur_brute'] ?></td><td><?= htmlspecialchars($m['distance_cm']) ?> cm</td><td><span class="badge" style="background: <?= $m['statut'] === 'alerte collision' ? 'var(--danger)' : 'var(--primary)' ?>;"><?= htmlspecialchars($m['statut']) ?></span></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'C'): ?>
        <h3>Journal Complet des Données G7C</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Distance Ultrason</th><th>Humidité</th><th>Altitude</th><th>Latitude</th><th>Longitude</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr>
                        <td><?= $m['date_enregistrement'] ?></td>
                        <td><strong><?= $m['distance_cm'] ?> cm</strong></td>
                        <td><?= $m['humidite_pourcent'] ?> %</td>
                        <td><?= $m['altitude'] ?> m</td>
                        <td><?= $m['latitude'] ?></td>
                        <td><?= $m['longitude'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'D'): ?>
        <h3>Relevés Climatiques (Capteur DHT11)</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Température</th><th>Humidité de l'air</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr>
                        <td><?= $m['timestamp'] ?></td>
                        <td><strong style="color: #ef4444;"><?= $m['temperature'] ?> °C</strong></td>
                        <td><strong style="color: #3b82f6;"><?= $m['humidity'] ?> %</strong></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'E'): ?>
        <h3>Fichiers Multimédias Archivés — MinIO</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Date d'envoi</th><th>Nom du Fichier</th><th>Lecture Audio</th><th>Taille</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($m['uploadedAt'])) ?></td><td><strong>📁 <?= htmlspecialchars($m['filename']) ?></strong></td>
                        <td><?php $minioBaseUrl = "http://178.33.122.21:9000"; $audioUrl = $minioBaseUrl . "/" . $m['minioBucket'] . "/" . $m['minioPath']; ?><audio controls preload="none" style="height: 35px; width: 220px;"><source src="<?= htmlspecialchars($audioUrl) ?>" type="audio/wav"></audio></td>
                        <td><?= $m['fileSize'] ? round($m['fileSize'] / (1024 * 1024), 2) . " Mo" : "0 Mo" ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>