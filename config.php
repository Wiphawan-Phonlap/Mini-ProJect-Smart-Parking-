<?php
$host = "localhost:3307"; // พอร์ต 3307 ตาม XAMPP ของคุณ
$user = "root";
$pass = ""; 
$db   = "smart_parking";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>