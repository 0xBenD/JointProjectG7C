<?php
require_once 'config.php';

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Redirige si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: utilisateur.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CAS : CONNEXION ---
    if (isset($_POST['action_login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT id, username, password, groupe FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_groupe'] = $user['groupe'];
            header('Location: utilisateur.php');
            exit();
        } else {
            $error = "Identifiants incorrects.";
        }
    }

    // --- CAS : INSCRIPTION ---
    elseif (isset($_POST['action_register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $groupe = $_POST['groupe']; // A, B, C, D, E ou F

        if (!empty($username) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, groupe) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $groupe]);

                // Connexion automatique après inscription
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['user_groupe'] = $groupe;

                header('Location: utilisateur.php');
                exit();
            } catch (\PDOException $e) {
                $error = ($e->getCode() == 23000) ? "Cet e-mail est déjà utilisé." : "Erreur technique.";
            }
        } else {
            $error = "Veuillez remplir correctement tous les champs.";
        }
    }
}

include 'header.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="display: flex; gap: 50px; justify-content: space-around; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <h3>Déjà inscrit ? Connexion</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="action_login" class="btn">Se connecter</button>
        </form>
    </div>

    <div style="flex: 1; min-width: 300px; border-left: 1px solid #ccc; padding-left: 30px;">
        <h3>Créer un compte commun</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Sélectionnez votre Groupe de Projet</label>
                <select name="groupe" required>
                    <option value="A">Groupe G7A (Mesures de Gaz)</option>
                    <option value="B">Groupe G7B (Capteur de recul)</option>
                    <option value="C" selected>Groupe G7C (Ultrason & GPS - Votre Projet)</option>
                    <option value="D">Groupe G7D</option>
                    <option value="E">Groupe G7E</option>
                    <option value="F">Groupe G7F</option>
                </select>
            </div>
            <button type="submit" name="action_register" class="btn"
                style="background: var(--success);">S'inscrire</button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>