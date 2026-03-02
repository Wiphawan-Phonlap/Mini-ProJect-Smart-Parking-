<?php 
session_start();
if(!isset($_SESSION['login_user'])){
    header("location: login.php");
    exit();
}
include 'config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Parking Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #f4f7f6; text-align: center; padding: 20px; color: #333; }
        .user-bar { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .logout-btn { background: #e74c3c; color: white; text-decoration: none; padding: 5px 15px; border-radius: 5px; font-size: 0.8em; }
        .grid { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 280px; }
        .btn { padding: 12px 20px; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; min-width: 100px; margin: 5px; transition: 0.2s; }
        .btn-blue { background: #3498db; }
        .btn-yellow { background: #f1c40f; color: #333; }
        .btn-gray { background: #95a5a6; }
        .status-badge { padding: 5px 15px; border-radius: 20px; font-size: 0.9em; font-weight: bold; }
        canvas { max-height: 300px; }
    </style>
</head>
<body>

    <div class="user-bar">
        <span>👤 ผู้ใช้งาน: <b><?php echo $_SESSION['login_user']; ?></b></span>
        <a href="logout.php" class="logout-btn">ออกจากระบบ 🚪</a>
    </div>

    <h1>🅿️ Smart Parking Control Center</h1>

    <div class="grid">
        <div class="card">
            <h3>🚗 สถานะที่จอด</h3>
            <h1 id="car_count" style="font-size: 60px; color: #2ecc71; margin: 10px 0;">--/2</h1>
            <p id="car_status">รอข้อมูล...</p>
        </div>

        <div class="card">
            <h3>🚧 ไม้กั้นทางเข้า</h3>
            <p>สถานะจริง: <span id="gate_badge" class="status-badge">...</span></p>
            <div style="margin-top: 20px;">
                <button onclick="sendCmd('gate', 1)" class="btn btn-blue">สั่งเปิด</button>
                <button onclick="sendCmd('gate', 0)" class="btn btn-gray">สั่งปิด</button>
            </div>
        </div>

        <div class="card">
            <h3>💡 ระบบไฟถนน</h3>
            <p>สถานะคำสั่ง: <b id="light_txt">...</b></p>
            <div style="margin-top: 20px;">
                <button onclick="sendCmd('light', 1)" class="btn btn-yellow">เปิดไฟ</button>
                <button onclick="sendCmd('light', 0)" class="btn btn-gray">ปิดไฟ</button>
            </div>
        </div>
    </div>

    <div class="card" style="width: 80%; max-width: 900px; margin: 30px auto;">
        <h3>📊 กราฟระดับแสง (Real-time)</h3>
        <canvas id="ldrChart"></canvas>
    </div>

    <script>
        // ฟังก์ชันส่งคำสั่งไปที่ api.php
        function sendCmd(type, val) {
            let fd = new FormData();
            fd.append('toggle_type', type);
            fd.append('value', val);
            fetch('api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => { updateDashboard(); })
            .catch(err => console.error("Error sending command:", err));
        }

        // ตั้งค่ากราฟ Chart.js
        const ctx = document.getElementById('ldrChart').getContext('2d');
        
        // สร้าง Gradient ให้พื้นหลังกราฟ
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(241, 196, 15, 0.5)');
        gradient.addColorStop(1, 'rgba(241, 196, 15, 0)');

        const ldrChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], 
                datasets: [{ 
                    label: 'ความเข้มแสง (LDR)', 
                    data: [], 
                    borderColor: '#f1c40f', 
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,           // <--- จุดที่ทำให้กราฟ Smooth (0.0 - 1.0)
                    borderWidth: 3,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#f1c40f'
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1000, easing: 'easeOutQuart' },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#eee' } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // ฟังก์ชันดึงข้อมูลจาก api.php มาอัปเดตหน้าเว็บ
        function updateDashboard() {
            fetch('api.php')
                .then(res => res.json())
                .then(data => {
                    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    
                    // อัปเดตกราฟ
                    ldrChart.data.labels.push(now);
                    ldrChart.data.datasets[0].data.push(data.ldr);

                    // เก็บข้อมูลไว้โชว์แค่ 12 จุดล่าสุด (เพื่อความสวยงาม)
                    if (ldrChart.data.labels.length > 12) { 
                        ldrChart.data.labels.shift(); 
                        ldrChart.data.datasets[0].data.shift(); 
                    }
                    ldrChart.update('none'); // อัปเดตแบบไม่ต้องโหลดอนิเมชั่นใหม่ทุกวินาที

                    // อัปเดตตัวเลขและข้อความสถานะ
                    document.getElementById('car_count').innerText = data.parked + "/2";
                    document.getElementById('car_status').innerText = (data.parked >= 2) ? "🔴 ที่จอดเต็ม" : "🟢 มีที่ว่าง";
                    
                    const badge = document.getElementById('gate_badge');
                    badge.innerText = (data.gate_physical == 1) ? "🔓 เปิดอยู่" : "🔒 ปิดสนิท";
                    badge.style.background = (data.gate_physical == 1) ? "#d5f5e3" : "#fadbd8";

                    document.getElementById('light_txt').innerText = (data.light == 1) ? "ON (Manual)" : "OFF / AUTO";
                })
                .catch(err => console.error("Error fetching data:", err));
        }

        // ตั้งเวลาดึงข้อมูลใหม่ทุกๆ 2 วินาที
        setInterval(updateDashboard, 2000);
        updateDashboard(); // เรียกใช้ครั้งแรกทันทีที่โหลดหน้า
    </script>
</body>
</html>