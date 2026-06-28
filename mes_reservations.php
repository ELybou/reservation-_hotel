<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

$message = "";
$message_type = "";

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Traitement de l'annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $reservation_id = (int)$_POST['cancel_reservation_id'];

    // Vérifier que la réservation appartient bien à l'utilisateur
    $check = $conn->prepare("SELECT id FROM reservations WHERE id = ? AND user_id = ? AND status != 'cancelled'");
    $check->bind_param("ii", $reservation_id, $_SESSION['user_id']);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 1) {
        $update = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
        $update->bind_param("i", $reservation_id);

        if ($update->execute()) {
            $message = "Réservation annulée avec succès.";
            $message_type = "success";
        } else {
            $message = "Erreur lors de l'annulation.";
            $message_type = "error";
        }
        $update->close();
    } else {
        $message = "Réservation introuvable ou déjà annulée.";
        $message_type = "error";
    }
    $check->close();
}

// Traitement de l'avis (Review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $reservation_id = (int)$_POST['reservation_id'];
    $room_id = (int)$_POST['room_id'];
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5) {
        $insert = $conn->prepare("INSERT INTO reviews (reservation_id, room_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiiis", $reservation_id, $room_id, $_SESSION['user_id'], $rating, $comment);
        if ($insert->execute()) {
            $message = "Merci pour votre avis !";
            $message_type = "success";
        } else {
            $message = "Erreur lors de l'envoi de l'avis.";
            $message_type = "error";
        }
        $insert->close();
    }
}

