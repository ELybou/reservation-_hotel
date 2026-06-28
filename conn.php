<?php
session_start();

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "hotel";

$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    $conn = @new mysqli($host, $user, $pass);

    if ($conn->connect_error) {
        die("Connexion à MySQL impossible : " . $conn->connect_error);
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $conn->select_db($dbname);
}

$conn->set_charset("utf8");

// Table users
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Migration : ajouter colonne role si elle n'existe pas
$check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role && $check_role->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user','admin') DEFAULT 'user' AFTER password");
}

// Table rooms
$conn->query("CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacity INT NOT NULL DEFAULT 2,
    available BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(255) DEFAULT NULL
)");

// Migration : ajouter colonnes si elles n'existent pas
$check_price = $conn->query("SHOW COLUMNS FROM rooms LIKE 'price_per_night'");
if ($check_price && $check_price->num_rows === 0) {
    $conn->query("ALTER TABLE rooms ADD COLUMN price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER type");
}
$check_capacity = $conn->query("SHOW COLUMNS FROM rooms LIKE 'capacity'");
if ($check_capacity && $check_capacity->num_rows === 0) {
    $conn->query("ALTER TABLE rooms ADD COLUMN capacity INT NOT NULL DEFAULT 2 AFTER price_per_night");
}
$check_image = $conn->query("SHOW COLUMNS FROM rooms LIKE 'image_url'");
if ($check_image && $check_image->num_rows === 0) {
    $conn->query("ALTER TABLE rooms ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER available");
}

$check_cleaning = $conn->query("SHOW COLUMNS FROM rooms LIKE 'cleaning_status'");
if ($check_cleaning && $check_cleaning->num_rows === 0) {
    $conn->query("ALTER TABLE rooms ADD COLUMN cleaning_status ENUM('clean', 'dirty', 'cleaning', 'maintenance') DEFAULT 'clean' AFTER available");
}

// Table reservations
$conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    num_guests INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    reservation_date DATE DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Migration : ajouter colonnes check_in, check_out, num_guests, status
$check_checkin = $conn->query("SHOW COLUMNS FROM reservations LIKE 'check_in'");
if ($check_checkin && $check_checkin->num_rows === 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN check_in DATE DEFAULT NULL AFTER client_name");
    $conn->query("ALTER TABLE reservations ADD COLUMN check_out DATE DEFAULT NULL AFTER check_in");
    $conn->query("ALTER TABLE reservations ADD COLUMN num_guests INT NOT NULL DEFAULT 1 AFTER check_out");
    $conn->query("UPDATE reservations SET check_in = reservation_date, check_out = DATE_ADD(reservation_date, INTERVAL 1 DAY) WHERE check_in IS NULL AND reservation_date IS NOT NULL");
}

$check_status = $conn->query("SHOW COLUMNS FROM reservations LIKE 'status'");
if ($check_status && $check_status->num_rows === 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN status ENUM('pending', 'paid', 'cancelled') DEFAULT 'paid' AFTER num_guests");
}

$check_receipt = $conn->query("SHOW COLUMNS FROM reservations LIKE 'receipt_url'");
if ($check_receipt && $check_receipt->num_rows === 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN receipt_url VARCHAR(255) DEFAULT NULL AFTER status");
}

$check_pay_method = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_method'");
if ($check_pay_method && $check_pay_method->num_rows === 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER receipt_url");
}

// Table reviews
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Insérer les chambres par défaut avec prix et capacité
$conn->query("INSERT INTO rooms (room_number, type, price_per_night, capacity, available) SELECT '101', 'Simple', 75.00, 1, TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '101')");
$conn->query("INSERT INTO rooms (room_number, type, price_per_night, capacity, available) SELECT '102', 'Double', 120.00, 2, TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '102')");
$conn->query("INSERT INTO rooms (room_number, type, price_per_night, capacity, available) SELECT '103', 'Suite', 250.00, 3, TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '103')");
$conn->query("INSERT INTO rooms (room_number, type, price_per_night, capacity, available) SELECT '104', 'Familiale', 180.00, 5, FALSE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '104')");

