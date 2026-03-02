<?php
session_start();
// --- ดึงไฟล์ config.php มาใช้ เพราะไฟล์นั้นตั้งค่าพอร์ต 3307 ไว้ถูกต้องแล้ว ---
include 'config.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ใช้ mysqli_real_escape_string เพื่อความปลอดภัย
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = mysqli_real_escape_string($conn, $_POST['password']);
    
    // ตรวจสอบข้อมูลในตาราง users
    $sql = "SELECT * FROM users WHERE username='$user' AND password='$pass'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['login_user'] = $user;
        header("location: index.php"); 
        exit();
    } else {
        $error = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Login - Smart Parking</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background: #2c3e50; 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0;
        }
        .login-box { 
            background: white; padding: 40px; border-radius: 15px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.3); 
            width: 320px; text-align: center;
        }
        input { 
            display: block; width: 100%; margin: 15px 0; padding: 12px; 
            border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box;
        }
        button { 
            width: 100%; padding: 12px; background: #3498db; color: white; 
            border: none; border-radius: 8px; cursor: pointer; font-weight: bold;
        }
        button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔒 เข้าสู่ระบบ</h2>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <?php if(isset($error)) echo "<p style='color:red; font-size:0.8em;'>$error</p>"; ?>
        </form>
    </div>
</body>
</html>