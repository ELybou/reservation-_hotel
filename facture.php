<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$reservation_id = (int)($_GET['id'] ?? 0);
if ($reservation_id <= 0) {
    header("Location: index.php");
    exit;
}

// Récupérer la réservation (doit être payée)
$stmt = $conn->prepare("
    SELECT r.*, ro.room_number, ro.type, ro.price_per_night, u.full_name, u.email
    FROM reservations r 
    JOIN rooms ro ON r.room_id = ro.id 
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.user_id = ? AND r.status = 'paid'
");
$stmt->bind_param("ii", $reservation_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: mes_reservations.php");
    exit;
}

$res = $result->fetch_assoc();
$stmt->close();

$d1 = new DateTime($res['check_in']);
$d2 = new DateTime($res['check_out']);
$num_nights = $d2->diff($d1)->days;
$total_price = $num_nights * $res['price_per_night'];

$invoice_number = "FAC-" . date('Y', strtotime($res['created_at'])) . "-" . str_pad($res['id'], 5, "0", STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?= $invoice_number ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            background: #f7f7f7;
            margin: 0;
            padding: 40px;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 40px;
            border: 1px solid #ddd;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            font-size: 15px;
            line-height: 24px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .invoice-header h1 {
            margin: 0;
            color: #d4af37;
            font-size: 2.2rem;
            text-transform: uppercase;
        }
        .company-info {
            text-align: right;
            color: #555;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .client-info h3 {
            margin-top: 0;
            color: #222;
        }
        .meta-info table {
            text-align: left;
        }
        .meta-info th {
            padding-right: 15px;
            color: #666;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .invoice-table th {
            background: #eee;
            border-bottom: 2px solid #ccc;
            padding: 12px;
            text-align: left;
        }
        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .invoice-table th.right, .invoice-table td.right {
            text-align: right;
        }
        .total-row {
            font-size: 1.2rem;
            font-weight: bold;
            color: #d4af37;
        }
        .footer {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .print-btn {
            display: block;
            width: 200px;
            margin: 30px auto;
            padding: 12px;
            background: #333;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
        .print-btn:hover {
            background: #555;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { box-shadow: none; border: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨️ Imprimer / Sauvegarder en PDF</button>

<div class="invoice-box">
    <div class="invoice-header">
        <div>
            <h1>FACTURE</h1>
            <p><strong>Hotel Booking System</strong><br>
            123 Avenue des Champs-Élysées<br>
            75008 Paris, France<br>
            contact@hotel.com</p>
        </div>
        <div class="company-info">
            <h2 style="margin-top:0; color:#333;">Facture N° <?= $invoice_number ?></h2>
            <p><strong>Date d'émission :</strong> <?= date('d/m/Y') ?></p>
            <p><strong>Date de réservation :</strong> <?= date('d/m/Y', strtotime($res['created_at'])) ?></p>
        </div>
    </div>

    <div class="invoice-details">
        <div class="client-info">
            <h3>Facturé à :</h3>
            <p>
                <strong><?= htmlspecialchars($res['full_name']) ?></strong><br>
                <?= htmlspecialchars($res['email']) ?><br>
                Nom sur la réservation : <?= htmlspecialchars($res['client_name']) ?>
            </p>
        </div>
        <div class="meta-info">
            <table>
                <tr><th>Statut</th><td style="color:#10b981; font-weight:bold;">PAYÉE ✅</td></tr>
                <tr><th>Arrivée</th><td><?= date('d/m/Y', strtotime($res['check_in'])) ?></td></tr>
                <tr><th>Départ</th><td><?= date('d/m/Y', strtotime($res['check_out'])) ?></td></tr>
                <tr><th>Personnes</th><td><?= (int)$res['num_guests'] ?></td></tr>
            </table>
        </div>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Prix Unitaire</th>
                <th class="right">Quantité (Nuits)</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Séjour en Chambre <?= htmlspecialchars($res['room_number']) ?> (<?= htmlspecialchars($res['type']) ?>)</td>
                <td class="right"><?= number_format($res['price_per_night'], 2, ',', ' ') ?> €</td>
                <td class="right"><?= $num_nights ?></td>
                <td class="right"><?= number_format($total_price, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td colspan="3" class="right" style="border-bottom:none; padding-top:20px;">Sous-total</td>
                <td class="right" style="border-bottom:none; padding-top:20px;"><?= number_format($total_price, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td colspan="3" class="right" style="border-bottom:none;">TVA (20%) incluse</td>
                <td class="right" style="border-bottom:none;"><?= number_format($total_price - ($total_price / 1.2), 2, ',', ' ') ?> €</td>
            </tr>
            <tr class="total-row">
                <td colspan="3" class="right">TOTAL PAYÉ</td>
                <td class="right"><?= number_format($total_price, 2, ',', ' ') ?> €</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Merci pour votre confiance et à très bientôt dans notre hôtel !</p>
        <p>Hotel Booking System — N° de SIRET : 123 456 789 00012</p>
    </div>
</div>

</body>
</html>
