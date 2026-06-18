<?php
require_once 'config.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pass, $reset['user_id']]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
        echo "Mot de passe mis à jour !";
    }
}
?>
<form method="POST">
    <input type="password" name="password" placeholder="Nouveau mot de passe" required>
    <button type="submit">Changer le mot de passe</button>
</form>