// Récupérer les réservations de l'utilisateur avec statuts et avis existants
$sql = $conn->prepare("
    SELECT r.id, r.client_name, r.check_in, r.check_out, r.num_guests, r.status, r.reservation_date, r.created_at, r.payment_method, r.receipt_url, 
           ro.id AS room_id, ro.room_number, ro.type, ro.price_per_night,
           rev.id AS review_id
    FROM reservations r 
    JOIN rooms ro ON r.room_id = ro.id 
    LEFT JOIN reviews rev ON rev.reservation_id = r.id
    WHERE r.user_id = ? 
    ORDER BY r.check_in DESC
");
$sql->bind_param("i", $_SESSION['user_id']);
$sql->execute();
$result = $sql->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes réservations</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .actions-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .actions-group button, .actions-group a.btn {
            margin-top: 0;
            flex: 1;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 13px;
            border: none;
            background: linear-gradient(135deg, #d4af37, #8c6b22);
            color: white;
            font-size: 15px;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(212, 175, 55, 0.28);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #d4af37;
        }
        .review-form {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 12px;
            margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>

<header>
    <h1>Mes réservations</h1>
    <p>Bonjour <?= htmlspecialchars($_SESSION['user_name']) ?>, voici vos réservations</p>
    <div>
        <?php if ($is_admin): ?>
            <a class="logout-link" href="admin.php">Admin</a>
        <?php endif; ?>
        <a class="logout-link" href="index.php">Accueil</a>
        <a class="logout-link" href="profil.php">Mon profil</a>
        <a class="logout-link" href="logout.php">Déconnexion</a>
    </div>
</header>

<section class="container">
    <h2>Historique des réservations</h2>

    <?php if ($message !== ''): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            $check_in = $row['check_in'] ?? $row['reservation_date'];
            $check_out = $row['check_out'] ?? null;
            $is_past = $check_out ? ($check_out < date('Y-m-d')) : ($check_in < date('Y-m-d'));
            $status = $row['status'];

            // Déterminer la couleur du badge selon le statut
            $badge_style = "";
            $status_text = "Confirmée";
            if ($status === 'pending') {
                $badge_style = "background: linear-gradient(135deg, #f59e0b, #d97706);";
                $status_text = "En attente de paiement";
            } elseif ($status === 'cancelled') {
                $badge_style = "background: linear-gradient(135deg, #ef4444, #dc2626);";
                $status_text = "Annulée";
            } elseif ($status === 'paid') {
                if ($is_past) {
                    $badge_style = "background: linear-gradient(135deg, #9ca3af, #6b7280);";
                    $status_text = "Terminée";
                } else {
                    $badge_style = "background: linear-gradient(135deg, #34d399, #10b981);";
                    $status_text = "Payée & Confirmée";
                }
            }

            // Calcul du prix total
            $num_nights = 0;
            $total_price = 0;
            if ($check_in && $check_out) {
                $d1 = new DateTime($check_in);
                $d2 = new DateTime($check_out);
                $num_nights = $d2->diff($d1)->days;
                $total_price = $num_nights * $row['price_per_night'];
            }
            ?>
            <div class="room">
                <div class="room-header">
                    <h3>Chambre <?= htmlspecialchars($row['room_number']) ?></h3>
                    <span class="badge" style="<?= $badge_style ?>"><?= $status_text ?></span>
                </div>

                <div class="room-body">
                    <p><strong>Type :</strong> <?= htmlspecialchars($row['type']) ?></p>
                    <p><strong>Nom du client :</strong> <?= htmlspecialchars($row['client_name']) ?></p>
                    <p><strong>Personnes :</strong> <?= (int)$row['num_guests'] ?></p>
                    <?php if ($check_in && $check_out): ?>
                        <p><strong>Check-in :</strong> <?= htmlspecialchars($check_in) ?></p>
                        <p><strong>Check-out :</strong> <?= htmlspecialchars($check_out) ?></p>
                        <p><strong>Durée :</strong> <?= $num_nights ?> nuit<?= $num_nights > 1 ? 's' : '' ?></p>
                        <p><strong>Prix total :</strong> <?= number_format($total_price, 2, ',', ' ') ?> €</p>
                    <?php else: ?>
                        <p><strong>Date :</strong> <?= htmlspecialchars($check_in) ?></p>
                    <?php endif; ?>
                    <p><strong>Réservée le :</strong> <?= htmlspecialchars($row['created_at']) ?></p>
                </div>

                <div class="actions-group">
                    <?php if ($status === 'pending'): ?>
                        <?php if (!empty($row['payment_method']) && !empty($row['receipt_url'])): ?>
                            <p style="color:#f59e0b; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; padding: 10px; background: rgba(245,158,11,0.1); border-radius: 8px; border: 1px solid rgba(245,158,11,0.2); width: 100%;">
                                ⏳ Reçu <?= ucfirst($row['payment_method']) ?> soumis. En attente de validation admin.
                            </p>
                        <?php else: ?>
                            <a href="paiement.php?id=<?= $row['id'] ?>" class="btn">Procéder au paiement</a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($status === 'paid'): ?>
                        <a href="facture.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline">📄 Facture PDF</a>
                    <?php endif; ?>

                    <?php if (!$is_past && $status !== 'cancelled'): ?>
                        <form method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="cancel_reservation_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" style="background: linear-gradient(135deg, #ef4444, #dc2626);" onclick="return confirm('Êtes-vous sûr de vouloir annuler ?')">Annuler</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Formulaire d'avis si séjour terminé et payé et non encore noté -->
                <?php if ($status === 'paid' && $is_past && !$row['review_id']): ?>
                    <div class="review-form">
                        <h4>Laisser un avis sur votre séjour</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="reservation_id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="room_id" value="<?= (int)$row['room_id'] ?>">
                            
                            <label>Note (sur 5)</label>
                            <select name="rating" required style="width:100px; display:inline-block;">
                                <option value="5">⭐⭐⭐⭐⭐ 5/5</option>
                                <option value="4">⭐⭐⭐⭐ 4/5</option>
                                <option value="3">⭐⭐⭐ 3/5</option>
                                <option value="2">⭐⭐ 2/5</option>
                                <option value="1">⭐ 1/5</option>
                            </select>

                            <label>Commentaire</label>
                            <input type="text" name="comment" placeholder="Qu'avez-vous pensé de la chambre ?" required>

                            <button type="submit">Envoyer mon avis</button>
                        </form>
                    </div>
                <?php elseif ($row['review_id']): ?>
                    <p style="color:#34d399; margin-top:10px; font-size:0.9rem;">✅ Vous avez déjà laissé un avis pour ce séjour.</p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="empty">Vous n'avez aucune réservation pour le moment.</p>
    <?php endif; ?>
</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

</body>
</html>
