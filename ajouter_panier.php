<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $room_id = (int)$_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $num_guests = (int)$_POST['num_guests'];
    $price_per_night = floatval($_POST['price_per_night']);
    $room_number = $_POST['room_number'];
    $type = $_POST['type'];
    
    $d1 = new DateTime($check_in);
    $d2 = new DateTime($check_out);
    $num_nights = $d2->diff($d1)->days;
    if ($num_nights < 1) $num_nights = 1;

    $total_price = $num_nights * $price_per_night;

    // Check if room overlaps in DB just to be safe
    $overlap = $conn->prepare("SELECT id FROM reservations WHERE room_id = ? AND status != 'cancelled' AND check_in < ? AND check_out > ?");
    $overlap->bind_param("iss", $room_id, $check_out, $check_in);
    $overlap->execute();
    if ($overlap->get_result()->num_rows > 0) {
        $_SESSION['reserve_message'] = "Désolé, la chambre $room_number n'est plus disponible pour ces dates.";
        $_SESSION['reserve_message_type'] = "error";
        header("Location: index.php");
        exit;
    }
    $overlap->close();

    // Generate a unique cart item ID
    $cart_item_id = uniqid();

    $_SESSION['cart'][$cart_item_id] = [
        'room_id' => $room_id,
        'room_number' => $room_number,
        'type' => $type,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'num_guests' => $num_guests,
        'num_nights' => $num_nights,
        'price_per_night' => $price_per_night,
        'total_price' => $total_price
    ];

    $_SESSION['reserve_message'] = "Chambre $room_number ajoutée au panier !";
    $_SESSION['reserve_message_type'] = "success";
}

header("Location: index.php");
exit;
