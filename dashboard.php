<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";

// Ambil data pelanggaran untuk chart
$chartData = [];
$sql = "SELECT date, COUNT(*) as total 
        FROM violation 
        GROUP BY date 
        ORDER BY date ASC";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()){
    $chartData[] = $row;
}

// Ambil capture terbaru dari tabel capture
$latest = $conn->query("SELECT image FROM capture ORDER BY timestamp DESC LIMIT 1");
$latestImage = $latest->num_rows > 0 ? $latest->fetch_assoc()['image'] : null;

// Ambil statistik untuk dashboard cards
$totalViolations = $conn->query("SELECT COUNT(*) as total FROM violation")->fetch_assoc()['total'];
$todayViolations = $conn->query("SELECT COUNT(*) as total FROM violation WHERE DATE(date) = CURDATE()")->fetch_assoc()['total'];
$totalCaptures = $conn->query("SELECT COUNT(*) as total FROM capture")->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - CTC Monitoring Employee</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --light: #f8fafc;
        --dark: #1e293b;
        --sidebar-width: 240px;
        --border-radius: 8px;
        --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    body {
        background-color: #f1f5f9;
        color: #334155;
        overflow-x: hidden;
    }

    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar Styles */
    .sidebar {
        width: var(--sidebar-width);
        background: white;
        color: var(--dark);
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 100;
        box-shadow: var(--box-shadow);
        border-right: 1px solid #e2e8f0;
    }

    .sidebar-header {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }

    .sidebar-header h2 {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary);
    }

    .sidebar ul {
        list-style: none;
        padding: 15px 0;
    }

    .sidebar ul li {
        margin: 5px 0;
    }

    .sidebar ul li a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--secondary);
        text-decoration: none;
        transition: all 0.2s;
        font-weight: 500;
    }

    .sidebar ul li a i {
        margin-right: 12px;
        font-size: 18px;
        width: 24px;
        text-align: center;
    }

    .sidebar ul li a:hover, 
    .sidebar ul li a.active {
        background: #f1f5f9;
        color: var(--primary);
    }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        padding: 25px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-header h1 {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--dark);
    }

    .user-profile {
        display: flex;
        align-items: center;
        background: white;
        padding: 8px 15px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .user-profile img {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        margin-right: 10px;
        object-fit: cover;
    }

    /* Dashboard Cards */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .card {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .card-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
        background: #eff6ff;
        color: var(--primary);
    }

    .card-title {
        font-size: 0.9rem;
        color: var(--secondary);
        font-weight: 500;
    }

    .card-value {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--dark);
    }

    .card-violations .card-icon {
        background: #fef2f2;
        color: var(--danger);
    }

    .card-today .card-icon {
        background: #ecfdf5;
        color: var(--success);
    }

    .card-captures .card-icon {
        background: #eff6ff;
        color: var(--primary);
    }

    /* Clock Widget */
    .clock-widget {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .clock-widget h3 {
        margin-bottom: 15px;
        color: var(--dark);
        font-weight: 500;
        font-size: 1.1rem;
    }

    #clock {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--primary);
        font-family: 'Courier New', monospace;
        background: #f8fafc;
        padding: 15px;
        border-radius: var(--border-radius);
        text-align: center;
        border: 1px solid #e2e8f0;
    }

    /* Camera Feed Section */
    .camera-section {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .camera-feed {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .camera-feed h3 {
        margin-bottom: 15px;
        color: var(--dark);
        font-weight: 500;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .camera-feed h3 i {
        margin-right: 10px;
        color: var(--primary);
    }

    .live-feed {
        position: relative;
        margin-bottom: 20px;
        text-align: center;
    }

    .live-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--danger);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
    }

    .live-indicator::before {
        content: '';
        width: 6px;
        height: 6px;
        background: white;
        border-radius: 50%;
        margin-right: 5px;
    }

    .camera-feed img {
        width: 100%;
        max-width: 640px;
        border-radius: var(--border-radius);
        border: 1px solid #e2e8f0;
    }

    #captureBtn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        font-size: 0.95rem;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: background 0.2s;
        display: inline-flex;
        align-items: center;
        font-weight: 500;
    }

    #captureBtn i {
        margin-right: 8px;
    }

    #captureBtn:hover {
        background: var(--primary-dark);
    }

    #captureBtn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    #status {
        margin: 15px 0;
        font-weight: 500;
        text-align: center;
    }

    #captureResult {
        margin-top: 20px;
        text-align: center;
    }

    #captureResult img {
        max-width: 100%;
        max-height: 450px; /* Added max-height to control size */
        border-radius: var(--border-radius);
        border: 1px solid #e2e8f0;
        object-fit: contain; /* Ensure proper aspect ratio */
    }

    /* Chart Section */
    .chart-container {
        background: white;
        border-radius: var(--border-radius);
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        height: 350px; /* Fixed height for chart */
    }

    .chart-container h3 {
        margin-bottom: 20px;
        color: var(--dark);
        font-weight: 500;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .chart-container h3 i {
        margin-right: 10px;
        color: var(--primary);
    }

    /* Notifications */
    #notification-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        max-width: 320px;
    }

    .notif {
        background: white;
        border-radius: var(--border-radius);
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: var(--box-shadow);
        display: flex;
        align-items: center;
        border: 1px solid #e2e8f0;
        border-left: 3px solid var(--primary);
    }

    .notif-content {
        flex: 1;
    }

    .notif-content p {
        margin-bottom: 5px;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .notif-content small {
        color: var(--secondary);
        font-size: 0.8rem;
    }

    .notif img {
        width: 50px;
        height: 50px;
        border-radius: var(--border-radius);
        margin-left: 15px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .sidebar {
            width: 70px;
            overflow: visible;
        }
        
        .sidebar-header h2, .sidebar ul li a span {
            display: none;
        }
        
        .sidebar ul li a {
            justify-content: center;
            padding: 15px;
        }
        
        .sidebar ul li a i {
            margin-right: 0;
            font-size: 20px;
        }
        
        .main-content {
            margin-left: 70px;
        }
    }

    @media (max-width: 768px) {
        .dashboard-cards {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .user-profile {
            margin-top: 15px;
        }
        
        #clock {
            font-size: 1.5rem;
        }
        
        .chart-container {
            height: 300px;
        }
        
        #captureResult img {
            max-height: 250px; /* Smaller on mobile */
        }
    }

    @media (max-width: 576px) {
        .sidebar {
            width: 0;
        }
        
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .menu-toggle {
            display: block;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--box-shadow);
        }
        
        .chart-container {
            height: 250px;
            padding: 15px;
        }
        
        #captureResult img {
            max-height: 200px; /* Even smaller on very small screens */
        }
    }
