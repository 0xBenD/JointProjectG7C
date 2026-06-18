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
$home_g7d = null;
$home_logs = [];

try {
    // ==========================================
    // 1. REQUÊTES DASHBOARD GLOBAL (HOME)
    // ==========================================
    if ($view_group === 'home') {
        $stmt = $pdo->query("SELECT gas_value, danger_level FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 1");
        $home_gas = $stmt->fetch();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM G7E_audiofiles");
        $home_g7e = $stmt->fetch();

        $stmt = $pdo->query("SELECT state FROM imu_readings_g7b ORDER BY timestamp DESC LIMIT 1");
        $home_imu = $stmt->fetch();

        try {
            $stmt = $pdo->query("SELECT temperature, humidity, timestamp FROM mesures_dht11_g7d ORDER BY timestamp DESC LIMIT 1");
            $home_g7d = $stmt->fetch();
        } catch (\PDOException $e) { $home_g7d = null; }

        $stmt_c_all = $pdo->query("SELECT * FROM mesures_capteurs_g7c ORDER BY date_enregistrement DESC");
        $all_g7c = $stmt_c_all->fetchAll();
        
        $stmt_b_all = $pdo->query("SELECT distance_cm, date_evenement FROM historique_capteur_g7b_recul ORDER BY date_evenement DESC");
        $all_g7b = $stmt_b_all->fetchAll();

        $stmt_gas_all = $pdo->query("SELECT gas_value, created_at FROM gas_measures_g7a ORDER BY created_at DESC LIMIT 20");
        $hist_gas = array_reverse($stmt_gas_all->fetchAll());

        // LOGBOOK INTÉGRÉ
        $stmt = $pdo->query("SELECT * FROM event_notification_log ORDER BY sent_at DESC LIMIT 30");
        $home_logs = $stmt->fetchAll();

        if (!empty($all_g7c)) {
            foreach ($all_g7c as $m) {
                if (floatval($m['distance_cm']) < 10) {
                    $home_logs[] = ['subject_line' => '🚨 OBSTACLE AVANT (' . $m['distance_cm'] . ' cm)', 'sent_at' => $m['date_enregistrement']];
                }
                if (isset($m['radiation_usv']) && floatval($m['radiation_usv']) > 2.0) {
                    $home_logs[] = ['subject_line' => '☢️ DANGER RADIOLOGIQUE (' . $m['radiation_usv'] . ' µSv/h)', 'sent_at' => $m['date_enregistrement']];
                }
            }
        }
        usort($home_logs, function($a, $b) { return strtotime($b['sent_at']) - strtotime($a['sent_at']); });
        $home_logs = array_slice($home_logs, 0, 15);
    }
    
    // ==========================================
    // 2. VUES DÉTAILLÉES
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
        try {
            $stmt = $pdo->query("SELECT * FROM mesures_dht11_g7d ORDER BY timestamp DESC LIMIT 50");
            $mesures = $stmt->fetchAll();
        } catch (\PDOException $e) { $db_error = "La table G7D n'existe pas encore."; }
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
    /* STYLES OPÉRATIONNELS ENTIÈREMENT EN MODE CLAIR */
    .flash-alert { background: var(--danger); color: white; padding: 15px; text-align: center; font-weight: 900; font-size: 1.2em; text-transform: uppercase; animation: flash 1s infinite; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 0 15px rgba(239, 68, 68, 0.5); }
    @keyframes flash { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

    .live-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: bold; font-size: 0.85em; padding: 6px 15px; border-radius: 20px; color: white; cursor: pointer; transition: 0.3s; }
    .live-badge.on { background: var(--danger); animation: pulseLive 2s infinite; }
    .live-badge.off { background: #f59e0b; animation: none; }
    @keyframes pulseLive { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

    /* KPIs en Mode Clair */
    .kpi-main-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .kpi-main-card { background: white; color: var(--text-main); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; overflow: hidden; }
    .kpi-main-title { font-size: 0.75em; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; font-weight: bold; margin-bottom: 10px; }
    .kpi-main-value { font-size: 2.2em; font-weight: 900; }

    .ha-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
    .ha-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: var(--shadow); position: relative; display: block; text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s; }
    .ha-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-color: var(--primary); }
    
    /* Radar en Mode Clair */
    .radar-car { display: flex; justify-content: center; align-items: center; gap: 40px; background: white; color: var(--text-main); padding: 40px 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 10px; position: relative; }
    .sensor-box { text-align: center; background: #f8fafc; padding: 15px 25px; border-radius: 8px; border: 1px solid var(--border); min-width: 140px; z-index: 2; }
    
    .chart-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); }
    .logbook-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9em; }
    .chart-grid-2x2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(45%, 1fr)); gap: 20px; margin-bottom: 30px; }
    
    /* Classes de surbrillance pour les tableaux */
    .row-danger { background-color: rgba(239, 68, 68, 0.15) !important; border-left: 4px solid #ef4444; }
    .row-warning { background-color: rgba(245, 158, 11, 0.15) !important; border-left: 4px solid #f59e0b; }
</style>

<div class="container" style="max-width: 1280px; margin-top: 20px;">
    
    <div class="group-tabs" style="margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <a href="utilisateur.php?show=home" class="tab-btn <?= $view_group === 'home' ? 'active' : '' ?>">🏠 Command Center</a>
        <a href="utilisateur.php?show=C" class="tab-btn <?= $view_group === 'C' ? 'active' : '' ?>">🚜 G7C (Votre Rover)</a>
        <a href="utilisateur.php?show=A" class="tab-btn <?= $view_group === 'A' ? 'active' : '' ?>">💨 G7A (Air)</a>
        <a href="utilisateur.php?show=B" class="tab-btn <?= $view_group === 'B' ? 'active' : '' ?>">🚨 G7B (Recul)</a>
        <a href="utilisateur.php?show=D" class="tab-btn <?= $view_group === 'D' ? 'active' : '' ?>">🌡️ G7D (Climat)</a>
        <a href="utilisateur.php?show=E" class="tab-btn <?= $view_group === 'E' ? 'active' : '' ?>">🎵 G7E (Audio)</a>
    </div>

    <?php if ($view_group === 'home' && !empty($all_g7c)): ?>
        <?php 
        $latest = $all_g7c[0];
        $is_global_danger = false;
        $global_danger_msg = [];

        if (floatval($latest['distance_cm']) < 10) { 
            $is_global_danger = true; 
            $global_danger_msg[] = "COLLISION IMMINENTE (" . $latest['distance_cm'] . " cm)"; 
        }
        if (isset($latest['radiation_usv']) && floatval($latest['radiation_usv']) > 2.0) { 
            $is_global_danger = true; 
            $global_danger_msg[] = "ZONE FORTEMENT RADIOACTIVE (" . $latest['radiation_usv'] . " µSv/h)"; 
        }

        if ($is_global_danger): 
        ?>
            <div class="flash-alert">
                ⚠️ ALERTE CRITIQUE : <?= implode(" | ", $global_danger_msg) ?> ⚠️
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($db_error)): ?>
        <div class="alert" style="background: var(--danger); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <?php if ($view_group === 'home'): ?>
        
        <div class="kpi-main-grid">
            <?php 
            $latest_rad = isset($all_g7c[0]['radiation_usv']) ? floatval($all_g7c[0]['radiation_usv']) : 0;
            $rad_color = $latest_rad > 2 ? 'var(--danger)' : ($latest_rad > 0.5 ? '#f59e0b' : '#10b981');
            $dist_avant = isset($all_g7c[0]['distance_cm']) ? floatval($all_g7c[0]['distance_cm']) : 999;
            $dist_color = $dist_avant < 10 ? 'var(--danger)' : '#10b981';
            $alt = isset($all_g7c[0]['altitude']) ? htmlspecialchars($all_g7c[0]['altitude']) : '--';
            ?>
            <div class="kpi-main-card" style="border-bottom: 4px solid <?= $rad_color ?>;">
                <div class="kpi-main-title">Niveau de Radiation</div>
                <div class="kpi-main-value" style="color: <?= $rad_color ?>;"><?= $latest_rad ?> <span style="font-size: 0.5em;">µSv/h</span></div>
            </div>
            <div class="kpi-main-card" style="border-bottom: 4px solid <?= $dist_color ?>;">
                <div class="kpi-main-title">Obstacle Avant</div>
                <div class="kpi-main-value" style="color: <?= $dist_color ?>;"><?= $dist_avant ?> <span style="font-size: 0.5em;">cm</span></div>
            </div>
            <div class="kpi-main-card" style="border-bottom: 4px solid #3b82f6;">
                <div class="kpi-main-title">Altitude Actuelle</div>
                <div class="kpi-main-value" style="color: #3b82f6;"><?= $alt ?> <span style="font-size: 0.5em;">mètres</span></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; margin-top: 20px;">
            <h3 style="margin: 0;">Radar Télémétrique & IMU</h3>
            
            <div id="live-toggle" class="live-badge on" onclick="toggleLiveMode()">
                <span id="live-icon">🔴</span> <span id="live-text">EN DIRECT</span>
            </div>
        </div>

        <div class="radar-car">
            <div style="position: absolute; top: 15px; background: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85em; z-index: 10; border: 1px solid var(--border); box-shadow: var(--shadow);">
                IMU : <span style="color: <?= ($home_imu && $home_imu['state'] === 'COLLISION') ? 'var(--danger)' : '#10b981' ?>; font-weight: bold;"><?= $home_imu ? htmlspecialchars($home_imu['state']) : 'INCONNU' ?></span>
            </div>
            
            <div class="sensor-box">
                <div style="font-size: 0.85em; color: var(--text-muted); text-transform: uppercase;">Avant (G7C)</div>
                <div id="home-radar-avant" style="font-size: 2.2em; font-weight: bold; color: #10b981;">-- cm</div>
            </div>
            
            <div style="font-size: 5.5em; filter: drop-shadow(0 5px 5px rgba(0,0,0,0.1)); z-index: 2;">🚜</div>
            
            <div class="sensor-box">
                <div style="font-size: 0.85em; color: var(--text-muted); text-transform: uppercase;">Arrière (G7B)</div>
                <div id="home-radar-arriere" style="font-size: 2.2em; font-weight: bold; color: #ef4444;">--</div>
            </div>
        </div>

        <div class="slider-container">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); font-weight: bold;">
                <span>Ancien</span>
                <span id="home-slider-date" style="color: var(--primary); font-size: 1rem;">Chargement...</span>
                <span>Récent</span>
            </div>
            <input type="range" id="home-time-slider" min="0" max="<?= count($all_g7c) - 1 ?>" value="<?= count($all_g7c) - 1 ?>" style="width: 100%; margin-top: 10px;">
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="chart-container" style="padding: 10px; border: none; overflow: hidden;">
                <h3 style="margin: 10px;">Tracé de la Zone d'Exploration</h3>
                <div id="homeMap" style="height: 400px; width: 100%; border-radius: 8px; z-index: 1;"></div>
            </div>
            <div class="chart-container" style="overflow-y: auto; max-height: 450px;">
                <h3 style="margin-top: 0; margin-bottom: 15px;">📋 Logbook d'Opération</h3>
                <?php if (count($home_logs) > 0): ?>
                    <?php foreach ($home_logs as $log): ?>
                        <?php 
                        $subj = strtolower($log['subject_line']);
                        $is_danger = str_contains($subj, 'danger') || str_contains($subj, 'urgence') || str_contains($subj, 'obstacle') || str_contains($subj, 'alert') || str_contains($subj, 'collision');
                        ?>
                        <div class="logbook-item">
                            <div>
                                <?php if ($is_danger): ?>
                                    <span style="color: var(--danger); font-weight: bold;">[ALERTE]</span> 
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

        <h3 style="margin-top: 30px;">📊 Capteurs Environnementaux Secondaires</h3>
        <div class="ha-grid">
            <a href="utilisateur.php?show=A" class="ha-card">
                <?php $gas_alert = ($home_gas && $home_gas['danger_level'] != '0'); ?>
                <div class="status-dot" style="background: <?= $gas_alert ? 'var(--danger)' : 'var(--success)' ?>;"></div>
                <div style="font-weight:bold; margin-bottom:5px; color: var(--text-muted);">💨 Qualité Air (G7A)</div>
                <div style="font-size: 1.8em; font-weight: bold; color: var(--text-main);"><?= $home_gas ? $home_gas['gas_value'] . ' ppm' : '--' ?></div>
            </a>
            <a href="utilisateur.php?show=D" class="ha-card">
                <div class="status-dot" style="background: var(--primary);"></div>
                <div style="font-weight:bold; margin-bottom:5px; color: var(--text-muted);">🌡️ Climat (G7D)</div>
                <div style="font-size: 1.8em; font-weight: bold; color: var(--text-main);"><?= $home_g7d ? htmlspecialchars($home_g7d['temperature']) . '°C' : '--' ?></div>
                <div style="color: var(--text-muted); font-size: 0.8em;"><?= $home_g7d ? htmlspecialchars($home_g7d['humidity']) . '% Humidité' : '' ?></div>
            </a>
            <a href="utilisateur.php?show=E" class="ha-card">
                <div class="status-dot" style="background: #4f46e5;"></div>
                <div style="font-weight:bold; margin-bottom:5px; color: var(--text-muted);">🎵 Serveur Audio (G7E)</div>
                <div style="font-size: 1.8em; font-weight: bold; color: var(--text-main);"><?= $home_g7e ? $home_g7e['total'] : '0' ?> <span style="font-size:0.5em;">fichiers</span></div>
            </a>
        </div>

        <script>
            // --- GESTION DU MODE LIVE ---
            let isLiveMode = localStorage.getItem('rover_live_mode') !== 'false';
            const liveToggle = document.getElementById('live-toggle');
            const liveIcon = document.getElementById('live-icon');
            const liveText = document.getElementById('live-text');

            function applyLiveUI() {
                if(isLiveMode) {
                    liveToggle.className = "live-badge on";
                    liveIcon.innerText = "🔴"; liveText.innerText = "EN DIRECT";
                } else {
                    liveToggle.className = "live-badge off";
                    liveIcon.innerText = "🟠"; liveText.innerText = "MODE HISTORIQUE";
                }
            }

            function toggleLiveMode() {
                isLiveMode = !isLiveMode;
                localStorage.setItem('rover_live_mode', isLiveMode ? 'true' : 'false');
                applyLiveUI();
                if(isLiveMode) window.location.reload();
            }

            setInterval(() => {
                if(isLiveMode) window.location.reload();
            }, 5000);

            applyLiveUI();

            // --- GESTION CARTE (MISE EN MODE CLAIR) ET SLIDER ---
            const rawDataHome = <?= json_encode($all_g7c) ?>;
            const rawDataBHome = <?= json_encode($all_g7b) ?>;
            const defaultLat = rawDataHome.length > 0 ? parseFloat(rawDataHome[0].latitude) : 48.8566;
            const defaultLon = rawDataHome.length > 0 ? parseFloat(rawDataHome[0].longitude) : 2.3522;
            const mapHome = L.map('homeMap').setView([defaultLat, defaultLon], 16); 
            
            // MAP EN MODE CLAIR UNIFIÉ
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(mapHome);
            
            let currentHomeMarker = null;
            let prevPoint = null;
            let chronologicalData = [...rawDataHome].reverse(); 
            
            chronologicalData.forEach(point => {
                if(point.latitude && point.longitude) {
                    let currentLatLng = [parseFloat(point.latitude), parseFloat(point.longitude)];
                    let rad = parseFloat(point.radiation_usv) || 0.1;
                    let color = '#10b981'; // Vert
                    if (rad > 2.0) color = '#ef4444'; // Rouge
                    else if (rad > 0.5) color = '#f59e0b'; // Orange

                    L.circle(currentLatLng, { color: color, fillColor: color, fillOpacity: 0.6, weight: 0, radius: 5 }).addTo(mapHome);

                    if (prevPoint) {
                        L.polyline([prevPoint, currentLatLng], { color: color, weight: 4, opacity: 0.8 }).addTo(mapHome);
                    }
                    prevPoint = currentLatLng;
                }
            });

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
                if (distAvant < 10) { 
                    radarAvantEl.style.color = 'var(--danger)'; 
                    radarAvantEl.innerHTML = distAvant + ' cm <br><span style="font-size:0.4em; display:block; margin-top:5px; color:var(--danger);">⚠️ OBSTACLE !</span>'; 
                } else { 
                    radarAvantEl.style.color = '#10b981'; radarAvantEl.innerHTML = distAvant + ' cm'; 
                }
                
                const closestB = getClosestBDistance(selectedRecord.date_enregistrement);
                const unit = String(closestB).includes('>') ? '' : ' cm';
                document.getElementById('home-radar-arriere').innerText = closestB + unit;

                if (currentHomeMarker) { mapHome.removeLayer(currentHomeMarker); }
                const radVal = selectedRecord.radiation_usv ? selectedRecord.radiation_usv : 'N/A';
                
                let customIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='font-size:24px;'>🚜</div>", iconSize: [30, 30], iconAnchor: [15, 15] });
                const popup = `<b>Date:</b> ${selectedRecord.date_enregistrement}<br><b>Altitude:</b> ${selectedRecord.altitude}m<br><b>Radiation:</b> ${radVal} µSv/h`;
                
                currentHomeMarker = L.marker([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)], {icon: customIcon}).addTo(mapHome).bindPopup(popup).openPopup();
                mapHome.panTo([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]);
            }
            
            const hSlider = document.getElementById('home-time-slider');
            hSlider.addEventListener('input', function(e) { 
                if(isLiveMode) {
                    isLiveMode = false;
                    localStorage.setItem('rover_live_mode', 'false');
                    applyLiveUI();
                }
                updateHomeDash(parseInt(e.target.value)); 
            });
            
            if (rawDataHome.length > 0) { 
                updateHomeDash(rawDataHome.length - 1); 
            }
        </script>

    <?php elseif ($view_group === 'C'): ?>
        <h3>Analyse Détaillée G7C (Humidité & Radiation)</h3>
        <div class="chart-grid-2x2">
            <div class="chart-container"><canvas id="tabChartHumidC" height="200"></canvas></div>
            <div class="chart-container"><canvas id="tabChartRadC" height="200"></canvas></div>
        </div>
        <script>
            <?php
            $lbl_c = []; $dat_hum_c = []; $dat_rad_c = [];
            foreach (array_reverse($mesures) as $m) { 
                $lbl_c[] = date('H:i:s', strtotime($m['date_enregistrement'])); $dat_hum_c[] = $m['humidite_pourcent']; $dat_rad_c[] = isset($m['radiation_usv']) ? $m['radiation_usv'] : 0.1; 
            }
            ?>
            const chartOptionsC = { maintainAspectRatio: false, responsive: true };
            new Chart(document.getElementById('tabChartHumidC'), { type: 'line', data: { labels: <?= json_encode($lbl_c) ?>, datasets: [{ label: 'Humidité du sol (%)', data: <?= json_encode($dat_hum_c) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.3 }] }, options: chartOptionsC });
            new Chart(document.getElementById('tabChartRadC'), { type: 'line', data: { labels: <?= json_encode($lbl_c) ?>, datasets: [{ label: 'Radiation (µSv/h)', data: <?= json_encode($dat_rad_c) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', fill: true, tension: 0.3 }] }, options: chartOptionsC });
        </script>

        <h3>Journal Complet des Données G7C</h3>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Distance</th><th>Radiation</th><th>Altitude</th><th>Humidité</th><th>GPS</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <?php 
                    $dist = floatval($m['distance_cm']);
                    $rad = isset($m['radiation_usv']) ? floatval($m['radiation_usv']) : 0;
                    
                    $estObstacle = ($dist < 10);
                    $estRadioactif = ($rad > 2.0);
                    
                    // LOGIQUE DES COULEURS DE LIGNE
                    $rowStyle = '';
                    if ($estRadioactif) {
                        // Priorité à la radiation : Rouge Vif
                        $rowStyle = 'class="row-danger" style="color: #b91c1c; font-weight: bold;"';
                    } elseif ($estObstacle) {
                        // Ensuite l'obstacle : Orange
                        $rowStyle = 'class="row-warning" style="color: #b45309;"';
                    }
                    ?>
                    <tr <?= $rowStyle ?>>
                        <td><?= $m['date_enregistrement'] ?></td>
                        <td><strong><?= $m['distance_cm'] ?> cm</strong></td>
                        <td><strong><?= isset($m['radiation_usv']) ? $m['radiation_usv'] . ' µSv/h' : '--' ?></strong></td>
                        <td><strong style="<?= ($estRadioactif || $estObstacle) ? '' : 'color: #3b82f6;' ?>"><?= $m['altitude'] ?> m</strong></td>
                        <td><?= $m['humidite_pourcent'] ?> %</td>
                        <td><span style="font-size: 0.8em; opacity: 0.8;"><?= $m['latitude'] ?>, <?= $m['longitude'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'A'): ?>
        <h3>Historique Complet des Gaz</h3>
        <div class="chart-container" style="margin-bottom: 30px;"><canvas id="tabChartA" height="100"></canvas></div>
        <script>
            <?php $lbl_a = []; $dat_a = []; foreach (array_reverse($mesures) as $m) { $lbl_a[] = date('H:i:s', strtotime($m['created_at'])); $dat_a[] = $m['gas_value']; } ?>
            new Chart(document.getElementById('tabChartA'), { type: 'line', data: { labels: <?= json_encode($lbl_a) ?>, datasets: [{ label: 'Concentration Globale MQ135 (ppm)', data: <?= json_encode($dat_a) ?>, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.1)', fill: true, tension: 0.3 }] } });
        </script>
        <div class="table-responsive">
            <table>
                <tr><th>Date</th><th>Type Gaz</th><th>Valeur</th><th>Danger</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <?php 
                    $danger = ($m['danger_level'] != '0');
                    $rowClass = $danger ? 'class="row-danger" style="color: #b91c1c; font-weight: bold;"' : '';
                    ?>
                    <tr <?= $rowClass ?>>
                        <td><?= $m['created_at'] ?></td>
                        <td><?= htmlspecialchars($m['gas_type']) ?></td>
                        <td><strong><?= $m['gas_value'] ?> ppm</strong></td>
                        <td>
                            <?php if($danger): ?>
                                ⚠️ DANGER
                            <?php else: ?>
                                <span class="badge" style="background: var(--success);">Normal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'B'): ?>
        <h3>Historique Recul & IMU Brut</h3>
        <div class="chart-container" style="margin-bottom: 30px;"><canvas id="tabChartB" height="100"></canvas></div>
        <script>
            <?php $lbl_b = []; $dat_b = []; foreach (array_reverse($mesures) as $m) { $lbl_b[] = date('H:i:s', strtotime($m['date_evenement'])); $dat_b[] = floatval(str_replace(['>','<'], '', $m['distance_cm'])); } ?>
            new Chart(document.getElementById('tabChartB'), { type: 'bar', data: { labels: <?= json_encode($lbl_b) ?>, datasets: [{ label: 'Historique Télémétrie Arrière (cm)', data: <?= json_encode($dat_b) ?>, backgroundColor: '#f59e0b', borderRadius: 4 }] } });
        </script>
        <div class="table-responsive">
            <table>
                <tr><th>Date</th><th>Valeur Brute</th><th>Distance</th><th>Statut</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <?php 
                    $alerte_col = ($m['statut'] === 'alerte collision');
                    $rowClass = $alerte_col ? 'class="row-danger" style="color: #b91c1c; font-weight: bold;"' : '';
                    ?>
                    <tr <?= $rowClass ?>>
                        <td><?= $m['date_evenement'] ?></td>
                        <td><?= $m['valeur_brute'] ?></td>
                        <td><?= htmlspecialchars($m['distance_cm']) ?> cm</td>
                        <td>
                            <?php if($alerte_col): ?>
                                ⚠️ COLLISION
                            <?php else: ?>
                                <span class="badge" style="background: var(--primary);"><?= htmlspecialchars($m['statut']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($view_group === 'D'): ?>
        <h3>Relevés Climatiques (Capteur DHT11)</h3>
        <div class="chart-container" style="margin-bottom: 30px;"><canvas id="tabChartD" height="100"></canvas></div>
        <script>
            <?php $lbl_d = []; $dat_temp = []; $dat_hum = []; foreach (array_reverse($mesures) as $m) { $lbl_d[] = date('H:i:s', strtotime($m['timestamp'])); $dat_temp[] = $m['temperature']; $dat_hum[] = $m['humidity']; } ?>
            new Chart(document.getElementById('tabChartD'), { type: 'line', data: { labels: <?= json_encode($lbl_d) ?>, datasets: [ { label: 'Température (°C)', data: <?= json_encode($dat_temp) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', fill: true, tension: 0.3 }, { label: 'Humidité (%)', data: <?= json_encode($dat_hum) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.3 } ] } });
        </script>
        <div class="table-responsive">
            <table>
                <tr><th>Horodatage</th><th>Température</th><th>Humidité de l'air</th></tr>
                <?php foreach ($mesures as $m): ?>
                    <tr><td><?= $m['timestamp'] ?></td><td><strong style="color: #ef4444;"><?= $m['temperature'] ?> °C</strong></td><td><strong style="color: #3b82f6;"><?= $m['humidity'] ?> %</strong></td></tr>
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