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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Sécurisé</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --gold-color: #d4af37;
            --cream-color: #ece4d2;
            --white-trans: rgba(255, 255, 255, 0.08);
            --white-trans-hover: rgba(255, 255, 255, 0.15);
            --border-trans: rgba(255, 255, 255, 0.15);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        header {
            text-align: center;
            padding: 40px 20px 20px;
        }

        header h1 {
            font-size: 2.2rem;
            margin-bottom: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        header p {
            color: var(--cream-color);
            opacity: 0.8;
            margin-top: 0;
        }

        .logout-link {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            color: var(--cream-color);
            font-size: 0.9rem;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .logout-link:hover {
            opacity: 1;
        }

        .payment-box {
            max-width: 550px;
            margin: 20px auto 60px;
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 24px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-trans);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .payment-box h2 {
            font-size: 1.4rem;
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: 500;
            border-bottom: 1px solid var(--border-trans);
            padding-bottom: 12px;
        }

        .order-summary {
            background: rgba(0, 0, 0, 0.25);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        .reservation-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .reservation-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .order-summary p {
            margin: 8px 0;
            color: var(--cream-color);
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
        }

        .order-summary p strong {
            color: white;
            font-weight: 500;
        }

        .total-amount {
            font-size: 1.6rem;
            color: var(--gold-color);
            font-weight: 600;
            margin-top: 20px;
            text-align: right;
            border-top: 1px dashed var(--border-trans);
            padding-top: 15px;
        }

        .method-label {
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 10px;
            display: block;
        }

        .payment-methods-grid {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .method-card {
            flex: 1;
            min-width: 130px;
            border: 1px solid var(--border-trans);
            padding: 16px 12px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            background: var(--white-trans);
            font-size: 0.9rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }

        .method-card:hover {
            background: var(--white-trans-hover);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .method-card.active {
            background: rgba(212, 175, 55, 0.15);
            border-color: var(--gold-color);
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.2);
        }

        .method-card input[type="radio"] {
            display: none;
        }

        /* Style spécifique pour vos images de paiement */
        .method-img {
            width: auto;
            height: 35px; /* Hauteur fixe idéale pour les logos */
            object-fit: contain;
            filter: grayscale(20%);
            transition: filter 0.3s ease;
        }

        .method-card.active .method-img {
            filter: grayscale(0%);
        }

        /* Formulaires inputs */
        label:not(.method-card):not(.method-label) {
            display: block;
            font-size: 0.85rem;
            color: var(--cream-color);
            margin-bottom: 8px;
            opacity: 0.9;
        }

        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 14px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-trans);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            box-sizing: border-box;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--gold-color);
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 8px rgba(212, 175, 55, 0.1);
        }

        .payment-row {
            display: flex;
            gap: 16px;
        }

        .payment-row > div {
            flex: 1;
        }

        #payment-instructions {
            background: rgba(0, 0, 0, 0.3);
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            border-left: 4px solid var(--gold-color);
            line-height: 1.6;
            color: var(--cream-color);
        }

        input[type="file"] {
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px dashed var(--border-trans);
            cursor: pointer;
        }
        
        input[type="file"]:hover {
            border-color: rgba(255,255,255,0.4);
        }

        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: var(--gold-color);
            color: #000;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
            filter: brightness(1.1);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .alert.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        .alert.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
    </style>
</head>
<body>

<header>
    <h1>Paiement</h1>
    <p>Finalisez votre réservation (Simulation)</p>
    <div>
        <a class="logout-link" href="index.php">✕ Annuler et retourner à l'accueil</a>
    </div>
</header>

<section class="container">
    <div class="payment-box">
        <h2>Résumé de votre commande</h2>
        <div class="order-summary">
            <?php foreach ($reservations as $res): ?>
                <div class="reservation-item">
                    <p><strong>Client :</strong> <span><?= htmlspecialchars($res['client_name']) ?></span></p>
                    <p><strong>Chambre :</strong> <span><?= htmlspecialchars($res['room_number']) ?> (<?= htmlspecialchars($res['type']) ?>)</span></p>
                    <p><strong>Dates :</strong> <span><?= date('d/m/Y', strtotime($res['check_in'])) ?> - <?= date('d/m/Y', strtotime($res['check_out'])) ?></span></p>
                    <p><strong>Durée :</strong> <span><?= $res['num_nights'] ?> nuit(s)</span></p>
                </div>
            <?php endforeach; ?>
            <div class="total-amount">Total à payer : <?= number_format($total_price, 2, ',', ' ') ?> €</div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <span class="method-label">Mode de paiement</span>
            <div class="payment-methods-grid">
                
                <label class="method-card active" id="label-card">
                    <input type="radio" name="payment_method" value="card" checked onclick="togglePaymentForm('card')">
                    <img src="assets/carte.png" alt="Carte" class="method-img">
                    <span>Carte</span>
                </label>
                
                <label class="method-card" id="label-bankily">
                    <input type="radio" name="payment_method" value="bankily" onclick="togglePaymentForm('bankily')">
                    <img src="assets/bankily.png" alt="Bankily" class="method-img">
                    <span>Bankily</span>
                </label>
                
                <label class="method-card" id="label-sedad">
                    <input type="radio" name="payment_method" value="sedad" onclick="togglePaymentForm('sedad')">
                    <img src="assets/sedad.jpeg" alt="Sedad" class="method-img">
                    <span>Sedad</span>
                </label>
                
            </div>

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

            <div id="form-mobile" style="display: none;">
                <div id="payment-instructions">
                    </div>

                <label>Votre numéro de téléphone (Compte mobile)</label>
                <input type="text" name="mobile_number" id="mobile_number_input" placeholder="Ex: 44123456" maxlength="8">

                <label style="margin-top: 5px;">Importer le reçu de transfert (Image ou PDF)</label>
                <input type="file" name="receipt_file" id="receipt_file_input" accept="image/*,application/pdf">
            </div>

            <button type="submit">Confirmer le paiement (<?= number_format($total_price, 2, ',', ' ') ?> €)</button>
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

            // Gestion de l'état actif visuel
            document.querySelectorAll('.method-card').forEach(card => card.classList.remove('active'));
            document.getElementById('label-' + method).classList.add('active');

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
                    instructions.innerHTML = "<strong>Instructions Bankily :</strong><br>Veuillez effectuer le transfert de <strong><?= number_format($total_price, 2, ',', ' ') ?> MRU</strong> vers le compte marchand Bankily <strong>007895</strong> (ou au <strong>44 00 11 22</strong>). Prenez une capture d'écran du reçu de confirmation de transfert puis importez-la ci-dessous.";
                } else if (method === 'sedad') {
                    instructions.innerHTML = "<strong>Instructions Sedad :</strong><br>Veuillez effectuer le transfert de <strong><?= number_format($total_price, 2, ',', ' ') ?> MRU</strong> vers le compte marchand Sedad <strong>008975</strong>. Prenez une capture d'écran du reçu de transfert puis importez-la ci-dessous.";
                }
            }
        }
        </script>
    </div>
</section>

</body>
</html>