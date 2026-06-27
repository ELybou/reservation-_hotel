<?php include "conn.php"; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Hotel Booking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Chambres disponibles</h1>

<?php
$result = $conn->query("SELECT * FROM rooms WHERE available=1");

while ($row = $result->fetch_assoc()) {
    echo "<div class='room'>
            <h3>Room ".$row['room_number']."</h3>
            <p>Type: ".$row['type']."</p>

            <form method='POST' action='reserve.php'>
                <input type='hidden' name='room_id' value='".$row['id']."'>
                <input type='text' name='client_name' placeholder='Votre nom' required>
                <input type='date' name='date' required>
                <button type='submit'>Réserver</button>
            </form>
          </div>";
}
?>

</body>
</html>