<?php
require_once "conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

// Récupérer les messages de réservation
$reserve_message = '';
$reserve_message_type = '';
if (isset($_SESSION['reserve_message'])) {
    $reserve_message = $_SESSION['reserve_message'];
    $reserve_message_type = $_SESSION['reserve_message_type'] ?? '';
    unset($_SESSION['reserve_message'], $_SESSION['reserve_message_type']);
}

// Paramètres de recherche
$search_check_in = $_GET['check_in'] ?? date('Y-m-d');
$search_check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
$search_guests = (int)($_GET['guests'] ?? 1);
$filter_type = trim($_GET['type'] ?? '');

if ($search_check_in < date('Y-m-d')) $search_check_in = date('Y-m-d');
if ($search_check_out <= $search_check_in) $search_check_out = date('Y-m-d', strtotime($search_check_in . ' +1 day'));
if ($search_guests < 1) $search_guests = 1;

$types_result = $conn->query("SELECT DISTINCT type FROM rooms ORDER BY type ASC");

// Et récupérer la note moyenne des avis et toutes les images
$sql = "
    SELECT ro.*, 
           AVG(rev.rating) as avg_rating, 
           COUNT(rev.id) as review_count,
           (SELECT GROUP_CONCAT(url SEPARATOR '|') FROM room_images WHERE room_id = ro.id) as all_images
    FROM rooms ro
    LEFT JOIN reviews rev ON rev.room_id = ro.id
    WHERE ro.available = 1 
    AND ro.capacity >= ?
";
$params = [$search_guests];
$types = "i";

if ($filter_type !== '') {
    $sql .= " AND ro.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

// Exclure les chambres qui ont une réservation (payée ou en attente) chevauchant les dates demandées
$sql .= " AND ro.id NOT IN (
    SELECT room_id FROM reservations 
    WHERE status != 'cancelled' 
    AND check_in < ? AND check_out > ?
)";
$params[] = $search_check_out;
$params[] = $search_check_in;
$types .= "ss";

$sql .= " GROUP BY ro.id ORDER BY ro.room_number ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réservation d'hôtel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-engine {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .search-engine form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            margin-top: 0;
        }
        .search-group {
            flex: 1;
            min-width: 150px;
        }
        .search-group label {
            margin-top: 0;
            font-size: 0.9rem;
        }
        .search-engine button {
            margin-top: 0;
            flex: 1;
            min-width: 150px;
        }
        .room-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
            background: rgba(0,0,0,0.2);
        }
        /* Carousel Styles */
        .carousel {
            position: relative;
            width: 100%;
            height: 200px;
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(0,0,0,0.2);
        }
        .carousel-images {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.4s ease-in-out;
        }
        .carousel-images img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 50%;
            z-index: 2;
        }
        .carousel-btn:hover { background: rgba(0,0,0,0.8); }
        .carousel-prev { left: 10px; }
        .carousel-next { right: 10px; }
    </style>
</head>
<body>

<header>
    <h1>Système de réservation d'hôtel</h1>
    <p>Bonjour <?= htmlspecialchars($_SESSION['user_name']) ?>, réservez votre chambre facilement</p>
    <div>
        
        <?php
        $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        ?>
        <a class="logout-link" style="border-color:#d4af37; color:#d4af37;" href="panier.php">🛒 Panier (<?= $cart_count ?>)</a>
        <a class="logout-link" href="mes_reservations.php">Mes réservations</a>
        <a class="logout-link" href="profil.php">Mon profil</a>
        <a class="logout-link" href="logout.php">Déconnexion</a>
    </div>
</header>

