<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $check_in = trim($_POST['check_in'] ?? '');
    $check_out = trim($_POST['check_out'] ?? '');
    $num_guests = (int)($_POST['num_guests'] ?? 1);

    if ($room_id > 0 && $client_name !== '' && $check_in !== '' && $check_out !== '') {
        // Vérifier que check-in n'est pas dans le passé
        if ($check_in < date('Y-m-d')) {
            $_SESSION['reserve_message'] = "La date d'arrivée ne peut pas être dans le passé.";
            $_SESSION['reserve_message_type'] = "error";
            header("Location: index.php");
            exit;
        }

        // Vérifier que check-out > check-in
        if ($check_out <= $check_in) {
            $_SESSION['reserve_message'] = "La date de départ doit être après la date d'arrivée.";
            $_SESSION['reserve_message_type'] = "error";
            header("Location: index.php");
            exit;
        }

        // Vérifier que la chambre existe et n'est pas bloquée manuellement par l'admin
        $check = $conn->prepare("SELECT available, capacity, price_per_night FROM rooms WHERE id = ?");
        $check->bind_param("i", $room_id);
        $check->execute();
        $check_result = $check->get_result();
        $room = $check_result->fetch_assoc();
        $check->close();

        if (!$room || !$room['available']) {
            $_SESSION['reserve_message'] = "Cette chambre n'est pas disponible pour le moment.";
            $_SESSION['reserve_message_type'] = "error";
            header("Location: index.php");
            exit;
        }

        // Vérifier les chevauchements de dates pour éviter la double réservation
        $overlap = $conn->prepare("SELECT id FROM reservations WHERE room_id = ? AND check_in < ? AND check_out > ?");
        $overlap->bind_param("iss", $room_id, $check_out, $check_in);
        $overlap->execute();
        if ($overlap->get_result()->num_rows > 0) {
            $_SESSION['reserve_message'] = "Désolé, cette chambre vient d'être réservée pour ces dates.";
            $_SESSION['reserve_message_type'] = "error";
            header("Location: index.php");
            exit;
        }
        $overlap->close();

        // Vérifier la capacité
        if ($num_guests < 1) $num_guests = 1;
        if ($num_guests > $room['capacity']) {
            $_SESSION['reserve_message'] = "Le nombre de personnes dépasse la capacité de la chambre (" . $room['capacity'] . " max).";
            $_SESSION['reserve_message_type'] = "error";
            header("Location: index.php");
            exit;
        }

        // Calculer le nombre de nuits et le prix total
        $date1 = new DateTime($check_in);
        $date2 = new DateTime($check_out);
        $num_nights = $date2->diff($date1)->days;
        $total_price = $num_nights * $room['price_per_night'];

        // Insérer la réservation en statut 'pending'
        $stmt = $conn->prepare("INSERT INTO reservations (room_id, client_name, check_in, check_out, num_guests, status, reservation_date, user_id) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
        $reservation_date = date('Y-m-d');
        $stmt->bind_param("isssisd", $room_id, $client_name, $check_in, $check_out, $num_guests, $reservation_date, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $new_res_id = $conn->insert_id;
            header("Location: paiement.php?id=" . $new_res_id);
            exit;
        } else {
            $_SESSION['reserve_message'] = "Une erreur est survenue lors de la création de la réservation.";
            $_SESSION['reserve_message_type'] = "error";
        }

        $stmt->close();
    } else {
        $_SESSION['reserve_message'] = "Veuillez remplir tous les champs.";
        $_SESSION['reserve_message_type'] = "error";
    }
}

header("Location: index.php");
exit;