</style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>CTC Monitoring</h2>
        </div>
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="history.php"><i class="fas fa-history"></i> <span>History</span></a></li>
            <li><a href="admin_panel.php"><i class="fas fa-user-cog"></i> <span>Admin</span></a></li>
            <li><a href="violation.php"><i class="fas fa-exclamation-triangle"></i> <span>Violations</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <div class="user-profile">
                <div class="avatar">
                    <i class="fas fa-user-circle" style="font-size: 32px; color: #64748b; margin-right: 10px;"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Sem</div>
                    <div style="font-size: 12px; color: #64748b;">manaluyudhasamuel@gmail.com</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card card-violations">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="card-title">Total Violations</div>
                        <div class="card-value"><?php echo $totalViolations; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card card-today">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="card-title">Today's Violations</div>
                        <div class="card-value"><?php echo $todayViolations; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card card-captures">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div>
                        <div class="card-title">Total Captures</div>
                        <div class="card-value"><?php echo $totalCaptures; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clock Widget -->
        <div class="clock-widget">
            <h3>Current Time</h3>
            <div id="clock"></div>
        </div>

        <!-- Camera Feed Section -->
        <div class="camera-section">
            <div class="camera-feed">
                <h3><i class="fas fa-video"></i> Live Camera Feed</h3>
                <div class="live-feed">
                    <span class="live-indicator">LIVE</span>
                    <img src="http://10.39.183.57:81/stream" alt="Live Stream">
                </div>
                <div style="text-align: center;">
                    <button id="captureBtn"><i class="fas fa-camera"></i> Capture Now</button>
                </div>
                <p id="status"></p>
                <div id="captureResult"></div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-bar"></i> Violation Trends</h3>
            <canvas id="violationChart"></canvas>
        </div>
    </main>
