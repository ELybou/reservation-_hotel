<?php
require_once "conn.php";

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $message = "Veuillez saisir votre email et votre mot de passe.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                header("Location: index.php");
                exit;
            } else {
                $message = "Mot de passe incorrect.";
                $message_type = "error";
            }
        } else {
            $message = "Aucun compte trouvé avec cet email.";
            $message_type = "error";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-box">
        <h2>Connexion</h2>
        <div class="auth-switch">
            <a href="register.php">Inscription</a>
            <a href="login.php" class="active">Connexion</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" placeholder="Votre email" required>

            <label>Mot de passe</label>
            <input type="password" name="password" required>

            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>
