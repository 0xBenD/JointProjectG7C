<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: connection.php'); exit(); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->execute([$username, $email, $_SESSION['user_id']]);
    $_SESSION['username'] = $username;
    
    if (!empty($_POST['new_password'])) {
        $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
    }
    $message = "Profil mis à jour avec succès !";
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

include 'header.php';
?>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="bot-icon">🤖</div>
            <div>
                <div class="title">Rover Control</div>
                <div class="subtitle"><?= htmlspecialchars($_SESSION['username'] ?? 'Opérateur') ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Navigation</div>
                <a href="utilisateur.php?show=home" class="nav-item">🏠 Home Dashboard</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="profile.php" class="nav-item active">⚙️ Gérer mon profil</a>
            <a href="connection.php?logout=1" class="btn-logout">❌ Terminate Session</a>
        </div>
    </aside>

    <main class="main-area">
        <div class="page-title">Paramètres de Compte</div>
        
        <?php if($message): ?>
            <div class="alert-success"><?= $message ?></div>
        <?php endif; ?>

        <div class="kpi-card" style="max-width: 500px;">
            <form method="POST">
                <div class="form-section-group">
                    <label class="form-label-style">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-input-style" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="form-section-group">
                    <label class="form-label-style">Adresse E-mail</label>
                    <input type="email" name="email" class="form-input-style" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-section-group">
                    <label class="form-label-style">Changer le mot de passe (laisser vide pour inchangé)</label>
                    <input type="password" name="new_password" class="form-input-style" placeholder="••••••••">
                </div>
                <button type="submit" class="form-submit-button">Sauvegarder les modifications</button>
            </form>
        </div>
    </main>
</div>