</div>

<div id="notification-container"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Clock function
function updateClock() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth()+1).padStart(2,'0');
    const day = String(now.getDate()).padStart(2,'0');
    const hours = String(now.getHours()).padStart(2,'0');
    const minutes = String(now.getMinutes()).padStart(2,'0');
    const seconds = String(now.getSeconds()).padStart(2,'0');

    const timeString = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    document.getElementById('clock').innerText = timeString;
}
setInterval(updateClock, 1000);
updateClock();

// Capture functionality
(function(){
    const btn = document.getElementById("captureBtn");
    const statusEl = document.getElementById("status");
    const resultEl = document.getElementById("captureResult");

    let autoBusy = false;

    async function doCapture(options){
        const { isManual } = options || { isManual: false };
        if (!isManual && autoBusy) return;
        if (!isManual) autoBusy = true;
        if (isManual) {
            statusEl.innerText = "Processing capture...";
            statusEl.style.color = "#2563eb";
            resultEl.innerHTML = '';
            btn.disabled = true;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000);
        try {
            const res = await fetch("http://localhost/employee_monitoring/capture.php", { signal: controller.signal });
            const text = await res.text();
            const body = text.trim();
            if (!body || body.toUpperCase().startsWith('ERROR') || !/^[A-Za-z0-9_.-]+\.(jpg|jpeg|png)$/i.test(body)) {
                if (isManual) {
                    statusEl.innerText = body ? body : 'Capture failed: empty response.';
                    statusEl.style.color = "#ef4444";
                }
                return;
            }
            const filename = body;
            const fileUrl = "http://localhost/employee_monitoring/uploads/" + filename;
            if (isManual) {
                statusEl.innerText = "Capture successful";
                statusEl.style.color = "#10b981";
                resultEl.innerHTML = `<img src="${fileUrl}?t=${Date.now()}" alt="Capture Result">`;
            } else {
                const stamp = new Date().toLocaleTimeString();
                statusEl.innerText = `Auto capture at ${stamp}`;
                statusEl.style.color = "#64748b";
            }
        } catch (error) {
            if (isManual) {
                statusEl.innerText = "Capture failed: " + (error.name === 'AbortError' ? 'Timeout' : error.message);
                statusEl.style.color = "#ef4444";
            }
        } finally {
            clearTimeout(timeoutId);
            if (isManual) btn.disabled = false;
            if (!isManual) autoBusy = false;
        }
    }

    btn.addEventListener("click", function() { doCapture({ isManual: true }); });

    // Auto capture every 120 seconds
    setInterval(() => { doCapture({ isManual: false }); }, 120000);
})();

// Chart.js - Clean and professional design
const ctx = document.getElementById('violationChart').getContext('2d');
const chartData = {
    labels: <?php echo json_encode(array_column($chartData, 'date')); ?>,
    datasets: [{
        label: 'Violations',
        data: <?php echo json_encode(array_column($chartData, 'total')); ?>,
        backgroundColor: '#2563eb',
        borderColor: '#1d4ed8',
        borderWidth: 1,
        borderRadius: 4,
        barPercentage: 0.6,
    }]
};

new Chart(ctx, {
    type: 'bar',
    data: chartData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0'
                },
                ticks: {
                    stepSize: 1,
                    color: '#64748b'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#64748b',
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});

// Notifications
function checkNotification(){
    $.get('check_notification.php', function(data){
        let notifications = JSON.parse(data);
        notifications.forEach(function(notif){
            let $notif = $(`
                <div class="notif">
                    <div class="notif-content">
                        <p>New Capture Detected</p>
                        <small>${new Date().toLocaleTimeString()}</small>
                    </div>
                    <img src="uploads/uploads_processing/${notif.image}" alt="Capture">
                </div>
            `);
            $('#notification-container').append($notif);
            setTimeout(function(){
                $notif.fadeOut(500, function(){ $(this).remove(); });
            }, 5000);
        });
    });
}
setInterval(checkNotification, 10000);
</script>
</body>
</html>