<section class="container">
    
    <!-- Moteur de recherche avancé -->
    <div class="search-engine">
        <h2>Rechercher une disponibilité</h2>
        <form method="GET">
            <div class="search-group">
                <label>Arrivée</label>
                <input type="date" name="check_in" value="<?= htmlspecialchars($search_check_in) ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="search-group">
                <label>Départ</label>
                <input type="date" name="check_out" value="<?= htmlspecialchars($search_check_out) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>
            <div class="search-group">
                <label>Personnes</label>
                <input type="number" name="guests" value="<?= $search_guests ?>" min="1" max="10" required>
            </div>
            <div class="search-group">
                <label>Type de chambre</label>
                <select name="type">
                    <option value="">Tous les types</option>
                    <?php if ($types_result): ?>
                        <?php while ($t = $types_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($t['type']) ?>" <?= $filter_type === $t['type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['type']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit">Rechercher</button>
        </form>
    </div>

    <h2>Chambres disponibles pour vos dates</h2>

    <?php if ($reserve_message !== ''): ?>
        <div class="alert <?= htmlspecialchars($reserve_message_type) ?>"><?= htmlspecialchars($reserve_message) ?></div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="room">
                <?php
                $images = [];
                if (!empty($row['all_images'])) {
                    $images = explode('|', $row['all_images']);
                } elseif (!empty($row['image_url'])) {
                    $images[] = $row['image_url'];
                }
                ?>
                
                <?php if (count($images) > 1): ?>
                    <div class="carousel" id="carousel-<?= $row['id'] ?>">
                        <button class="carousel-btn carousel-prev" onclick="moveCarousel(<?= $row['id'] ?>, -1)">&#10094;</button>
                        <div class="carousel-images" id="track-<?= $row['id'] ?>">
                            <?php foreach ($images as $img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="Chambre <?= htmlspecialchars($row['room_number']) ?>">
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-btn carousel-next" onclick="moveCarousel(<?= $row['id'] ?>, 1)">&#10095;</button>
                    </div>
                <?php elseif (count($images) === 1): ?>
                    <img src="<?= htmlspecialchars($images[0]) ?>" alt="Chambre <?= htmlspecialchars($row['room_number']) ?>" class="room-image">
                <?php else: ?>
                    <div class="room-image" style="display:flex; align-items:center; justify-content:center; color:#999;">Pas d'image</div>
                <?php endif; ?>

                <div class="room-header">
                    <h3>Chambre <?= htmlspecialchars($row['room_number']) ?></h3>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:5px;">
                        <span class="badge">Libre du <?= date('d/m', strtotime($search_check_in)) ?> au <?= date('d/m', strtotime($search_check_out)) ?></span>
                        <?php if ($row['review_count'] > 0): ?>
                            <span style="font-size:0.85rem; color:#d4af37;">
                                <?= str_repeat('⭐', round($row['avg_rating'])) ?> 
                                (<?= number_format($row['avg_rating'], 1) ?>/5)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="room-body">
                    <p><strong>Type :</strong> <?= htmlspecialchars($row['type']) ?></p>
                    <p><strong>Prix :</strong> <?= number_format($row['price_per_night'], 2, ',', ' ') ?> € / nuit</p>
                    <p><strong>Capacité :</strong> <?= (int)$row['capacity'] ?> personne<?= $row['capacity'] > 1 ? 's' : '' ?></p>
                    <p><strong>Services :</strong> WiFi, TV, Climatisation</p>
                </div>

                <form method="POST" action="ajouter_panier.php">
                    <input type="hidden" name="room_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="check_in" value="<?= htmlspecialchars($search_check_in) ?>">
                    <input type="hidden" name="check_out" value="<?= htmlspecialchars($search_check_out) ?>">
                    <input type="hidden" name="num_guests" value="<?= $search_guests ?>">
                    <input type="hidden" name="price_per_night" value="<?= $row['price_per_night'] ?>">
                    <input type="hidden" name="room_number" value="<?= htmlspecialchars($row['room_number']) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($row['type']) ?>">

                    <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669);">🛒 Ajouter au panier</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="empty">Aucune chambre ne correspond à vos critères pour ces dates.</p>
    <?php endif; ?>
</section>

<footer>
    <p>© 2026 - Hotel Booking System</p>
</footer>

<script>
    const carousels = {};
    function moveCarousel(id, direction) {
        if (!carousels[id]) carousels[id] = 0;
        const track = document.getElementById('track-' + id);
        const images = track.querySelectorAll('img').length;
        carousels[id] += direction;
        
        if (carousels[id] >= images) carousels[id] = 0;
        if (carousels[id] < 0) carousels[id] = images - 1;
        
        track.style.transform = `translateX(-${carousels[id] * 100}%)`;
    }
</script>

</body>
</html>