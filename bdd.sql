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
-- Additional rooms from assets
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-1', 'Double', 120, 2, TRUE, 'assets/chambre1.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-10', 'Double', 120, 2, TRUE, 'assets/chambre10.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-11', 'Double', 120, 2, TRUE, 'assets/chambre11.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-12', 'Double', 120, 2, TRUE, 'assets/chambre12.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-2', 'Double', 120, 2, TRUE, 'assets/chambre2.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-3', 'Double', 120, 2, TRUE, 'assets/chambre3.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-4', 'Double', 120, 2, TRUE, 'assets/chambre4.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-5', 'Double', 120, 2, TRUE, 'assets/chambre5.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-6', 'Double', 120, 2, TRUE, 'assets/chambre6.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-7', 'Double', 120, 2, TRUE, 'assets/chambre7.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-8', 'Double', 120, 2, TRUE, 'assets/chambre8.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CHAMBRE-9', 'Double', 120, 2, TRUE, 'assets/chambre9.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-1', 'Salle de conférence', 500, 50, TRUE, 'assets/conf1.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-10', 'Salle de conférence', 500, 50, TRUE, 'assets/conf10.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-11', 'Salle de conférence', 500, 50, TRUE, 'assets/conf11.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-12', 'Salle de conférence', 500, 50, TRUE, 'assets/conf12.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-2', 'Salle de conférence', 500, 50, TRUE, 'assets/conf2.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-3', 'Salle de conférence', 500, 50, TRUE, 'assets/conf3.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-4', 'Salle de conférence', 500, 50, TRUE, 'assets/conf4.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-5', 'Salle de conférence', 500, 50, TRUE, 'assets/conf5.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-6', 'Salle de conférence', 500, 50, TRUE, 'assets/conf6.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-7', 'Salle de conférence', 500, 50, TRUE, 'assets/conf7.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-8', 'Salle de conférence', 500, 50, TRUE, 'assets/conf8.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('CONF-9', 'Salle de conférence', 500, 50, TRUE, 'assets/conf9.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-1', 'Suite', 250, 3, TRUE, 'assets/suite1.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-10', 'Suite', 250, 3, TRUE, 'assets/suite10.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-11', 'Suite', 250, 3, TRUE, 'assets/suite11.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-12', 'Suite', 250, 3, TRUE, 'assets/suite12.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-2', 'Suite', 250, 3, TRUE, 'assets/suite2.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-3', 'Suite', 250, 3, TRUE, 'assets/suite3.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-4', 'Suite', 250, 3, TRUE, 'assets/suite4.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-5', 'Suite', 250, 3, TRUE, 'assets/suite5.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-6', 'Suite', 250, 3, TRUE, 'assets/suite6.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-7', 'Suite', 250, 3, TRUE, 'assets/suite7.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-8', 'Suite', 250, 3, TRUE, 'assets/suite8.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('SUITE-9', 'Suite', 250, 3, TRUE, 'assets/suite9.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-1', 'Tente', 50, 2, TRUE, 'assets/t1.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-10', 'Tente', 50, 2, TRUE, 'assets/t10.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-11', 'Tente', 50, 2, TRUE, 'assets/t11.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-12', 'Tente', 50, 2, TRUE, 'assets/t12.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-2', 'Tente', 50, 2, TRUE, 'assets/t2.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-3', 'Tente', 50, 2, TRUE, 'assets/t3.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-4', 'Tente', 50, 2, TRUE, 'assets/t4.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-5', 'Tente', 50, 2, TRUE, 'assets/t5.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-6', 'Tente', 50, 2, TRUE, 'assets/t6.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-7', 'Tente', 50, 2, TRUE, 'assets/t7.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-8', 'Tente', 50, 2, TRUE, 'assets/t8.jpg');
INSERT INTO rooms (room_number, type, price_per_night, capacity, available, image_url) VALUES ('T-9', 'Tente', 50, 2, TRUE, 'assets/t9.jpg');
