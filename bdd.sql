CREATE DATABASE hotel;

USE hotel;

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10),
    type VARCHAR(50),
    available BOOLEAN DEFAULT TRUE
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT,
    client_name VARCHAR(100),
    reservation_date DATE,
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);