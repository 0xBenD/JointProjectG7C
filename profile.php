<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: connection.php'); exit(); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Mise à jour basique (pseudo + email)
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->execute([$username, $email, $_SESSION['user_id']]);
    $_SESSION['username'] = $username;
    
    // Mise à jour mot de passe si rempli
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

<div class="main-area" style="padding: 40px;">
    <div class="page-title" style="margin-bottom: 30px;">⚙️ Paramètres du Profil</div>
    
    <?php if($message) echo "<div class='alert-success' style='padding:15px; background:#dcfce7; border-radius:8px; margin-bottom:20px;'>$message</div>"; ?>

    <div class="kpi-card" style="max-width: 500px;">
        <form method="POST">
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px;">Nom d'utilisateur</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px;">E-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px;">Nouveau mot de passe (laisser vide pour conserver l'actuel)</label>
                <input type="password" name="new_password" placeholder="••••••••" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <button type="submit" style="background:#2563eb; color:white; border:none; padding:12px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Enregistrer les modifications</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>