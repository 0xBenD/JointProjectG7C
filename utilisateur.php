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

        // LOGBOOK
        $stmt = $pdo->query("SELECT * FROM event_notification_log ORDER BY sent_at DESC LIMIT 30");
        $home_logs = $stmt->fetchAll();

        if (!empty($all_g7c)) {
            foreach ($all_g7c as $m) {
                if (floatval($m['distance_cm']) <= 10) {
                    $home_logs[] = ['subject_line' => '🚨 OBSTACLE AVANT (' . $m['distance_cm'] . ' cm)', 'sent_at' => $m['date_enregistrement']];
                }
                if (isset($m['radiation_usv']) && floatval($m['radiation_usv']) >= 400.0) {
                    $home_logs[] = ['subject_line' => '☢️ DANGER DE MORT RADIOLOGIQUE (' . $m['radiation_usv'] . ' mSv/h)', 'sent_at' => $m['date_enregistrement']];
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

// L'inclusion du header natif
include 'header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* =======================================================
       CORRECTION DE LA PAGE BLANCHE 
       ======================================================= */
    /* On masque silencieusement l'ancien header et footer */
    header, footer { display: none !important; }
    /* On retire les restrictions de l'ancien conteneur */
    .container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

    body, html { margin: 0; padding: 0; height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; overflow: hidden; }
    
    /* Notre application devient une interface plein écran qui recouvre tout */
    .app-wrapper { 
        position: fixed; 
        top: 0; left: 0; right: 0; bottom: 0; 
        z-index: 9999; 
        display: flex; 
        background-color: #f8fafc;
        overflow: hidden; 
    }
    
    /* SIDEBAR (Navigation Latérale) */
    .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; flex-shrink: 0; }
    .sidebar-header { padding: 25px 20px; display: flex; align-items: center; gap: 15px; }
    .sidebar-header .bot-icon { font-size: 2.2em; background: #f1f5f9; padding: 10px; border-radius: 12px; }
    .sidebar-header .title { font-weight: 900; font-size: 1.2em; color: #0f172a; margin-bottom: 4px; }
    .sidebar-header .subtitle { font-size: 0.85em; color: #10b981; display: flex; align-items: center; gap: 6px; font-weight: 600; }
    .sidebar-header .subtitle::before { content: ''; width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; }
    
    .sidebar-nav { padding: 10px 0; flex: 1; overflow-y: auto; display: block; }
    .nav-section { margin-bottom: 25px; }
    .nav-title { font-size: 0.7em; text-transform: uppercase; color: #94a3b8; font-weight: 800; padding: 0 20px; margin-bottom: 10px; letter-spacing: 1px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px 10px 16px; color: #475569; text-decoration: none; font-weight: 600; font-size: 0.95em; transition: 0.2s; border-left: 4px solid transparent; }
    .nav-item:hover { background: #f8fafc; color: #0f172a; }
    .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; }
    
    .sidebar-footer { padding: 20px; border-top: 1px solid #e2e8f0; }
    .btn-logout { color: #ef4444; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9em; }

    /* ZONE DE CONTENU PRINCIPALE */
    .main-area { flex: 1; overflow-y: auto; background-color: #f8fafc; padding: 30px; position: relative; }
    .page-title { font-size: 1.8em; font-weight: 800; color: #0f172a; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; }
    
    /* ALERTE FLASH */
    .flash-alert-banner { background: #ef4444; color: white; padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: center; font-weight: 900; font-size: 1.1em; letter-spacing: 1px; animation: pulseRed 1.5s infinite; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); display: none; }
    @keyframes pulseRed { 0% { opacity: 1; } 50% { opacity: 0.8; } 100% { opacity: 1; } }

    /* LE NOUVEAU RADAR (Barres de progression) */
    .telemetry-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; gap: 50px; margin-bottom: 25px; }
    .tel-side { text-align: center; width: 220px; }
    .tel-label { font-size: 0.8em; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 1px; }
    .tel-bar-bg { background: #f1f5f9; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 15px; display: flex; }
    .tel-bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s ease, background-color 0.3s ease; }
    .tel-value { font-size: 2.2em; font-weight: 900; color: #0f172a; margin-bottom: 10px; }
    .tel-status { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.75em; font-weight: 800; letter-spacing: 1px; }
    .status-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
    .status-safe { background: #f0fdf4; color: #10b981; border: 1px solid #bbf7d0; }
    
    .tel-robot { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; }
    .tel-robot-icon { font-size: 4.5em; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
    .tel-robot-label { font-weight: 800; color: #475569; letter-spacing: 2px; font-size: 0.85em; }

    /* GRILLES & CARTES KPI */
    .dashboard-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-bottom: 25px; }
    @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } .telemetry-card { flex-direction: column; gap: 20px; } .sidebar { width: 200px; } }
    
    .kpi-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .kpi-title { font-size: 0.75em; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; letter-spacing: 1px; }
    .kpi-value { font-size: 1.8em; font-weight: 900; color: #0f172a; }
    
    /* SLIDER & MAP */
    .slider-box { background: white; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .map-container { height: 400px; width: 100%; border-radius: 12px; z-index: 1; }
    
    /* LOGBOOK */
    .log-container { max-height: 350px; overflow-y: auto; padding-right: 10px; }
    .log-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9em; }
    .log-item:last-child { border-bottom: none; }

    /* LIVE TOGGLE BTN */
    .live-btn { background: white; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 20px; font-size: 0.8em; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; color: #475569; }
    .live-btn.on { border-color: #fca5a5; background: #fef2f2; color: #dc2626; }
    .live-btn.on .dot { background: #dc2626; animation: pulseDot 1.5s infinite; }
    .live-btn .dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; display: inline-block; }
    @keyframes pulseDot { 0% { box-shadow: 0 0 0 0 rgba(220,38,38,0.4); } 70% { box-shadow: 0 0 0 6px rgba(220,38,38,0); } 100% { box-shadow: 0 0 0 0; } }

    /* TABLE */
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    th, td { padding: 15px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.8em; letter-spacing: 1px; }
    .row-mortal { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; color: #7f1d1d; }
    .row-warning { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; color: #92400e; }
</style>

<div class="app-wrapper">
    <aside class="sidebar">
        <a href="utilisateur.php?show=home" class="sidebar-header" style="text-decoration: none; color: inherit;">
            <div class="bot-icon">🤖</div>
            <div>
                <div class="title">Hangar G7</div> <div class="subtitle"><?= htmlspecialchars($_SESSION['username'] ?? 'Opérateur') ?></div>
            </div>
        </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Navigation</div>
                <a href="utilisateur.php?show=home" class="nav-item <?= $view_group === 'home' ? 'active' : '' ?>">🏠 Home Dashboard</a>
            </div>
            
            <div class="nav-section">
                <div class="nav-title">Primary · G7C</div>
                <a href="utilisateur.php?show=C" class="nav-item <?= $view_group === 'C' ? 'active' : '' ?>">📍 GPS, Rad & Sonar</a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Secondary Modules</div>
                <a href="utilisateur.php?show=B" class="nav-item <?= $view_group === 'B' ? 'active' : '' ?>">🚨 Rear & Kin. (G7B)</a>
                <a href="utilisateur.php?show=A" class="nav-item <?= $view_group === 'A' ? 'active' : '' ?>">💨 Gas & Env. (G7A)</a>
                <a href="utilisateur.php?show=D" class="nav-item <?= $view_group === 'D' ? 'active' : '' ?>">🌡️ Atmosphere (G7D)</a>
                <a href="utilisateur.php?show=E" class="nav-item <?= $view_group === 'E' ? 'active' : '' ?>">🎙️ Audio Feed (G7E)</a>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <a href="profile.php" class="nav-item" style="color: #2563eb; margin-bottom: 5px;">⚙️ Gérer mon profil</a>
            <a href="connection.php?logout=1" class="btn-logout">❌ Terminate Session</a>
        </div>
    </aside>

    <main class="main-area">
        
        <?php if (isset($db_error)): ?>
            <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <div id="flash-alert-banner" class="flash-alert-banner"></div>

        <?php if ($view_group === 'home'): ?>
            
            <div class="page-title">
                Rover Telemetry Hub
                <button id="live-toggle" class="live-btn on" onclick="toggleLiveMode()">
                    <span class="dot"></span> <span id="live-text">Live Sync</span>
                </button>
            </div>

            <div class="telemetry-card">
                <div class="tel-side">
                    <div class="tel-label">Rear Sonar</div>
                    <div class="tel-bar-bg" style="justify-content: flex-end;">
                        <div id="bar-rear" class="tel-bar-fill" style="width: 0%; background: #e2e8f0;"></div>
                    </div>
                    <div id="val-rear" class="tel-value">-- cm</div>
                    <div id="status-rear" class="tel-status" style="background:#f1f5f9; color:#64748b; border: 1px solid #cbd5e1;">WAITING</div>
                </div>
                
                <div class="tel-robot">
                    <div class="tel-robot-icon">🤖</div>
                    <div class="tel-robot-label">ROVER G7</div>
                    <div style="margin-top: 5px; font-size: 0.7em; color: #94a3b8;">
                        IMU: <span id="imu-status" style="color: <?= ($home_imu && $home_imu['state'] === 'COLLISION') ? '#ef4444' : '#10b981' ?>; font-weight: bold;"><?= $home_imu ? htmlspecialchars($home_imu['state']) : '--' ?></span>
                    </div>
                </div>

                <div class="tel-side">
                    <div class="tel-label">Front Sonar</div>
                    <div class="tel-bar-bg">
                        <div id="bar-front" class="tel-bar-fill" style="width: 0%; background: #e2e8f0;"></div>
                    </div>
                    <div id="val-front" class="tel-value">-- cm</div>
                    <div id="status-front" class="tel-status" style="background:#f1f5f9; color:#64748b; border: 1px solid #cbd5e1;">WAITING</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div>
                    <div class="kpi-card" style="padding: 10px; margin-bottom: 20px;">
                        <div class="kpi-title" style="padding: 10px; margin-bottom: 0;">📍 Live Position & Radiation Path</div>
                        <div id="homeMap" class="map-container"></div>
                    </div>
                    
                    <div class="slider-box">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #94a3b8; font-weight: 700;">
                            <span>Past</span><span id="home-slider-date" style="color: #3b82f6;">Syncing...</span><span>Now</span>
                        </div>
                        <input type="range" id="home-time-slider" min="0" max="<?= count($all_g7c) - 1 ?>" value="<?= count($all_g7c) - 1 ?>" style="width: 100%; margin-top: 10px;">
                    </div>
                </div>

                <div>
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="kpi-card" style="flex: 1;">
                            <div class="kpi-title">☢️ Radiation Level</div>
                            <div id="kpi-rad" class="kpi-value">-- <span style="font-size: 0.5em; color: #94a3b8;">mSv/h</span></div>
                        </div>
                        <div class="kpi-card" style="flex: 1;">
                            <div class="kpi-title">🏔️ Altitude</div>
                            <div id="kpi-alt" class="kpi-value">-- <span style="font-size: 0.5em; color: #94a3b8;">m</span></div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="kpi-card" style="flex: 1;">
                            <div class="kpi-title">💨 Gas (G7A)</div>
                            <div class="kpi-value" style="font-size: 1.4em;">
                                <?= $home_gas ? $home_gas['gas_value'] . ' PPM' : '--' ?>
                            </div>
                        </div>
                        <div class="kpi-card" style="flex: 1;">
                            <div class="kpi-title">🌡️ Climate (G7D)</div>
                            <div class="kpi-value" style="font-size: 1.4em;">
                                <?= $home_g7d ? $home_g7d['temperature'].'°C' : 'N/A' ?>
                            </div>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-title">📋 Mission Logbook</div>
                        <div class="log-container">
                            <?php foreach ($home_logs as $log): ?>
                                <?php $is_danger = preg_match('/danger|urgence|obstacle|alert|collision|mort/i', $log['subject_line']); ?>
                                <div class="log-item">
                                    <div style="font-weight: 600; color: <?= $is_danger ? '#ef4444' : '#3b82f6' ?>;">
                                        <?= htmlspecialchars($log['subject_line']) ?>
                                    </div>
                                    <div style="color: #94a3b8; font-size: 0.85em;"><?= date('H:i:s', strtotime($log['sent_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // --- LOGIQUE METIER JS (Live, Map, Slider, Bars) ---
                let isLiveMode = localStorage.getItem('rover_live_mode') !== 'false';
                const liveBtn = document.getElementById('live-toggle');
                const liveText = document.getElementById('live-text');

                function applyLiveUI() {
                    if(isLiveMode) {
                        liveBtn.className = "live-btn on"; liveText.innerText = "Live Sync";
                    } else {
                        liveBtn.className = "live-btn"; liveText.innerText = "History Mode";
                    }
                }

                function toggleLiveMode() {
                    isLiveMode = !isLiveMode;
                    localStorage.setItem('rover_live_mode', isLiveMode ? 'true' : 'false');
                    applyLiveUI();
                    if(isLiveMode) window.location.reload(); 
                }

                applyLiveUI();

                const rawDataHome = <?= json_encode($all_g7c) ?>;
                const rawDataBHome = <?= json_encode($all_g7b) ?>;
                const mapHome = L.map('homeMap').setView([48.8566, 2.3522], 16); 
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(mapHome);
                
                let currentHomeMarker = null;
                let prevPoint = null;
                
                [...rawDataHome].reverse().forEach(point => {
                    if(point.latitude && point.longitude) {
                        let currentLatLng = [parseFloat(point.latitude), parseFloat(point.longitude)];
                        let rad = parseFloat(point.radiation_usv) || 0.1;
                        let color = '#10b981';
                        if (rad >= 400) color = '#ef4444'; else if (rad > 50) color = '#f59e0b';
                        L.circle(currentLatLng, { color: color, fillColor: color, fillOpacity: 0.6, weight: 0, radius: 5 }).addTo(mapHome);
                        if (prevPoint) L.polyline([prevPoint, currentLatLng], { color: color, weight: 3, opacity: 0.5 }).addTo(mapHome);
                        prevPoint = currentLatLng;
                    }
                });

                function getClosestBDistance(targetDateStr) {
                    if (!rawDataBHome || rawDataBHome.length === 0) return '--';
                    const targetTime = new Date(targetDateStr.replace(' ', 'T')).getTime();
                    let closestVal = '--';
                    let minDiff = Infinity;
                    for (let i = 0; i < rawDataBHome.length; i++) {
                        const bTime = new Date(rawDataBHome[i].date_evenement.replace(' ', 'T')).getTime();
                        const diff = Math.abs(bTime - targetTime);
                        if (diff < minDiff) { minDiff = diff; closestVal = rawDataBHome[i].distance_cm; }
                    }
                    if (minDiff > 10000) return '--';
                    return closestVal;
                }

                function updateHomeDash(index) {
                    const selectedRecord = rawDataHome[(rawDataHome.length - 1) - index]; 
                    if (!selectedRecord) return;
                    
                    document.getElementById('home-slider-date').innerText = selectedRecord.date_enregistrement;
                    document.getElementById('kpi-alt').innerHTML = selectedRecord.altitude + ' <span style="font-size: 0.5em; color: #94a3b8;">m</span>';
                    
                    let rad = parseFloat(selectedRecord.radiation_usv) || 0;
                    let radColor = rad >= 400 ? '#ef4444' : (rad > 50 ? '#f59e0b' : '#10b981');
                    document.getElementById('kpi-rad').innerHTML = `<span style="color:${radColor}">${rad}</span> <span style="font-size: 0.5em; color: #94a3b8;">mSv/h</span>`;

                    // GESTION DES BARRES (Front/Rear)
                    let distAvant = parseFloat(selectedRecord.distance_cm);
                    let closestB = getClosestBDistance(selectedRecord.date_enregistrement);
                    let distArriere = parseFloat(closestB);

                    // Avant
                    let pctAvant = Math.min(100, (distAvant / 150) * 100); 
                    document.getElementById('val-front').innerText = distAvant + ' cm';
                    if (distAvant <= 10) {
                        document.getElementById('bar-front').style.cssText = `width: ${pctAvant}%; background: #ef4444;`;
                        document.getElementById('status-front').className = "tel-status status-danger";
                        document.getElementById('status-front').innerText = "🚨 DANGER";
                    } else {
                        document.getElementById('bar-front').style.cssText = `width: ${pctAvant}%; background: #10b981;`;
                        document.getElementById('status-front').className = "tel-status status-safe";
                        document.getElementById('status-front').innerText = "✅ CLEAR";
                    }

                    // Arrière
                    document.getElementById('val-rear').innerText = isNaN(distArriere) ? '--' : distArriere + ' cm';
                    if (isNaN(distArriere)) {
                        document.getElementById('bar-rear').style.cssText = `width: 0%;`;
                        document.getElementById('status-rear').className = "tel-status";
                        document.getElementById('status-rear').innerText = "OFFLINE";
                    } else {
                        let pctArriere = Math.min(100, (distArriere / 150) * 100);
                        if (distArriere <= 10) {
                            document.getElementById('bar-rear').style.cssText = `width: ${pctArriere}%; background: #ef4444;`;
                            document.getElementById('status-rear').className = "tel-status status-danger";
                            document.getElementById('status-rear').innerText = "🚨 DANGER";
                        } else {
                            document.getElementById('bar-rear').style.cssText = `width: ${pctArriere}%; background: #10b981;`;
                            document.getElementById('status-rear').className = "tel-status status-safe";
                            document.getElementById('status-rear').innerText = "✅ CLEAR";
                        }
                    }

                    // GESTION FLASH ALERT GLOBALE
                    let alertBanner = document.getElementById('flash-alert-banner');
                    let msgs = [];
                    if(distAvant <= 10) msgs.push(`COLLISION IMMINENTE (${distAvant} cm)`);
                    if(rad >= 400) msgs.push(`RADIATION MORTELLE (${rad} mSv/h)`);
                    if(msgs.length > 0) {
                        alertBanner.style.display = 'block';
                        alertBanner.innerText = "⚠️ ALERTE CRITIQUE : " + msgs.join(" | ");
                    } else {
                        alertBanner.style.display = 'none';
                    }

                    // CARTE
                    if (currentHomeMarker) { mapHome.removeLayer(currentHomeMarker); }
                    let customIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='font-size:24px;'>🤖</div>", iconSize: [30, 30], iconAnchor: [15, 15] });
                    currentHomeMarker = L.marker([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)], {icon: customIcon}).addTo(mapHome);
                    mapHome.panTo([parseFloat(selectedRecord.latitude), parseFloat(selectedRecord.longitude)]);
                }
                
                const hSlider = document.getElementById('home-time-slider');
                hSlider.addEventListener('input', function(e) { 
                    if(isLiveMode) { isLiveMode = false; localStorage.setItem('rover_live_mode', 'false'); applyLiveUI(); }
                    updateHomeDash(parseInt(e.target.value)); 
                });
                
                if (rawDataHome.length > 0) { updateHomeDash(rawDataHome.length - 1); }

                setInterval(() => {
                    if (isLiveMode) {
                        fetch('api_get_latest_measures.php').then(res => res.json()).then(data => {
                            if (data.g7c && rawDataHome.length > 0 && data.g7c.date_enregistrement !== rawDataHome[0].date_enregistrement) {
                                rawDataHome.unshift(data.g7c);
                                if(data.g7b) rawDataBHome.unshift(data.g7b);
                                hSlider.max = rawDataHome.length - 1; hSlider.value = rawDataHome.length - 1;
                                updateHomeDash(hSlider.value);
                            }
                        }).catch(e => console.log(e));
                    }
                }, 3000);
            </script>

        <?php elseif ($view_group === 'C'): ?>
            <div class="page-title" style="margin-bottom: 20px;">Primary Sensor Array (G7C)</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="kpi-card"><canvas id="tabChartHumidC" height="150"></canvas></div>
                <div class="kpi-card"><canvas id="tabChartRadC" height="150"></canvas></div>
            </div>
            <div class="kpi-card">
                <table>
                    <tr><th>Time</th><th>Front Dist.</th><th>Radiation</th><th>Alt</th><th>Hum</th><th>GPS</th></tr>
                    <?php foreach ($mesures as $m): ?>
                        <?php 
                        $d = floatval($m['distance_cm']); $r = floatval($m['radiation_usv']);
                        $s = $r >= 400 ? 'row-mortal' : ($d < 10 ? 'row-warning' : '');
                        ?>
                        <tr class="<?= $s ?>">
                            <td><?= $m['date_enregistrement'] ?></td>
                            <td><strong><?= $m['distance_cm'] ?> cm</strong></td>
                            <td><strong><?= $m['radiation_usv'] ?> mSv/h</strong></td>
                            <td><?= $m['altitude'] ?> m</td>
                            <td><?= $m['humidite_pourcent'] ?> %</td>
                            <td style="color:#94a3b8; font-size:0.9em;"><?= $m['latitude'] ?>, <?= $m['longitude'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <script>
                <?php
                $lbl_c = []; $dat_hum_c = []; $dat_rad_c = [];
                foreach (array_reverse($mesures) as $m) { $lbl_c[] = date('H:i', strtotime($m['date_enregistrement'])); $dat_hum_c[] = $m['humidite_pourcent']; $dat_rad_c[] = $m['radiation_usv'] ?? 0; }
                ?>
                new Chart(document.getElementById('tabChartHumidC'), { type: 'line', data: { labels: <?= json_encode($lbl_c) ?>, datasets: [{ label: 'Soil Humidity (%)', data: <?= json_encode($dat_hum_c) ?>, borderColor: '#3b82f6', fill: false }] } });
                new Chart(document.getElementById('tabChartRadC'), { type: 'line', data: { labels: <?= json_encode($lbl_c) ?>, datasets: [{ label: 'Radiation (mSv/h)', data: <?= json_encode($dat_rad_c) ?>, borderColor: '#ef4444', fill: false }] } });
            </script>
        
        <?php elseif ($view_group === 'A'): ?>
            <div class="page-title" style="margin-bottom: 20px;">Gas & Environment (G7A)</div>
            <div class="kpi-card" style="margin-bottom: 20px;"><canvas id="tabChartA" height="80"></canvas></div>
            <div class="kpi-card">
                <table>
                    <tr><th>Time</th><th>Gas Type</th><th>Value</th><th>Status</th></tr>
                    <?php foreach ($mesures as $m): ?>
                        <tr class="<?= $m['danger_level'] != '0' ? 'row-mortal' : '' ?>">
                            <td><?= $m['created_at'] ?></td><td><?= $m['gas_type'] ?></td><td><strong><?= $m['gas_value'] ?> ppm</strong></td>
                            <td><?= $m['danger_level'] != '0' ? '⚠️ DANGER' : '✅ SAFE' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <script>
                <?php $lbl_a = []; $dat_a = []; foreach (array_reverse($mesures) as $m) { $lbl_a[] = date('H:i', strtotime($m['created_at'])); $dat_a[] = $m['gas_value']; } ?>
                new Chart(document.getElementById('tabChartA'), { type: 'line', data: { labels: <?= json_encode($lbl_a) ?>, datasets: [{ label: 'Gas (ppm)', data: <?= json_encode($dat_a) ?>, borderColor: '#8b5cf6', fill: false }] } });
            </script>

        <?php elseif ($view_group === 'B'): ?>
            <div class="page-title" style="margin-bottom: 20px;">Kinetic Overview (G7B)</div>
            <div class="kpi-card" style="margin-bottom: 20px;"><canvas id="tabChartB" height="80"></canvas></div>
            <div class="kpi-card">
                <table>
                    <tr><th>Time</th><th>Raw Value</th><th>Distance</th><th>Status</th></tr>
                    <?php foreach ($mesures as $m): ?>
                        <tr class="<?= $m['statut'] === 'alerte collision' ? 'row-mortal' : '' ?>">
                            <td><?= $m['date_evenement'] ?></td><td><?= $m['valeur_brute'] ?></td><td><?= $m['distance_cm'] ?> cm</td>
                            <td><?= htmlspecialchars($m['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <script>
                <?php $lbl_b = []; $dat_b = []; foreach (array_reverse($mesures) as $m) { $lbl_b[] = date('H:i', strtotime($m['date_evenement'])); $dat_b[] = floatval(str_replace(['>','<'], '', $m['distance_cm'])); } ?>
                new Chart(document.getElementById('tabChartB'), { type: 'bar', data: { labels: <?= json_encode($lbl_b) ?>, datasets: [{ label: 'Rear Distance (cm)', data: <?= json_encode($dat_b) ?>, backgroundColor: '#f59e0b' }] } });
            </script>

        <?php elseif ($view_group === 'D'): ?>
            <div class="page-title" style="margin-bottom: 20px;">Atmosphere (G7D)</div>
            <?php if (count($mesures) === 0): ?>
                <div style="padding: 40px; text-align: center; color: #94a3b8;">No data received from G7D.</div>
            <?php else: ?>
                <div class="kpi-card" style="margin-bottom: 20px;"><canvas id="tabChartD" height="80"></canvas></div>
                <div class="kpi-card">
                    <table>
                        <tr><th>Time</th><th>Temperature</th><th>Humidity</th></tr>
                        <?php foreach ($mesures as $m): ?>
                            <tr><td><?= $m['timestamp'] ?></td><td style="color:#ef4444; font-weight:bold;"><?= $m['temperature'] ?> °C</td><td style="color:#3b82f6; font-weight:bold;"><?= $m['humidity'] ?> %</td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <script>
                    <?php $lbl_d = []; $dat_t = []; $dat_h = []; foreach (array_reverse($mesures) as $m) { $lbl_d[] = date('H:i', strtotime($m['timestamp'])); $dat_t[] = $m['temperature']; $dat_h[] = $m['humidity']; } ?>
                    new Chart(document.getElementById('tabChartD'), { type: 'line', data: { labels: <?= json_encode($lbl_d) ?>, datasets: [{ label: 'Temp (°C)', data: <?= json_encode($dat_t) ?>, borderColor: '#ef4444' }, { label: 'Hum (%)', data: <?= json_encode($dat_h) ?>, borderColor: '#3b82f6' }] } });
                </script>
            <?php endif; ?>

        <?php elseif ($view_group === 'E'): ?>
            <div class="page-title" style="margin-bottom: 20px;">Audio Feed (G7E)</div>
            <div class="kpi-card">
                <table>
                    <tr><th>Upload Time</th><th>Filename</th><th>Playback</th><th>Size</th></tr>
                    <?php foreach ($mesures as $m): ?>
                        <tr>
                            <td><?= date('d/m H:i', strtotime($m['uploadedAt'])) ?></td><td><strong><?= htmlspecialchars($m['filename']) ?></strong></td>
                            <td><audio controls preload="none" style="height: 35px;"><source src="http://178.33.122.21:9000/<?= $m['minioBucket'] ?>/<?= $m['minioPath'] ?>" type="audio/wav"></audio></td>
                            <td><?= $m['fileSize'] ? round($m['fileSize']/(1024*1024), 2) . " MB" : "0 MB" ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

    </main>
</div>