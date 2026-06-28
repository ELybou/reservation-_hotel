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
    $payment_method = $_POST['payment_method'] ?? 'card';

    if ($payment_method === 'card') {
        $card_number = trim($_POST['card_number'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');
        $cvc = trim($_POST['cvc'] ?? '');

        if (strlen($card_number) < 16 || strlen($cvc) < 3 || $expiry === '') {
            $message = "Veuillez entrer des informations de carte valides (simulation).";
            $message_type = "error";
        } else {
            // Mettre à jour le statut en 'paid'
            $update = $conn->prepare("UPDATE reservations SET status = 'paid', payment_method = 'card', receipt_url = NULL WHERE id IN ($in_clause)");
            $update->bind_param(str_repeat('i', count($reservation_ids)), ...$reservation_ids);
            
            if ($update->execute()) {
                $_SESSION['reserve_message'] = "Paiement par carte réussi ! Vos réservations sont confirmées.";
                $_SESSION['reserve_message_type'] = "success";
                header("Location: mes_reservations.php");
                exit;
            } else {
                $message = "Erreur lors du traitement du paiement.";
                $message_type = "error";
            }
            $update->close();
        }
    } else {
        // Paiement mobile Bankily ou Sedad
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        
        if (strlen($mobile_number) < 8 || !is_numeric($mobile_number)) {
            $message = "Veuillez entrer un numéro de téléphone mobile valide (8 chiffres).";
            $message_type = "error";
        } elseif (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
            $message = "Veuillez importer le reçu de paiement.";
            $message_type = "error";
        } else {
            $file = $_FILES['receipt_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];

            if (!in_array($file_ext, $allowed_exts)) {
                $message = "Seuls les fichiers JPG, PNG et PDF sont autorisés pour le reçu.";
                $message_type = "error";
            } else {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Nom unique
                $new_filename = uniqid('receipt_', true) . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Mettre à jour avec le reçu et le statut 'pending'
                    $update = $conn->prepare("UPDATE reservations SET status = 'pending', payment_method = ?, receipt_url = ? WHERE id IN ($in_clause)");
                    
                    $types_str = "ss" . str_repeat('i', count($reservation_ids));
                    $bind_params = array_merge([$payment_method, $target_path], $reservation_ids);
                    $update->bind_param($types_str, ...$bind_params);
                    
                    if ($update->execute()) {
                        $_SESSION['reserve_message'] = "Reçu de paiement par " . ucfirst($payment_method) . " envoyé avec succès ! Un administrateur va valider votre réservation.";
                        $_SESSION['reserve_message_type'] = "success";
                        header("Location: mes_reservations.php");
                        exit;
                    } else {
                        $message = "Erreur lors de l'enregistrement du paiement.";
                        $message_type = "error";
                    }
                    $update->close();
                } else {
                    $message = "Une erreur est survenue lors du téléversement du reçu.";
                    $message_type = "error";
                }
            }
        }
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

        <form method="POST" enctype="multipart/form-data">
            <label>Mode de paiement</label>
            <div style="display: flex; gap: 10px; margin-top: 6px; margin-bottom: 20px; flex-wrap: wrap;">
                <label style="flex: 1; min-width: 120px; border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(255,255,255,0.08); margin-top: 0; font-size: 0.9rem;">
                    <input type="radio" name="payment_method" value="card" checked onclick="togglePaymentForm('card')" style="width: auto; margin-top: 0; cursor: pointer;">
                    💳 Carte
                </label>
                <label style="flex: 1; min-width: 120px; border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(255,255,255,0.08); margin-top: 0; font-size: 0.9rem;">
                    <input type="radio" name="payment_method" value="bankily" onclick="togglePaymentForm('bankily')" style="width: auto; margin-top: 0; cursor: pointer;">
                    📱 Bankily
                </label>
                <label style="flex: 1; min-width: 120px; border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(255,255,255,0.08); margin-top: 0; font-size: 0.9rem;">
                    <input type="radio" name="payment_method" value="sedad" onclick="togglePaymentForm('sedad')" style="width: auto; margin-top: 0; cursor: pointer;">
                    📱 Sedad
                </label>
            </div>

            <!-- Formulaire Carte Bancaire -->
            <div id="form-card">
                <label>Numéro de carte bancaire (Fictif)</label>
                <input type="text" name="card_number" id="card_number_input" placeholder="0000 0000 0000 0000" maxlength="16" required>

                <div class="payment-row">
                    <div>
                        <label>Date d'expiration</label>
                        <input type="text" name="expiry" id="expiry_input" placeholder="MM/AA" maxlength="5" required>
                    </div>
                    <div>
                        <label>CVC</label>
                        <input type="text" name="cvc" id="cvc_input" placeholder="123" maxlength="3" required>
                    </div>
                </div>
            </div>

            <!-- Formulaire Mobile Payment (Bankily / Sedad) -->
            <div id="form-mobile" style="display: none;">
                <div id="payment-instructions" style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px; margin-bottom: 15px; font-size: 0.9rem; border-left: 4px solid #d4af37; line-height: 1.5; color:#ece4d2;">
                    <!-- Instructions dynamiques -->
                </div>

                <label>Votre numéro de téléphone (Compte mobile)</label>
                <input type="text" name="mobile_number" id="mobile_number_input" placeholder="Ex: 44123456" maxlength="8">

                <label style="margin-top: 15px;">Importer le reçu de transfert (Image ou PDF)</label>
                <input type="file" name="receipt_file" id="receipt_file_input" accept="image/*,application/pdf" style="padding: 10px; background: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.3); border-radius: 12px; color: white; display:block; margin-top: 6px; width:100%;">
            </div>

            <button type="submit" style="margin-top:25px;">Confirmer le paiement (<?= number_format($total_price, 2, ',', ' ') ?> €)</button>
        </form>

        <script>
        function togglePaymentForm(method) {
            const formCard = document.getElementById('form-card');
            const formMobile = document.getElementById('form-mobile');
            const instructions = document.getElementById('payment-instructions');
            const cardInputs = [
                document.getElementById('card_number_input'), 
                document.getElementById('expiry_input'), 
                document.getElementById('cvc_input')
            ];
            const mobileInputs = [
                document.getElementById('mobile_number_input'), 
                document.getElementById('receipt_file_input')
            ];

            if (method === 'card') {
                formCard.style.display = 'block';
                formMobile.style.display = 'none';
                cardInputs.forEach(i => i.setAttribute('required', 'true'));
                mobileInputs.forEach(i => i.removeAttribute('required'));
            } else {
                formCard.style.display = 'none';
                formMobile.style.display = 'block';
                cardInputs.forEach(i => i.removeAttribute('required'));
                mobileInputs.forEach(i => i.setAttribute('required', 'true'));

                if (method === 'bankily') {
                    instructions.innerHTML = "<strong>Instructions Bankily :</strong><br>Veuillez effectuer le transfert de <strong><?= number_format($total_price, 2, ',', ' ') ?> €</strong> vers le numéro marchand Bankily <strong>BPM-HOTEL-552</strong> (ou au <strong>44 00 11 22</strong>). Prenez une capture d'écran du reçu de confirmation de transfert puis importez-la ci-dessous.";
                } else if (method === 'sedad') {
                    instructions.innerHTML = "<strong>Instructions Sedad :</strong><br>Veuillez effectuer le transfert de <strong><?= number_format($total_price, 2, ',', ' ') ?> €</strong> vers le compte marchand Sedad <strong>SEDAD-HOTEL-990</strong>. Prenez une capture d'écran du reçu de transfert puis importez-la ci-dessous.";
                }
            }
        }
        </script>
    </div>
</section>

</body>
</html>
