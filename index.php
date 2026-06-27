<?php include "conn.php"; ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ely Booking System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Système de réservation d’hôtel</h1>
    <p>Réservez votre chambre facilement et rapidement</p>
</header>

<section class="container">

<h2>Chambres disponibles</h2>

<?php
$sql = "SELECT * FROM rooms WHERE available = 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
?>

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
            <input type="hidden" name="room_id" value="<?= $row['id'] ?>">

            <label>Nom du client</label>
            <input type="text" name="client_name" placeholder="Entrez votre nom" required>

            <label>Date de réservation</label>
            <input type="date" name="date" required>

            <button type="submit">Réserver</button>
        </form>

    </div>

<?php
    }
} else {
    echo "<p class='empty'>Aucune chambre disponible</p>";
}
?>

</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

</body>
</html>