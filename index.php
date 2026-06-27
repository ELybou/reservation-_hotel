<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

$sql = "SELECT * FROM rooms WHERE available = 1 ORDER BY room_number ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réservation d'hôtel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Système de réservation d’hôtel</h1>
    <p>Bonjour <?= htmlspecialchars($_SESSION['user_name']) ?>, réservez votre chambre facilement</p>
    <a class="logout-link" href="logout.php">Déconnexion</a>
</header>

<section class="container">
    <h2>Chambres disponibles</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="room">
                <div class="room-header">
                    <h3>Chambre <?= htmlspecialchars($row['room_number']) ?></h3>
                    <span class="badge">Disponible</span>
                </div>

                <div class="room-body">
                    <p><strong>Type :</strong> <?= htmlspecialchars($row['type']) ?></p>
                    <p><strong>Description :</strong> Chambre confortable et propre</p>
                    <p><strong>Services :</strong> WiFi, TV, Climatisation</p>
                </div>

                <form method="POST" action="reserve.php">
                    <input type="hidden" name="room_id" value="<?= (int)$row['id'] ?>">

                    <label>Nom du client</label>
                    <input type="text" name="client_name" placeholder="Entrez votre nom" required>

                    <label>Date de réservation</label>
                    <input type="date" name="date" required>

                    <button type="submit">Réserver</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="empty">Aucune chambre disponible pour le moment</p>
    <?php endif; ?>
</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

</body>
</html>