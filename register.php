<?php
require_once "conn.php";

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $message = "Veuillez remplir tous les champs.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Ce compte existe déjà. Veuillez vous connecter.";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $full_name, $email, $hashed_password);

            if ($insert->execute()) {
                $message = "Inscription réussie. Vous pouvez maintenant vous connecter.";
                $message_type = "success";
            } else {
                $message = "Une erreur est survenue lors de l'inscription.";
                $message_type = "error";
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-box">
        <h2>Créer un compte</h2>
        <div class="auth-switch">
            <a href="register.php" class="active">Inscription</a>
            <a href="login.php">Connexion</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
                <?php if ($message_type === 'error' && strpos($message, 'existe déjà') !== false): ?>
                    <br><br>
                    <a href="login.php" class="logout-link" style="display:inline-block; margin-top:0;">Se connecter</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nom complet</label>
            <input type="text" name="full_name" placeholder="Votre nom complet" required>

            <label>Email</label>
            <input type="email" name="email" placeholder="Votre email" required>

            <label>Mot de passe</label>
            <input type="password" name="password" required>

            <label>Confirmer le mot de passe</label>
            <input type="password" name="confirm_password" required>

            <button type="submit">S'inscrire</button>
        </form>
    </div>
</body>
</html>
