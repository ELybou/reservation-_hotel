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

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL,
    type VARCHAR(50) NOT NULL,
    available BOOLEAN DEFAULT TRUE
)");

$conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    reservation_date DATE NOT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$conn->query("INSERT INTO rooms (room_number, type, available) SELECT '101', 'Simple', TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '101')");
$conn->query("INSERT INTO rooms (room_number, type, available) SELECT '102', 'Double', TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '102')");
$conn->query("INSERT INTO rooms (room_number, type, available) SELECT '103', 'Suite', TRUE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '103')");
$conn->query("INSERT INTO rooms (room_number, type, available) SELECT '104', 'Familiale', FALSE WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE room_number = '104')");
?>