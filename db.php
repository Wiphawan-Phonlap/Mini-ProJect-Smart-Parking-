<?php
$host = "localhost:3307"; 
$user = "root";
$pass = ""; 
$db   = "smart_parking";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . mysqli_connect_error());
}
// ตั้งค่าภาษาไทยให้รองรับการเก็บชื่อรถหรือสถานะภาษาไทย
mysqli_set_charset($conn, "utf8mb4"); 
?>