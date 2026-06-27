<?php
require_once "../conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = "";
$message_type = "";

// ===== AJOUTER UNE CHAMBRE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_room') {
    $room_number = trim($_POST['room_number'] ?? '');
    $room_type = trim($_POST['room_type'] ?? '');
    $price = floatval($_POST['price_per_night'] ?? 0);
    $capacity = (int)($_POST['capacity'] ?? 1);
    // Handle uploaded image file
    $image_url = '';
    if (!empty($_FILES['image_file']['name'])) {
        $targetDir = '../images/rooms/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $newName = uniqid('room_', true) . '.' . $ext;
        $dest = $targetDir . $newName;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
            $image_url = 'images/rooms/' . $newName;
        }
    }

    if ($room_number === '' || $room_type === '' || $price <= 0 || $capacity < 1) {
        $message = "Veuillez remplir tous les champs obligatoires correctement.";
        $message_type = "error";
    } else {
        // Vérifier que le numéro de chambre n'existe pas déjà
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
        $check->bind_param("s", $room_number);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Une chambre avec ce numéro existe déjà.";
            $message_type = "error";
        } else {
            $insert = $conn->prepare("INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES (?, ?, ?, ?, TRUE, ?)");
            $insert->bind_param("ssdis", $room_number, $room_type, $price, $capacity, $image_url);

            if ($insert->execute()) {
                $message = "Chambre ajoutée avec succès.";
                $message_type = "success";
            } else {
                $message = "Erreur lors de l'ajout de la chambre.";
                $message_type = "error";
            }

            $insert->close();
        }

        $check->close();
    }
}

// ===== SUPPRIMER UNE CHAMBRE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_room') {
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($room_id > 0) {
        // Vérifier s'il y a des réservations actives pour cette chambre
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM reservations WHERE room_id = ? AND check_out >= CURDATE()");
        $check->bind_param("i", $room_id);
        $check->execute();
        $check_result = $check->get_result();
        $count = $check_result->fetch_assoc()['cnt'];
        $check->close();

        if ($count > 0) {
            $message = "Impossible de supprimer : cette chambre a des réservations actives.";
            $message_type = "error";
        } else {
            // Supprimer d'abord les anciennes réservations liées
            $del_res = $conn->prepare("DELETE FROM reservations WHERE room_id = ?");
            $del_res->bind_param("i", $room_id);
            $del_res->execute();
            $del_res->close();

            // Supprimer la chambre
            $delete = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $delete->bind_param("i", $room_id);

            if ($delete->execute()) {
                $message = "Chambre supprimée avec succès.";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la suppression.";
                $message_type = "error";
            }

            $delete->close();
        }
    }
}

// ===== REMETTRE UNE CHAMBRE DISPONIBLE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_available') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 1);

    if ($room_id > 0) {
        $update = $conn->prepare("UPDATE rooms SET available = ? WHERE id = ?");
        $update->bind_param("ii", $new_status, $room_id);
        $update->execute();
        $update->close();

        $message = $new_status ? "Chambre remise comme disponible." : "Chambre bloquée manuellement.";
        $message_type = "success";
    }
}

// ===== AJOUTER UNE IMAGE A UNE CHAMBRE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_room_image') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $img_url = trim($_POST['image_url'] ?? '');

    if ($room_id > 0 && filter_var($img_url, FILTER_VALIDATE_URL)) {
        $insert = $conn->prepare("INSERT INTO room_images (room_id, url) VALUES (?, ?)");
        $insert->bind_param("is", $room_id, $img_url);
        $insert->execute();
        $insert->close();
        $message = "Image ajoutée à la galerie de la chambre.";
        $message_type = "success";
    } else {
        $message = "URL de l'image invalide.";
        $message_type = "error";
    }
}

