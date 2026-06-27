<?php
$conn = new mysqli("localhost", "root", "", "hotel");

if ($conn->connect_error) {
    die("Connexion échouée");
}
?>