// Mettre à jour les prix/capacités des chambres existantes qui ont un prix à 0
$conn->query("UPDATE rooms SET price_per_night = 75.00, capacity = 1 WHERE room_number = '101' AND price_per_night = 0");
$conn->query("UPDATE rooms SET price_per_night = 120.00, capacity = 2 WHERE room_number = '102' AND price_per_night = 0");
$conn->query("UPDATE rooms SET price_per_night = 250.00, capacity = 3 WHERE room_number = '103' AND price_per_night = 0");
$conn->query("UPDATE rooms SET price_per_night = 180.00, capacity = 5 WHERE room_number = '104' AND price_per_night = 0");

// Ajouter des images par défaut si elles sont vides
$conn->query("UPDATE rooms SET image_url = 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=800&q=80' WHERE room_number = '101' AND (image_url IS NULL OR image_url = '')");
$conn->query("UPDATE rooms SET image_url = 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=800&q=80' WHERE room_number = '102' AND (image_url IS NULL OR image_url = '')");
$conn->query("UPDATE rooms SET image_url = 'https://images.unsplash.com/photo-1582719478250-c894e4dc240e?auto=format&fit=crop&w=800&q=80' WHERE room_number = '103' AND (image_url IS NULL OR image_url = '')");
$conn->query("UPDATE rooms SET image_url = 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=800&q=80' WHERE room_number = '104' AND (image_url IS NULL OR image_url = '')");

// Table room_images
$conn->query("CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
)");

// Migration des images existantes (de rooms.image_url vers room_images)
$check_migrated_images = $conn->query("SELECT COUNT(*) as cnt FROM room_images");
if ($check_migrated_images && $check_migrated_images->fetch_assoc()['cnt'] == 0) {
    // Si la table est vide, on migre les images de la table rooms
    $rooms_with_images = $conn->query("SELECT id, image_url FROM rooms WHERE image_url IS NOT NULL AND image_url != ''");
    if ($rooms_with_images && $rooms_with_images->num_rows > 0) {
        $insert_img = $conn->prepare("INSERT INTO room_images (room_id, url) VALUES (?, ?)");
        while ($r = $rooms_with_images->fetch_assoc()) {
            $insert_img->bind_param("is", $r['id'], $r['image_url']);
            $insert_img->execute();
        }
        $insert_img->close();
    }
}

// Ajouter des images par défaut supplémentaires pour la démo si la table room_images n'a que 4 images
if ($check_migrated_images && $conn->query("SELECT COUNT(*) as cnt FROM room_images")->fetch_assoc()['cnt'] <= 4) {
    $r101 = $conn->query("SELECT id FROM rooms WHERE room_number = '101'")->fetch_assoc();
    $r102 = $conn->query("SELECT id FROM rooms WHERE room_number = '102'")->fetch_assoc();
    
    if ($r101) {
        $conn->query("INSERT INTO room_images (room_id, url) SELECT " . $r101['id'] . ", 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?auto=format&fit=crop&w=800&q=80' WHERE NOT EXISTS (SELECT 1 FROM room_images WHERE room_id = " . $r101['id'] . " AND url LIKE '%15963945%')");
    }
    if ($r102) {
        $conn->query("INSERT INTO room_images (room_id, url) SELECT " . $r102['id'] . ", 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=800&q=80' WHERE NOT EXISTS (SELECT 1 FROM room_images WHERE room_id = " . $r102['id'] . " AND url LIKE '%15786830%')");
    }
}

// Créer un compte admin par défaut s'il n'existe pas
$check_admin = $conn->query("SELECT id FROM users WHERE email = 'admin@hotel.com'");
if ($check_admin && $check_admin->num_rows === 0) {
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (full_name, email, password, role) VALUES ('Administrateur', 'admin@hotel.com', '$admin_pass', 'admin')");
}
?>