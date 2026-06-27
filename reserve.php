<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $date = trim($_POST['date'] ?? '');

    if ($room_id > 0 && $client_name !== '' && $date !== '') {
        $stmt = $conn->prepare("INSERT INTO reservations (room_id, client_name, reservation_date, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $room_id, $client_name, $date, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: index.php");
exit;
