<?php
include 'config.php';
header('Content-Type: application/json');

// --- 1. รับค่าจากหน้า Web Dashboard (ปุ่มกดสั่งงาน) ---
if (isset($_POST['toggle_type']) && isset($_POST['value'])) {
    $type = $_POST['toggle_type']; 
    $val = (int)$_POST['value'];   
    $update_cmd = "";
    $msg = "";

    if ($type === 'gate') {
        $update_cmd = "UPDATE device_status SET gate_cmd=$val WHERE id=1";
        $msg = ($val == 1) ? "🔓 สั่งเปิดไม้กั้นจากหน้าเว็บ" : "🔒 สั่งปิดไม้กั้นจากหน้าเว็บ";
    } elseif ($type === 'light') {
        $update_cmd = "UPDATE device_status SET light_cmd=$val WHERE id=1";
        $msg = ($val == 1) ? "💡 สั่งเปิดไฟจากหน้าเว็บ" : "🌑 สั่งปิดไฟ ";
    }

    if ($update_cmd != "") {
        mysqli_query($conn, $update_cmd);
        
        // --- ส่ง Telegram ---
        $botToken = "8077783059:AAHLq-_w_EVlsn3xREiUROT7_c6KPKkWYsY";
        $chatId = "-1003546434420";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($msg);
        $ctx = stream_context_create(['http' => ['timeout' => 2]]); 
        @file_get_contents($url, false, $ctx);
        
        // *** ห้ามใส่ exit; ตรงนี้เพื่อให้โค้ดไหลลงไปทำส่วนที่ 3 เพื่อตอบกลับ JSON ***
    }
}

// --- 2. รับค่าจาก ESP32 (เพื่อ Update สถานะหน้าจอ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parked'])) {
    $parked = (int)$_POST['parked'];
    $ldr = (int)$_POST['ldr'];
    $g_stat = (int)$_POST['gate_status'];

    $update_sql = "UPDATE device_status SET 
                   slot_occupied = $parked, 
                   ldr_value = $ldr, 
                   gate_status_physical = $g_stat,
                   last_update = NOW() 
                   WHERE id = 1";
    mysqli_query($conn, $update_sql);

    $insert_sql = "INSERT INTO parking_logs (parked_count, ldr_val) 
                   VALUES ($parked, $ldr)";
    mysqli_query($conn, $insert_sql);
    // สำหรับ POST จากบอร์ด ไม่ต้องดึงข้อมูลกลับก็ได้
}

// --- 3. ส่งข้อมูลปัจจุบันกลับไป (Output JSON ที่สำคัญมาก!) ---
$res = mysqli_query($conn, "SELECT * FROM device_status WHERE id=1");
$row = mysqli_fetch_assoc($res);

echo json_encode([
    "gate" => (int)$row['gate_cmd'],
    "light" => (int)$row['light_cmd'],
    "ldr" => (int)$row['ldr_value'],
    "parked" => (int)$row['slot_occupied'],
    "gate_physical" => (int)$row['gate_status_physical']
]);
?>