// ===== METTRE A JOUR LE MENAGE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cleaning') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $new_cleaning = $_POST['cleaning_status'] ?? 'clean';

    if ($room_id > 0 && in_array($new_cleaning, ['clean', 'dirty', 'cleaning', 'maintenance'])) {
        $update = $conn->prepare("UPDATE rooms SET cleaning_status = ? WHERE id = ?");
        $update->bind_param("si", $new_cleaning, $room_id);
        $update->execute();
        $update->close();

        $message = "Statut de nettoyage mis à jour.";
        $message_type = "success";
    }
}

// ===== METTRE A JOUR LE STATUT DE RESERVATION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $allowed = ['pending', 'paid', 'cancelled'];
    if ($reservation_id > 0 && in_array($new_status, $allowed)) {
        // Retrieve current status
        $cur_stmt = $conn->prepare("SELECT status FROM reservations WHERE id = ?");
        $cur_stmt->bind_param("i", $reservation_id);
        $cur_stmt->execute();
        $cur_res = $cur_stmt->get_result();
        $row = $cur_res->fetch_assoc();
        $cur_stmt->close();
        $current_status = $row['status'] ?? '';
        // Define allowed transitions
        $valid = false;
        if ($current_status === 'pending' && in_array($new_status, ['paid', 'cancelled'])) {
            $valid = true;
        } elseif ($current_status === 'paid' && $new_status === 'cancelled') {
            $valid = true;
        }
        if ($valid) {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $reservation_id);
            if ($stmt->execute()) {
                $message = "Statut de réservation mis à jour.";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la mise à jour du statut.";
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Transition de statut invalide.";
            $message_type = "error";
        }
    }
}

