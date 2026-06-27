<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$cart_count = count($cart);
$total_cart_price = 0;

foreach ($cart as $item) {
    $total_cart_price += $item['total_price'];
}

$message = '';
$message_type = '';

// Retirer un élément du panier
if (isset($_GET['remove']) && isset($cart[$_GET['remove']])) {
    unset($_SESSION['cart'][$_GET['remove']]);
    header("Location: panier.php");
    exit;
}

// Valider la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (empty($cart)) {
        $message = "Votre panier est vide.";
        $message_type = "error";
    } else {
        $client_name = trim($_POST['client_name'] ?? '');
        if ($client_name === '') {
            $message = "Veuillez entrer le nom du client principal.";
            $message_type = "error";
        } else {
            // Créer toutes les réservations
            $reservation_ids = [];
            $conn->begin_transaction();
            
            try {
                $insert = $conn->prepare("INSERT INTO reservations (room_id, user_id, client_name, check_in, check_out, num_guests, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                
                foreach ($cart as $item) {
                    $insert->bind_param("iisssi", 
                        $item['room_id'], 
                        $_SESSION['user_id'], 
                        $client_name, 
                        $item['check_in'], 
                        $item['check_out'], 
                        $item['num_guests']
                    );
                    $insert->execute();
                    $reservation_ids[] = $conn->insert_id;
                }
                
                $conn->commit();
                
                // Vider le panier
                unset($_SESSION['cart']);
                
                // Rediriger vers le paiement
                // On passe les IDs séparés par des virgules
                $ids_string = implode(',', $reservation_ids);
                header("Location: paiement.php?ids=" . urlencode($ids_string));
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Une erreur est survenue lors de la création des réservations.";
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Votre Panier</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .cart-item h3 { margin: 0 0 5px 0; color: #d4af37; }
        .cart-item p { margin: 0; font-size: 0.9rem; }
        .cart-total {
            text-align: right;
            font-size: 1.5rem;
            font-weight: bold;
            color: #d4af37;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.2);
        }
        .checkout-form {
            background: rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>

<header>
    <h1>🛒 Votre Panier</h1>
    <div>
        <a class="logout-link" href="index.php">Continuer la recherche</a>
        <a class="logout-link" href="mes_reservations.php">Mes réservations</a>
    </div>
</header>

<section class="container" style="max-width: 800px;">

    <?php if ($message !== ''): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($cart_count > 0): ?>
        
        <h2>Chambres sélectionnées (<?= $cart_count ?>)</h2>
        
        <?php foreach ($cart as $id => $item): ?>
            <div class="cart-item">
                <div>
                    <h3>Chambre <?= htmlspecialchars($item['room_number']) ?> (<?= htmlspecialchars($item['type']) ?>)</h3>
                    <p>Du <?= date('d/m/Y', strtotime($item['check_in'])) ?> au <?= date('d/m/Y', strtotime($item['check_out'])) ?> (<?= $item['num_nights'] ?> nuit<?= $item['num_nights'] > 1 ? 's' : '' ?>)</p>
                    <p><?= $item['num_guests'] ?> personne<?= $item['num_guests'] > 1 ? 's' : '' ?></p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 10px;">
                        <?= number_format($item['total_price'], 2, ',', ' ') ?> €
                    </div>
                    <a href="panier.php?remove=<?= urlencode($id) ?>" style="color: #ef4444; text-decoration: none; font-size: 0.9rem;">❌ Retirer</a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total">
            Total : <?= number_format($total_cart_price, 2, ',', ' ') ?> €
        </div>

        <div class="checkout-form">
            <h2>Finaliser la commande</h2>
            <form method="POST">
                <input type="hidden" name="action" value="checkout">
                <label>Nom du client principal (qui occupera les chambres)</label>
                <input type="text" name="client_name" placeholder="Ex: Jean Dupont" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669); font-size:1.1rem; padding: 15px;">Procéder au paiement</button>
            </form>
        </div>

    <?php else: ?>
        <p class="empty">Votre panier est vide.</p>
        <div style="text-align:center; margin-top:30px;">
            <a href="index.php" style="color:#d4af37; text-decoration:none; font-size:1.2rem;">← Retourner à la recherche</a>
        </div>
    <?php endif; ?>

</section>

</body>
</html>
