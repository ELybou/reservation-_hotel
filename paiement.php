<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ids_string = $_GET['ids'] ?? ($_GET['id'] ?? '');
if (empty($ids_string)) {
    header("Location: index.php");
    exit;
}

$id_parts = explode(',', $ids_string);
$reservation_ids = [];
foreach ($id_parts as $part) {
    $val = (int)$part;
    if ($val > 0) $reservation_ids[] = $val;
}

if (empty($reservation_ids)) {
    header("Location: index.php");
    exit;
}

// Récupérer toutes les réservations
$in_clause = implode(',', array_fill(0, count($reservation_ids), '?'));
$sql = "
    SELECT r.*, ro.room_number, ro.type, ro.price_per_night 
    FROM reservations r 
    JOIN rooms ro ON r.room_id = ro.id 
    WHERE r.id IN ($in_clause) AND r.user_id = ? AND r.status = 'pending'
";

$stmt = $conn->prepare($sql);
$types = str_repeat('i', count($reservation_ids)) . 'i';
$params = array_merge($reservation_ids, [$_SESSION['user_id']]);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: mes_reservations.php");
    exit;
}

$reservations = [];
$total_price = 0;
while ($row = $result->fetch_assoc()) {
    $d1 = new DateTime($row['check_in']);
    $d2 = new DateTime($row['check_out']);
    $num_nights = $d2->diff($d1)->days;
    if ($num_nights < 1) $num_nights = 1;
    $row['num_nights'] = $num_nights;
    
    $total_price += $num_nights * $row['price_per_night'];
    $reservations[] = $row;
}
$stmt->close();

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulation de paiement
    $card_number = trim($_POST['card_number'] ?? '');
    $expiry = trim($_POST['expiry'] ?? '');
    $cvc = trim($_POST['cvc'] ?? '');

    if (strlen($card_number) < 16 || strlen($cvc) < 3 || $expiry === '') {
        $message = "Veuillez entrer des informations de carte valides (simulation).";
        $message_type = "error";
    } else {
        // Mettre à jour le statut
        $update = $conn->prepare("UPDATE reservations SET status = 'paid' WHERE id IN ($in_clause)");
        $update->bind_param(str_repeat('i', count($reservation_ids)), ...$reservation_ids);
        
        if ($update->execute()) {
            $_SESSION['reserve_message'] = "Paiement réussi ! Vos réservations sont confirmées.";
            $_SESSION['reserve_message_type'] = "success";
            header("Location: mes_reservations.php");
            exit;
        } else {
            $message = "Erreur lors du paiement.";
            $message_type = "error";
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement Sécurisé</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-box {
            max-width: 500px;
            margin: 40px auto;
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .order-summary {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .order-summary p {
            margin: 5px 0;
            color: #ece4d2;
        }
        .total-amount {
            font-size: 1.5rem;
            color: #d4af37;
            font-weight: bold;
            margin-top: 10px;
            text-align: right;
        }
        .payment-row {
            display: flex;
            gap: 15px;
        }
        .payment-row > div {
            flex: 1;
        }
    </style>
</head>
<body>

<header>
    <h1>Paiement</h1>
    <p>Finalisez votre réservation (Simulation)</p>
    <div>
        <a class="logout-link" href="index.php">Annuler</a>
    </div>
</header>

<section class="container">
    <div class="payment-box">
        <h2>Résumé de votre commande</h2>
        <div class="order-summary">
            <?php foreach ($reservations as $res): ?>
                <div style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 10px;">
                    <p><strong>Client :</strong> <?= htmlspecialchars($res['client_name']) ?></p>
                    <p><strong>Chambre :</strong> <?= htmlspecialchars($res['room_number']) ?> (<?= htmlspecialchars($res['type']) ?>)</p>
                    <p><strong>Dates :</strong> <?= date('d/m/Y', strtotime($res['check_in'])) ?> - <?= date('d/m/Y', strtotime($res['check_out'])) ?></p>
                    <p><strong>Durée :</strong> <?= $res['num_nights'] ?> nuit(s)</p>
                </div>
            <?php endforeach; ?>
            <div class="total-amount">Total à payer : <?= number_format($total_price, 2, ',', ' ') ?> €</div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Numéro de carte bancaire (Fictif)</label>
            <input type="text" name="card_number" placeholder="0000 0000 0000 0000" maxlength="16" required>

            <div class="payment-row">
                <div>
                    <label>Date d'expiration</label>
                    <input type="text" name="expiry" placeholder="MM/AA" maxlength="5" required>
                </div>
                <div>
                    <label>CVC</label>
                    <input type="text" name="cvc" placeholder="123" maxlength="3" required>
                </div>
            </div>

            <button type="submit">Payer <?= number_format($total_price, 2, ',', ' ') ?> €</button>
        </form>
    </div>
</section>

</body>
</html>
