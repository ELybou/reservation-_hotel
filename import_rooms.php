<?php
require_once "conn.php";

$assets_dir = __DIR__ . '/assets';
$files = scandir($assets_dir);

$prefix_map = [
    'chambre' => ['type' => 'Double', 'price' => 120.00, 'capacity' => 2],
    'suite' => ['type' => 'Suite', 'price' => 250.00, 'capacity' => 3],
    'conf' => ['type' => 'Salle de conférence', 'price' => 500.00, 'capacity' => 50],
    't' => ['type' => 'Tente', 'price' => 50.00, 'capacity' => 2]
];

$added = 0;

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

    $matched_prefix = '';
    foreach (array_keys($prefix_map) as $prefix) {
        if (strpos(strtolower($file), $prefix) === 0) {
            $matched_prefix = $prefix;
            break;
        }
    }

    if ($matched_prefix) {
        $info = $prefix_map[$matched_prefix];
        
        // Extraire le numéro
        preg_match('/\d+/', $file, $matches);
        $num = isset($matches[0]) ? $matches[0] : rand(1000, 9999);
        
        $room_number = strtoupper($matched_prefix) . '-' . $num;
        $image_url = 'assets/' . $file;
        
        // Vérifier si la chambre existe déjà
        $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
        $stmt->bind_param("s", $room_number);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            // Insérer la chambre
            $insert = $conn->prepare("INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES (?, ?, ?, ?, 1, ?)");
            $insert->bind_param("ssdis", $room_number, $info['type'], $info['price'], $info['capacity'], $image_url);
            $insert->execute();
            $room_id = $insert->insert_id;
            
            $insert_img = $conn->prepare("INSERT INTO room_images (room_id, url) VALUES (?, ?)");
            $insert_img->bind_param("is", $room_id, $image_url);
            $insert_img->execute();
            
            echo "Added $room_number with image $image_url\n";
            $added++;
        }
    }
}

echo "Total rooms added: $added\n";
?>
