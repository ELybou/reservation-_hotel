<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "";

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Récupérer les infos de l'utilisateur
$stmt = $conn->prepare("SELECT full_name, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

// Traitement de la modification du nom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_name') {
    $new_name = trim($_POST['full_name'] ?? '');

    if ($new_name === '') {
        $message = "Le nom ne peut pas être vide.";
        $message_type = "error";
    } else {
        $update = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $update->bind_param("si", $new_name, $_SESSION['user_id']);

        if ($update->execute()) {
            $_SESSION['user_name'] = $new_name;
            $user['full_name'] = $new_name;
            $message = "Nom mis à jour avec succès.";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la mise à jour.";
            $message_type = "error";
        }

        $update->close();
    }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_new_password === '') {
        $message = "Veuillez remplir tous les champs de mot de passe.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_new_password) {
        $message = "Les nouveaux mots de passe ne correspondent pas.";
        $message_type = "error";
    } elseif (strlen($new_password) < 4) {
        $message = "Le nouveau mot de passe doit contenir au moins 4 caractères.";
        $message_type = "error";
    } else {
        // Vérifier l'ancien mot de passe
        $check = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $check->bind_param("i", $_SESSION['user_id']);
        $check->execute();
        $check_result = $check->get_result();
        $user_pass = $check_result->fetch_assoc();
        $check->close();

        if (!password_verify($current_password, $user_pass['password'])) {
            $message = "Le mot de passe actuel est incorrect.";
            $message_type = "error";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed, $_SESSION['user_id']);

            if ($update->execute()) {
                $message = "Mot de passe changé avec succès.";
                $message_type = "success";
            } else {
                $message = "Erreur lors du changement de mot de passe.";
                $message_type = "error";
            }

            $update->close();
        }
    }
}

// Compter les réservations de l'utilisateur
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ?");
$count_stmt->bind_param("i", $_SESSION['user_id']);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$reservation_count = $count_result->fetch_assoc()['total'];
$count_stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon profil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Mon profil</h1>
    <p>Gérez vos informations personnelles</p>
    <div>
        <?php if ($is_admin): ?>
            <a class="logout-link" href="admin.php">Admin</a>
        <?php endif; ?>
        <a class="logout-link" href="index.php">Accueil</a>
        <a class="logout-link" href="mes_reservations.php">Mes réservations</a>
        <a class="logout-link" href="logout.php">Déconnexion</a>
    </div>
</header>

<section class="container">

    <?php if ($message !== ''): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Informations du profil -->
    <div class="room">
        <div class="room-header">
            <h3>Informations personnelles</h3>
        </div>
        <div class="room-body profile-info">
            <p><strong>Nom complet :</strong> <?= htmlspecialchars($user['full_name']) ?></p>
            <p><strong>Email :</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Membre depuis :</strong> <?= htmlspecialchars($user['created_at']) ?></p>
            <p><strong>Réservations :</strong> <?= $reservation_count ?></p>
        </div>
    </div>

    <!-- Modifier le nom -->
    <div class="room">
        <div class="room-header">
            <h3>Modifier le nom</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_name">

            <label>Nouveau nom complet</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

            <button type="submit">Mettre à jour</button>
        </form>
    </div>

    <!-- Changer le mot de passe -->
    <div class="room">
        <div class="room-header">
            <h3>Changer le mot de passe</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">

            <label>Mot de passe actuel</label>
            <input type="password" name="current_password" required>

            <label>Nouveau mot de passe</label>
            <input type="password" name="new_password" required>

            <label>Confirmer le nouveau mot de passe</label>
            <input type="password" name="confirm_new_password" required>

            <button type="submit">Changer le mot de passe</button>
        </form>
    </div>

</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

</body>
</html>
