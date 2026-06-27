CREATE DATABASE IF NOT EXISTS hotel;
USE hotel;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacity INT NOT NULL DEFAULT 2,
    available BOOLEAN DEFAULT TRUE,
    cleaning_status ENUM('clean', 'dirty', 'cleaning', 'maintenance') DEFAULT 'clean',
    image_url VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS reservations (
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
);

CREATE TABLE IF NOT EXISTS reviews (
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
);

CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

INSERT INTO rooms (room_number, type, price_per_night, capacity, available) VALUES
('101', 'Simple', 75.00, 1, TRUE),
('102', 'Double', 120.00, 2, TRUE),
('103', 'Suite', 250.00, 3, TRUE),
('104', 'Familiale', 180.00, 5, FALSE);