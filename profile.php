<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: connection.php'); exit(); }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->execute([$new_user, $new_email, $_SESSION['user_id']]);
    $_SESSION['username'] = $new_user;
    $message = "Profil mis à jour avec succès !";
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

include 'header.php';
?>
<div class="main-area">
    <div class="page-title">Paramètres du Profil</div>
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>
    <div class="kpi-card">
        <form method="POST">
            <label>Nom d'utilisateur</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            <label>E-mail</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            <button type="submit" class="btn">Enregistrer les modifications</button>
        </form>
    </div>
</div>