// ===== AUTO-DIRTY (Ménage automatique au départ du client) =====
// Marquer "sale" toutes les chambres dont une réservation s'est terminée avant aujourd'hui 
// et qui sont encore marquées "clean".
$conn->query("
    UPDATE rooms 
    SET cleaning_status = 'dirty' 
    WHERE cleaning_status = 'clean' 
    AND id IN (
        SELECT room_id FROM reservations 
        WHERE status IN ('paid', 'pending') AND check_out <= CURDATE()
    )
");

// ===== STATISTIQUES DASHBOARD =====
$rev_query = $conn->query("
    SELECT SUM(DATEDIFF(r.check_out, r.check_in) * ro.price_per_night) as total_revenue
    FROM reservations r
    JOIN rooms ro ON r.room_id = ro.id
    WHERE r.check_in IS NOT NULL AND r.check_out IS NOT NULL
");
$stats_revenue = $rev_query->fetch_assoc()['total_revenue'] ?? 0;

$occ_query = $conn->query("SELECT COUNT(*) as total_reservations FROM reservations");
$total_reservations = $occ_query->fetch_assoc()['total_reservations'] ?? 0;

$rooms_query = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
$total_rooms = $rooms_query->fetch_assoc()['total_rooms'] ?? 0;

$pop_query = $conn->query("
    SELECT ro.type, COUNT(r.id) as count
    FROM reservations r
    JOIN rooms ro ON r.room_id = ro.id
    GROUP BY ro.type
    ORDER BY count DESC
    LIMIT 1
");
$most_popular = $pop_query->fetch_assoc()['type'] ?? 'N/A';

// Récupérer toutes les chambres
$rooms = $conn->query("SELECT * FROM rooms ORDER BY room_number ASC");

// Récupérer toutes les réservations
$reservations = $conn->query("
    SELECT r.id, r.client_name, r.check_in, r.check_out, r.num_guests, r.status, r.reservation_date, r.created_at,
           ro.room_number, ro.type, ro.price_per_night,
           u.full_name AS user_name, u.email AS user_email
    FROM reservations r
    JOIN rooms ro ON r.room_id = ro.id
    LEFT JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .dashboard-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-card h4 {
            color: #d9d2c1;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #d4af37;
        }
    </style>
</head>
<body>

<header>
    <h1>Panel d'administration</h1>
    <p>Gérez les chambres et suivez vos performances</p>
    <div>
        <a class="logout-link" style="border-color:#34d399; color:#34d399;" href="calendrier.php">📅 Calendrier visuel</a>
        <a class="logout-link" href="../index.php">Accueil Client</a>
        <a class="logout-link" href="../logout.php">Déconnexion</a>
    </div>
</header>

<section class="container">

    <?php if ($message !== ''): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ===== DASHBOARD ===== -->
    <h2>Tableau de bord</h2>
    <div class="dashboard-stats">
        <div class="stat-card">
            <h4>Revenu Total (Payé)</h4>
            <div class="value"><?= number_format($stats_revenue, 2, ',', ' ') ?> €</div>
        </div>
        <div class="stat-card">
            <h4>Total Réservations</h4>
            <div class="value"><?= $total_reservations ?></div>
        </div>
        <div class="stat-card">
            <h4>Type le plus populaire</h4>
            <div class="value"><?= htmlspecialchars($most_popular) ?></div>
        </div>
        <div class="stat-card">
            <h4>Chambres enregistrées</h4>
            <div class="value"><?= $total_rooms ?></div>
        </div>
    </div>

    <!-- ===== AJOUTER UNE CHAMBRE ===== -->
    <h2>Ajouter une chambre</h2>
    <div class="room">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_room">

            <label>Numéro de chambre</label>
            <input type="text" name="room_number" placeholder="Ex: 201" required>

            <label>Type</label>
            <select name="room_type" required>
                <option value="Simple">Simple</option>
                <option value="Double">Double</option>
                <option value="Suite">Suite</option>
                <option value="Familiale">Familiale</option>
            </select>

            <label>Prix par nuit (€)</label>
            <input type="number" name="price_per_night" min="1" step="0.01" placeholder="Ex: 120.00" required>

            <label>Capacité (personnes)</label>
            <input type="number" name="capacity" min="1" max="10" value="2" required>

            <label>Image (Optionnel)</label>
            <input type="file" name="image_file" accept="image/*">

            <button type="submit">Ajouter la chambre</button>
        </form>
    </div>

    <!-- ===== LISTE DES CHAMBRES ===== -->
    <h2>Toutes les chambres</h2>

    <?php if ($rooms && $rooms->num_rows > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Type</th>
                        <th>Prix/nuit</th>
                        <th>Ménage</th>
                        <th>Statut manuel</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($room = $rooms->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($room['room_number']) ?></td>
                            <td><?= htmlspecialchars($room['type']) ?></td>
                            <td><?= number_format($room['price_per_night'], 2, ',', ' ') ?> €</td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="update_cleaning">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <select name="cleaning_status" onchange="this.form.submit()" style="padding:5px; font-size:0.85rem; border-radius:4px; border:none;">
                                        <option value="clean" <?= $room['cleaning_status'] === 'clean' ? 'selected' : '' ?>>Propre</option>
                                        <option value="dirty" <?= $room['cleaning_status'] === 'dirty' ? 'selected' : '' ?>>Sale</option>
                                        <option value="cleaning" <?= $room['cleaning_status'] === 'cleaning' ? 'selected' : '' ?>>En nettoyage</option>
                                        <option value="maintenance" <?= $room['cleaning_status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <span class="badge" <?php if (!$room['available']) echo 'style="background: linear-gradient(135deg, #ef4444, #dc2626);"'; ?>>
                                    <?= $room['available'] ? 'Ouverte' : 'Bloquée' ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline; margin-bottom:5px;">
                                    <input type="hidden" name="action" value="toggle_available">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $room['available'] ? 0 : 1 ?>">
                                    <button type="submit" style="padding:5px; font-size:12px; margin:2px;"><?= $room['available'] ? 'Bloquer' : 'Libérer' ?></button>
                                </form>
                                <form method="POST" style="display:inline; margin-bottom:5px;">
                                    <input type="hidden" name="action" value="delete_room">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <button type="submit" style="padding:5px; font-size:12px; margin:2px;" onclick="return confirm('Supprimer cette chambre ?')">Supprimer</button>
                                </form>
                                <form method="POST" style="display:flex; margin-top:5px; gap:5px;">
                                    <input type="hidden" name="action" value="add_room_image">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <input type="url" name="image_url" placeholder="Nouvelle URL d'image" required style="padding:5px; width:120px; font-size:12px; margin:0;">
                                    <button type="submit" style="padding:5px; font-size:12px; margin:0;">+ Photo</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">Aucune chambre enregistrée.</p>
    <?php endif; ?>

    <!-- ===== TOUTES LES RÉSERVATIONS ===== -->
    <h2>Toutes les réservations</h2>

    <?php if ($reservations && $reservations->num_rows > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Chambre</th>
                        <th>Client</th>
                        <th>Utilisateur</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Pers.</th>
                        <th>Total</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($res = $reservations->fetch_assoc()): ?>
                        <?php
                        $check_in = $res['check_in'] ?? $res['reservation_date'];
                        $check_out = $res['check_out'] ?? null;
                        
                        $status = $res['status'];
                        $is_past = $check_out ? ($check_out < date('Y-m-d')) : ($check_in < date('Y-m-d'));

                        $badge_style = "";
                        $status_text = "Confirmée";
                        if ($status === 'pending') {
                            $badge_style = "background: linear-gradient(135deg, #f59e0b, #d97706);";
                            $status_text = "En attente";
                        } elseif ($status === 'cancelled') {
                            $badge_style = "background: linear-gradient(135deg, #ef4444, #dc2626);";
                            $status_text = "Annulée";
                        } elseif ($status === 'paid') {
                            if ($is_past) {
                                $badge_style = "background: linear-gradient(135deg, #9ca3af, #6b7280);";
                                $status_text = "Terminée";
                            } else {
                                $badge_style = "background: linear-gradient(135deg, #34d399, #10b981);";
                                $status_text = "Payée";
                            }
                        }

                        $num_nights = 0;
                        $total = 0;
                        if ($check_in && $check_out) {
                            $d1 = new DateTime($check_in);
                            $d2 = new DateTime($check_out);
                            $num_nights = $d2->diff($d1)->days;
                            if ($num_nights < 1) $num_nights = 1;
                            $total = $num_nights * $res['price_per_night'];
                        }
                        // Badge couleur selon statut
                        $badge_style = '';
                        $status_text = 'Confirmée';
                        switch ($res['status']) {
                            case 'pending':
                                $badge_style = 'background: linear-gradient(135deg, #f59e0b, #d97706);';
                                $status_text = 'En attente';
                                break;
                            case 'cancelled':
                                $badge_style = 'background: linear-gradient(135deg, #ef4444, #dc2626);';
                                $status_text = 'Annulée';
                                break;
                            case 'paid':
                                $badge_style = 'background: linear-gradient(135deg, #34d399, #10b981);';
                                $status_text = 'Payée';
                                break;
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($res['room_number']) ?> (<?= htmlspecialchars($res['type']) ?>)</td>
                            <td><?= htmlspecialchars($res['client_name']) ?></td>
                            <td><?= htmlspecialchars($res['user_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($check_in) ?></td>
                            <td><?= htmlspecialchars($check_out ?? '-') ?></td>
                            <td><?= (int)$res['num_guests'] ?></td>
                            <td><?= $num_nights > 0 ? number_format($total, 2, ',', ' ') . ' €' : '-' ?></td>
                            <td>
                                <span class="badge" style="<?= $badge_style ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                    <select name="new_status" onchange="this.form.submit()" style="padding:3px; font-size:0.85rem;">
                                        <option value="pending" <?= $res['status']==='pending' ? 'selected' : '' ?>>En attente</option>
                                        <option value="paid" <?= $res['status']==='paid' ? 'selected' : '' ?>>Payée</option>
                                        <option value="cancelled" <?= $res['status']==='cancelled' ? 'selected' : '' ?>>Annulée</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">Aucune réservation enregistrée.</p>
    <?php endif; ?>

</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

</body>
</html>
