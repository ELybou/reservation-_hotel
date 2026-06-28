<?php
require_once "conn.php";

// Sécurité : Vérification stricte du rôle administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';
$message = '';
$message_type = '';

// Traitement des opérations POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Ajouter une chambre
    if ($action === 'add_room') {
        $room_number = trim($_POST['room_number'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $custom_type = trim($_POST['custom_type'] ?? '');
        if ($type === 'Autre' && $custom_type !== '') {
            $type = $custom_type;
        }
        $price_per_night = floatval($_POST['price_per_night'] ?? 0);
        $capacity = (int)($_POST['capacity'] ?? 2);
        $available = isset($_POST['available']) ? 1 : 0;
        $cleaning_status = $_POST['cleaning_status'] ?? 'clean';
        $image_url = trim($_POST['image_url'] ?? '');
        $additional_images = trim($_POST['additional_images'] ?? '');

        if ($room_number === '' || $type === '') {
            $message = "Le numéro et le type de chambre sont obligatoires.";
            $message_type = "error";
        } else {
            // Vérifier les doublons de numéro de chambre
            $chk = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
            $chk->bind_param("s", $room_number);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "Une chambre avec ce numéro existe déjà.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, type, price_per_night, capacity, available, cleaning_status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdiiss", $room_number, $type, $price_per_night, $capacity, $available, $cleaning_status, $image_url);
                if ($stmt->execute()) {
                    $new_room_id = $conn->insert_id;

                    // Images secondaires
                    if ($additional_images !== '') {
                        $urls = explode("\n", str_replace("\r", "", $additional_images));
                        $ins = $conn->prepare("INSERT INTO room_images (room_id, url) VALUES (?, ?)");
                        foreach ($urls as $url) {
                            $url = trim($url);
                            if ($url !== '') {
                                $ins->bind_param("is", $new_room_id, $url);
                                $ins->execute();
                            }
                        }
                        $ins->close();
                    }
                    $message = "Chambre $room_number ajoutée avec succès !";
                    $message_type = "success";
                } else {
                    $message = "Erreur lors de la création de la chambre.";
                    $message_type = "error";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }

    // 2. Modifier une chambre
    elseif ($action === 'edit_room') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        $room_number = trim($_POST['room_number'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $custom_type = trim($_POST['custom_type'] ?? '');
        if ($type === 'Autre' && $custom_type !== '') {
            $type = $custom_type;
        }
        $price_per_night = floatval($_POST['price_per_night'] ?? 0);
        $capacity = (int)($_POST['capacity'] ?? 2);
        $available = isset($_POST['available']) ? 1 : 0;
        $cleaning_status = $_POST['cleaning_status'] ?? 'clean';
        $image_url = trim($_POST['image_url'] ?? '');
        $additional_images = trim($_POST['additional_images'] ?? '');

        if ($room_id > 0 && ($room_number !== '' && $type !== '')) {
            // Vérifier les doublons de numéro
            $chk = $conn->prepare("SELECT id FROM rooms WHERE room_number = ? AND id != ?");
            $chk->bind_param("si", $room_number, $room_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "Une autre chambre porte déjà ce numéro.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("UPDATE rooms SET room_number = ?, type = ?, price_per_night = ?, capacity = ?, available = ?, cleaning_status = ?, image_url = ? WHERE id = ?");
                $stmt->bind_param("ssdiissi", $room_number, $type, $price_per_night, $capacity, $available, $cleaning_status, $image_url, $room_id);
                if ($stmt->execute()) {
                    // Mettre à jour les images secondaires (supprimer anciennes et insérer nouvelles)
                    $del = $conn->prepare("DELETE FROM room_images WHERE room_id = ?");
                    $del->bind_param("i", $room_id);
                    $del->execute();
                    $del->close();

                    if ($additional_images !== '') {
                        $urls = explode("\n", str_replace("\r", "", $additional_images));
                        $ins = $conn->prepare("INSERT INTO room_images (room_id, url) VALUES (?, ?)");
                        foreach ($urls as $url) {
                            $url = trim($url);
                            if ($url !== '') {
                                $ins->bind_param("is", $room_id, $url);
                                $ins->execute();
                            }
                        }
                        $ins->close();
                    }
                    $message = "Chambre $room_number mise à jour avec succès !";
                    $message_type = "success";
                    // Rediriger pour nettoyer l'url d'édition
                    header("Location: admin.php?tab=rooms&msg=" . urlencode($message) . "&msg_type=" . $message_type);
                    exit;
                } else {
                    $message = "Erreur lors de la mise à jour.";
                    $message_type = "error";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }

    // 3. Supprimer une chambre
    elseif ($action === 'delete_room') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        if ($room_id > 0) {
            $conn->begin_transaction();
            try {
                // Supprimer les avis
                $stmt = $conn->prepare("DELETE FROM reviews WHERE room_id = ?");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();

                // Supprimer les réservations
                $stmt = $conn->prepare("DELETE FROM reservations WHERE room_id = ?");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();

                // Supprimer les images
                $stmt = $conn->prepare("DELETE FROM room_images WHERE room_id = ?");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();

                // Supprimer la chambre
                $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message = "La chambre et toutes ses données associées (réservations, avis, images) ont été supprimées.";
                $message_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Une erreur est survenue lors de la suppression : " . $e->getMessage();
                $message_type = "error";
            }
        }
    }

    // 4. Activer / Désactiver la disponibilité d'une chambre
    elseif ($action === 'toggle_availability') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        if ($room_id > 0) {
            $stmt = $conn->prepare("UPDATE rooms SET available = 1 - available WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            if ($stmt->execute()) {
                $message = "Disponibilité de la chambre modifiée avec succès.";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la modification.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }

    // 5. Modifier le statut de nettoyage
    elseif ($action === 'update_cleaning') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        $cleaning_status = $_POST['cleaning_status'] ?? 'clean';
        if ($room_id > 0) {
            $stmt = $conn->prepare("UPDATE rooms SET cleaning_status = ? WHERE id = ?");
            $stmt->bind_param("si", $cleaning_status, $room_id);
            if ($stmt->execute()) {
                $message = "Statut de nettoyage mis à jour.";
                $message_type = "success";
            } else {
                $message = "Erreur de mise à jour.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }

    // 6. Modifier le statut d'une réservation
    elseif ($action === 'update_reservation_status') {
        $reservation_id = (int)($_POST['reservation_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if ($reservation_id > 0) {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $reservation_id);
            if ($stmt->execute()) {
                $message = "Le statut de la réservation a été modifié en '$status'.";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la modification du statut.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }

    // 7. Supprimer une réservation définitivement
    elseif ($action === 'delete_reservation') {
        $reservation_id = (int)($_POST['reservation_id'] ?? 0);
        if ($reservation_id > 0) {
            $conn->begin_transaction();
            try {
                // Supprimer les avis associés
                $stmt = $conn->prepare("DELETE FROM reviews WHERE reservation_id = ?");
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();

                // Supprimer la réservation
                $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message = "Réservation supprimée définitivement.";
                $message_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Erreur lors de la suppression : " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Récupérer un éventuel message passé par GET (redirection)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['msg_type'] ?? 'success';
}

// -------------------------------------------------------------
// Récupération des données selon l'onglet
// -------------------------------------------------------------

// DONNÉES TABLEAU DE BORD (DASHBOARD)
$stats = [];
if ($tab === 'dashboard') {
    // Nombre total de chambres
    $r_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms");
    $stats['total_rooms'] = $r_rooms ? $r_rooms->fetch_assoc()['total'] : 0;

    // Nombre de chambres actives/disponibles
    $r_avail = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE available = 1");
    $stats['available_rooms'] = $r_avail ? $r_avail->fetch_assoc()['total'] : 0;

    // Chambres occupées aujourd'hui
    $today = date('Y-m-d');
    $r_occupied = $conn->prepare("SELECT COUNT(DISTINCT room_id) as total FROM reservations WHERE status = 'paid' AND check_in <= ? AND check_out > ?");
    $r_occupied->bind_param("ss", $today, $today);
    $r_occupied->execute();
    $stats['occupied_today'] = $r_occupied->get_result()->fetch_assoc()['total'];
    $r_occupied->close();

    // Chiffre d'affaires total payé
    $r_revenue = $conn->query("
        SELECT SUM(CASE 
            WHEN r.check_in IS NOT NULL AND r.check_out IS NOT NULL THEN (DATEDIFF(r.check_out, r.check_in) * ro.price_per_night)
            ELSE ro.price_per_night 
        END) as total 
        FROM reservations r 
        JOIN rooms ro ON r.room_id = ro.id 
        WHERE r.status = 'paid'
    ");
    $stats['revenue'] = $r_revenue ? (float)$r_revenue->fetch_assoc()['total'] : 0.0;

    // Chiffre d'affaires en attente (pending)
    $r_pending_revenue = $conn->query("
        SELECT SUM(CASE 
            WHEN r.check_in IS NOT NULL AND r.check_out IS NOT NULL THEN (DATEDIFF(r.check_out, r.check_in) * ro.price_per_night)
            ELSE ro.price_per_night 
        END) as total 
        FROM reservations r 
        JOIN rooms ro ON r.room_id = ro.id 
        WHERE r.status = 'pending'
    ");
    $stats['pending_revenue'] = $r_pending_revenue ? (float)$r_pending_revenue->fetch_assoc()['total'] : 0.0;

    // Nombre total de réservations
    $r_res = $conn->query("SELECT COUNT(*) as total FROM reservations");
    $stats['total_reservations'] = $r_res ? $r_res->fetch_assoc()['total'] : 0;

    // Note moyenne
    $r_rating = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews");
    $rating_data = $r_rating ? $r_rating->fetch_assoc() : ['avg_rating' => 0, 'cnt' => 0];
    $stats['avg_rating'] = (float)$rating_data['avg_rating'];
    $stats['reviews_count'] = (int)$rating_data['cnt'];
}

// DONNÉES CHAMBRES
$rooms = [];
$edit_room = null;
if ($tab === 'rooms') {
    $res_rooms = $conn->query("
        SELECT ro.*, 
               (SELECT GROUP_CONCAT(url SEPARATOR '\n') FROM room_images WHERE room_id = ro.id) as secondary_images 
        FROM rooms ro 
        ORDER BY ro.room_number ASC
    ");
    if ($res_rooms) {
        while ($r = $res_rooms->fetch_assoc()) {
            $rooms[] = $r;
        }
    }

    // Si on est en mode édition d'une chambre
    if (isset($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];
        $stmt = $conn->prepare("
            SELECT ro.*, 
                   (SELECT GROUP_CONCAT(url SEPARATOR '\n') FROM room_images WHERE room_id = ro.id) as secondary_images 
            FROM rooms ro 
            WHERE ro.id = ?
        ");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_room = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// DONNÉES RÉSERVATIONS
$reservations = [];
if ($tab === 'reservations') {
    $search_res = trim($_GET['search_res'] ?? '');
    $filter_status = trim($_GET['status'] ?? '');

    $sql = "
        SELECT r.*, ro.room_number, ro.type as room_type, ro.price_per_night, u.full_name as user_name, u.email as user_email
        FROM reservations r 
        JOIN rooms ro ON r.room_id = ro.id 
        LEFT JOIN users u ON r.user_id = u.id
        WHERE 1 = 1
    ";
    $params = [];
    $types = "";

    if ($search_res !== '') {
        $sql .= " AND (r.client_name LIKE ? OR ro.room_number LIKE ? OR u.full_name LIKE ?)";
        $like_search = "%$search_res%";
        $params[] = $like_search;
        $params[] = $like_search;
        $params[] = $like_search;
        $types .= "sss";
    }

    if ($filter_status !== '') {
        $sql .= " AND r.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $sql .= " ORDER BY r.check_in DESC, r.created_at DESC";

    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res_reservations = $stmt->get_result();
    } else {
        $res_reservations = $conn->query($sql);
    }

    if ($res_reservations) {
        while ($res = $res_reservations->fetch_assoc()) {
            $reservations[] = $res;
        }
    }
    if (isset($stmt)) $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - Hôtel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-layout {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        /* Tabs styles */
        .admin-tabs {
            display: flex;
            gap: 12px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 12px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .admin-tabs a {
            padding: 12px 24px;
            background: rgba(255,255,255,0.06);
            color: #d9d2c1;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .admin-tabs a:hover {
            background: rgba(255,255,255,0.12);
            color: white;
        }
        .admin-tabs a.active {
            background: linear-gradient(135deg, #d4af37, #8c6b22);
            color: white;
            border-color: #d4af37;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.25);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 22px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 0.95rem;
            color: #d9d2c1;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 2.2rem;
            font-weight: bold;
            color: #d4af37;
        }
        .stat-card .sub-value {
            font-size: 0.85rem;
            color: #a0aec0;
            margin-top: 5px;
        }

        /* Forms Layout in Rooms */
        .rooms-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        @media(min-width: 900px) {
            .rooms-layout {
                grid-template-columns: 2fr 1fr;
            }
        }

        .img-preview-mini {
            width: 50px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Helpers */
        .flex-actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .flex-actions form {
            margin-top: 0;
            display: inline-block;
        }
        .flex-actions button, .flex-actions a.btn-small {
            width: auto;
            margin-top: 0;
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-blue {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
        }
        .btn-green {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            border: none;
        }
        .btn-red {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: white;
            border: none;
        }
        .btn-amber {
            background: linear-gradient(135deg, #f59e0b, #b45309);
            color: white;
            border: none;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.25);
        }

        /* Filter block */
        .admin-filter-bar {
            background: rgba(255,255,255,0.06);
            padding: 18px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .admin-filter-bar form {
            margin-top: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .admin-filter-bar .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .admin-filter-bar label {
            margin-top: 0;
            font-size: 0.85rem;
            color: #d9d2c1;
        }
        .admin-filter-bar button {
            margin-top: 0;
            width: auto;
            padding: 11px 20px;
        }

        textarea {
            width: 100%;
            padding: 12px 13px;
            margin-top: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            background: rgba(255,255,255,0.14);
            color: white;
            outline: none;
            font-family: inherit;
            resize: vertical;
        }
        textarea:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.25);
        }
    </style>
</head>
<body>

<header>
    <h1>🔑 Espace Administration</h1>
    <p>Gestion globale des chambres, de la disponibilité et des réservations de l'hôtel</p>
    <div>
        <span style="color:#d9d2c1; margin-right: 15px;">Connecté en tant qu'<strong>Administrateur</strong></span>
        <a class="logout-link" href="logout.php">Déconnexion</a>
    </div>
</header>

<section class="container">
    
    <?php if ($message !== ''): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="admin-layout">
        <!-- Navigation par onglets -->
        <div class="admin-tabs">
            <a href="admin.php?tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">📊 Tableau de bord</a>
            <a href="admin.php?tab=rooms" class="<?= $tab === 'rooms' ? 'active' : '' ?>">🔑 Chambres & Disponibilités</a>
            <a href="admin.php?tab=reservations" class="<?= $tab === 'reservations' ? 'active' : '' ?>">📅 Réservations client</a>
        </div>

        <!-- CONTENU : TABLEAU DE BORD (DASHBOARD) -->
        <?php if ($tab === 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Chiffre d'affaires</h3>
                    <div class="value"><?= number_format($stats['revenue'], 2, ',', ' ') ?> €</div>
                    <div class="sub-value">Confirmé & payé</div>
                </div>
                <div class="stat-card">
                    <h3>En attente</h3>
                    <div class="value" style="color: #f59e0b;"><?= number_format($stats['pending_revenue'], 2, ',', ' ') ?> €</div>
                    <div class="sub-value">Panier validé / attente</div>
                </div>
                <div class="stat-card">
                    <h3>Occupation</h3>
                    <div class="value"><?= $stats['occupied_today'] ?> / <?= $stats['total_rooms'] ?></div>
                    <div class="sub-value">Chambres occupées ce jour</div>
                </div>
                <div class="stat-card">
                    <h3>Taux de dispo</h3>
                    <div class="value" style="color: #10b981;">
                        <?= $stats['total_rooms'] > 0 ? round(($stats['available_rooms'] / $stats['total_rooms']) * 100) : 0 ?> %
                    </div>
                    <div class="sub-value"><?= $stats['available_rooms'] ?> chambre(s) actives</div>
                </div>
                <div class="stat-card">
                    <h3>Total Résas</h3>
                    <div class="value"><?= $stats['total_reservations'] ?></div>
                    <div class="sub-value">Réservations enregistrées</div>
                </div>
                <div class="stat-card">
                    <h3>Note Clients</h3>
                    <div class="value" style="color: #f59e0b;">
                        <?= $stats['reviews_count'] > 0 ? number_format($stats['avg_rating'], 1) . '⭐' : 'N/A' ?>
                    </div>
                    <div class="sub-value"><?= $stats['reviews_count'] ?> avis clients</div>
                </div>
            </div>

            <!-- Graphique / Raccourcis rapides -->
            <div class="room">
                <h3>Bienvenue dans votre espace d'administration</h3>
                <p style="margin-top:10px; line-height:1.6;">
                    Utilisez les onglets ci-dessus pour gérer la liste des chambres et changer leur disponibilité (par exemple en les désactivant temporairement pour travaux ou maintenance), ou pour gérer les réservations de vos clients (valider des paiements, annuler des séjours).
                </p>
                <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="admin.php?tab=rooms" class="btn" style="width:auto; text-decoration:none; padding: 12px 20px;">Gérer les chambres</a>
                    <a href="admin.php?tab=reservations" class="btn" style="width:auto; text-decoration:none; background:linear-gradient(135deg, #10b981, #059669); padding: 12px 20px;">Voir les réservations récentes</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- CONTENU : GESTION DES CHAMBRES & DISPONIBILITÉS -->
        <?php if ($tab === 'rooms'): ?>
            <div class="rooms-layout">
                <!-- LISTE DES CHAMBRES -->
                <div class="room" style="padding: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3>Chambres enregistrées</h3>
                        <a href="admin.php?tab=rooms" class="btn-small btn-secondary" style="padding:8px 12px; text-decoration:none; border-radius:8px; font-size:0.85rem; font-weight:bold;">+ Nouvelle Chambre</a>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Chambre</th>
                                    <th>Image</th>
                                    <th>Type</th>
                                    <th>Prix / Nuit</th>
                                    <th>Capacité</th>
                                    <th>Disponibilité</th>
                                    <th>Nettoyage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rooms)): ?>
                                    <?php foreach ($rooms as $r): ?>
                                        <tr>
                                            <td style="font-weight: bold; font-size:1.05rem;">N° <?= htmlspecialchars($r['room_number']) ?></td>
                                            <td>
                                                <?php if (!empty($r['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($r['image_url']) ?>" alt="Miniature" class="img-preview-mini">
                                                <?php else: ?>
                                                    <div class="img-preview-mini" style="background:#4a5568; display:flex; align-items:center; justify-content:center; font-size:9px; color:#cbd5e0;">No image</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($r['type']) ?></td>
                                            <td><?= number_format($r['price_per_night'], 2, ',', ' ') ?> €</td>
                                            <td><?= $r['capacity'] ?> pers.</td>
                                            <td>
                                                <form method="POST" style="margin-top:0;">
                                                    <input type="hidden" name="action" value="toggle_availability">
                                                    <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                                    <?php if ($r['available']): ?>
                                                        <button type="submit" class="btn-green" style="padding:5px 10px; font-size:0.8rem; width:auto; margin-top:0;">🟢 Actif</button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn-red" style="padding:5px 10px; font-size:0.8rem; width:auto; margin-top:0;">🔴 Bloqué</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" style="margin-top:0;">
                                                    <input type="hidden" name="action" value="update_cleaning">
                                                    <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                                    <select name="cleaning_status" onchange="this.form.submit()" style="padding:4px 8px; font-size:0.8rem; margin-top:0; border-radius:6px; background:rgba(255,255,255,0.08); min-width:110px;">
                                                        <option value="clean" <?= $r['cleaning_status'] === 'clean' ? 'selected' : '' ?>>✨ Propre</option>
                                                        <option value="dirty" <?= $r['cleaning_status'] === 'dirty' ? 'selected' : '' ?>>🧹 Sale</option>
                                                        <option value="cleaning" <?= $r['cleaning_status'] === 'cleaning' ? 'selected' : '' ?>>🧼 En cours</option>
                                                        <option value="maintenance" <?= $r['cleaning_status'] === 'maintenance' ? 'selected' : '' ?>>🔧 Entretien</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="flex-actions">
                                                    <a href="admin.php?tab=rooms&edit=<?= $r['id'] ?>" class="btn-small btn-blue">✏️ Modifier</a>
                                                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette chambre ? Cela supprimera également tous ses avis et ses réservations.')">
                                                        <input type="hidden" name="action" value="delete_room">
                                                        <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                                        <button type="submit" class="btn-red" style="padding:8px 12px; font-size:0.8rem;">🗑️ Supprimer</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: #a0aec0;">Aucune chambre trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FORMULAIRE AJOUT / EDITION -->
                <div class="room" style="padding: 20px; align-self: flex-start;">
                    <?php if ($edit_room): ?>
                        <h3>✏️ Modifier la chambre <?= htmlspecialchars($edit_room['room_number']) ?></h3>
                    <?php else: ?>
                        <h3>➕ Ajouter une chambre</h3>
                    <?php endif; ?>

                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="<?= $edit_room ? 'edit_room' : 'add_room' ?>">
                        <?php if ($edit_room): ?>
                            <input type="hidden" name="room_id" value="<?= $edit_room['id'] ?>">
                        <?php endif; ?>

                        <label>Numéro de chambre</label>
                        <input type="text" name="room_number" value="<?= htmlspecialchars($edit_room['room_number'] ?? '') ?>" placeholder="Ex: 105" required>

                        <label>Type de chambre</label>
                        <select name="type" id="room_type_select" onchange="toggleCustomType(this.value)" required>
                            <option value="Simple" <?= ($edit_room['type'] ?? '') === 'Simple' ? 'selected' : '' ?>>Simple</option>
                            <option value="Double" <?= ($edit_room['type'] ?? '') === 'Double' ? 'selected' : '' ?>>Double</option>
                            <option value="Suite" <?= ($edit_room['type'] ?? '') === 'Suite' ? 'selected' : '' ?>>Suite</option>
                            <option value="Familiale" <?= ($edit_room['type'] ?? '') === 'Familiale' ? 'selected' : '' ?>>Familiale</option>
                            <option value="Autre" <?= ($edit_room && !in_array($edit_room['type'], ['Simple', 'Double', 'Suite', 'Familiale'])) ? 'selected' : '' ?>>Autre...</option>
                        </select>

                        <div id="custom_type_container" style="display: <?= ($edit_room && !in_array($edit_room['type'], ['Simple', 'Double', 'Suite', 'Familiale'])) ? 'block' : 'none' ?>; margin-top: 10px;">
                            <label>Saisir le type personnalisé</label>
                            <input type="text" name="custom_type" value="<?= htmlspecialchars($edit_room['type'] ?? '') ?>" placeholder="Ex: Loft Deluxe">
                        </div>

                        <label>Prix par nuit (€)</label>
                        <input type="number" name="price_per_night" step="0.01" min="0" value="<?= htmlspecialchars($edit_room['price_per_night'] ?? '100.00') ?>" required>

                        <label>Capacité maximale (Personnes)</label>
                        <input type="number" name="capacity" min="1" max="20" value="<?= htmlspecialchars($edit_room['capacity'] ?? '2') ?>" required>

                        <label>Image principale (URL)</label>
                        <input type="url" name="image_url" value="<?= htmlspecialchars($edit_room['image_url'] ?? '') ?>" placeholder="https://images.unsplash.com/...">

                        <label>Images additionnelles (Une URL par ligne)</label>
                        <textarea name="additional_images" rows="4" placeholder="https://images.unsplash.com/image1...&#10;https://images.unsplash.com/image2..."><?= htmlspecialchars($edit_room['secondary_images'] ?? '') ?></textarea>

                        <label>Statut de nettoyage initial</label>
                        <select name="cleaning_status">
                            <option value="clean" <?= ($edit_room['cleaning_status'] ?? '') === 'clean' ? 'selected' : '' ?>>✨ Propre</option>
                            <option value="dirty" <?= ($edit_room['cleaning_status'] ?? '') === 'dirty' ? 'selected' : '' ?>>🧹 Sale</option>
                            <option value="cleaning" <?= ($edit_room['cleaning_status'] ?? '') === 'cleaning' ? 'selected' : '' ?>>🧼 En cours</option>
                            <option value="maintenance" <?= ($edit_room['cleaning_status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>🔧 Entretien</option>
                        </select>

                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="available" id="available_chk" value="1" <?= (!isset($edit_room) || $edit_room['available']) ? 'checked' : '' ?> style="width: auto; margin-top:0; cursor:pointer;">
                            <label for="available_chk" style="margin-top:0; cursor:pointer;">Chambre disponible pour les réservations</label>
                        </div>

                        <div style="display:flex; gap:10px; margin-top: 20px;">
                            <button type="submit" class="btn-green" style="margin-top:0; flex:1;"><?= $edit_room ? '💾 Sauvegarder' : '➕ Ajouter' ?></button>
                            <?php if ($edit_room): ?>
                                <a href="admin.php?tab=rooms" class="btn btn-secondary" style="margin-top:0; flex:1; text-align:center; text-decoration:none; padding:13px; border-radius:12px; font-weight:700;">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function toggleCustomType(value) {
                    const container = document.getElementById('custom_type_container');
                    if (value === 'Autre') {
                        container.style.display = 'block';
                    } else {
                        container.style.display = 'none';
                    }
                }
            </script>
        <?php endif; ?>

        <!-- CONTENU : GESTION DES RÉSERVATIONS CLIENTS -->
        <?php if ($tab === 'reservations'): ?>
            <!-- BARRE DE RECHERCHE & FILTRAGE -->
            <div class="admin-filter-bar">
                <form method="GET">
                    <input type="hidden" name="tab" value="reservations">
                    <div class="filter-group">
                        <label>Rechercher un client, chambre ou compte</label>
                        <input type="text" name="search_res" value="<?= htmlspecialchars($search_res ?? '') ?>" placeholder="Ex: Dupont, 101, admin...">
                    </div>
                    <div class="filter-group">
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= ($filter_status ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="paid" <?= ($filter_status ?? '') === 'paid' ? 'selected' : '' ?>>Payée</option>
                            <option value="cancelled" <?= ($filter_status ?? '') === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    <button type="submit">Rechercher</button>
                    <a href="admin.php?tab=reservations" class="btn btn-secondary" style="text-decoration:none; padding:11px 20px; font-size:15px; font-weight:700; border-radius:12px; height: 46px; line-height:22px; display:inline-block; margin-top:0;">Réinitialiser</a>
                </form>
            </div>

            <!-- TABLEAU DES RÉSERVATIONS -->
            <div class="room" style="padding: 20px;">
                <h3>Réservations clients</h3>
                <div class="table-container" style="margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Chambre</th>
                                <th>Arrivée</th>
                                <th>Départ</th>
                                <th>Nuits</th>
                                <th>Prix total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reservations)): ?>
                                <?php foreach ($reservations as $res): ?>
                                    <?php
                                    $d1 = new DateTime($res['check_in']);
                                    $d2 = new DateTime($res['check_out']);
                                    $nights = $d2->diff($d1)->days;
                                    if ($nights < 1) $nights = 1;
                                    $res_price = $nights * $res['price_per_night'];
                                    
                                    // Style statut
                                    $status_style = "";
                                    $status_label = "";
                                    if ($res['status'] === 'pending') {
                                        $status_style = "background: rgba(245,158,11,0.2); color:#f59e0b; border: 1px solid rgba(245,158,11,0.4);";
                                        $status_label = "En attente";
                                    } elseif ($res['status'] === 'paid') {
                                        $status_style = "background: rgba(16,185,129,0.2); color:#10b981; border: 1px solid rgba(16,185,129,0.4);";
                                        $status_label = "Payée & Confirmée";
                                    } elseif ($res['status'] === 'cancelled') {
                                        $status_style = "background: rgba(239,68,68,0.2); color:#ef4444; border: 1px solid rgba(239,68,68,0.4);";
                                        $status_label = "Annulée";
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:bold;"><?= htmlspecialchars($res['client_name']) ?></div>
                                            <div style="font-size:0.8rem; color:#a0aec0;">
                                                <?php if ($res['user_name']): ?>
                                                    Compte: <?= htmlspecialchars($res['user_name']) ?> (<?= htmlspecialchars($res['user_email']) ?>)
                                                <?php else: ?>
                                                    Sans compte (Anonyme)
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($res['payment_method'])): ?>
                                                <div style="margin-top: 5px; font-size: 0.8rem;">
                                                    Paiement : <strong><?= ucfirst($res['payment_method']) ?></strong>
                                                    <?php if (!empty($res['receipt_url'])): ?>
                                                        | <a href="<?= htmlspecialchars($res['receipt_url']) ?>" target="_blank" style="color: #d4af37; text-decoration: underline;">📄 Reçu</a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight:bold; color:#d4af37;">N° <?= htmlspecialchars($res['room_number']) ?></span>
                                            <span style="font-size:0.8rem; color:#cbd5e0; block;"><?= htmlspecialchars($res['room_type']) ?></span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($res['check_in'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($res['check_out'])) ?></td>
                                        <td><?= $nights ?> nuit(s)</td>
                                        <td style="font-weight:bold;"><?= number_format($res_price, 2, ',', ' ') ?> €</td>
                                        <td>
                                            <span class="badge" style="<?= $status_style ?> font-size:0.75rem; padding: 4px 10px;"><?= $status_label ?></span>
                                        </td>
                                        <td>
                                            <div class="flex-actions">
                                                <!-- Modifier statut -->
                                                <form method="POST" style="margin-top:0;">
                                                    <input type="hidden" name="action" value="update_reservation_status">
                                                    <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                                                    <select name="status" onchange="this.form.submit()" style="padding:4px 8px; font-size:0.8rem; margin-top:0; border-radius:6px; background:rgba(255,255,255,0.08); min-width:110px;">
                                                        <option value="pending" <?= $res['status'] === 'pending' ? 'selected' : '' ?>>⏳ En attente</option>
                                                        <option value="paid" <?= $res['status'] === 'paid' ? 'selected' : '' ?>>💳 Payer/Valider</option>
                                                        <option value="cancelled" <?= $res['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Annuler</option>
                                                    </select>
                                                </form>

                                                <?php if ($res['status'] === 'paid'): ?>
                                                    <a href="facture.php?id=<?= $res['id'] ?>" target="_blank" class="btn-small btn-blue" style="text-decoration:none;">📄 Facture</a>
                                                <?php endif; ?>

                                                <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette réservation ? Cette action est irréversible.')" style="margin-top:0;">
                                                    <input type="hidden" name="action" value="delete_reservation">
                                                    <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                                                    <button type="submit" class="btn-red" style="padding:8px 12px; font-size:0.8rem;">🗑️ Supprimer</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #a0aec0;">Aucune réservation trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

</section>

<footer>
    <p>© 2026 - Hotel Booking System - Espace Administration</p>
</footer>

</body>
</html>
