<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";

// Filter berdasarkan tanggal (opsional)
$filterDate = isset($_GET['date']) ? $_GET['date'] : null;

$sql = "SELECT *,
    COALESCE(created_at, `timestamp`) AS sort_time
    FROM capture";
if ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $dateEsc = $conn->real_escape_string($filterDate);
    $sql .= " WHERE DATE(COALESCE(created_at, `timestamp`)) = '$dateEsc'";
}
$sql .= " ORDER BY sort_time DESC";
$res = $conn->query($sql);

// Kumpulkan data dari DB ke array
$dbItems = [];
if ($res && $res->num_rows > 0) {
	while($row = $res->fetch_assoc()){
		$dbItems[] = [
			'image' => isset($row['image']) ? (string)$row['image'] : '',
			'time' => isset($row['created_at']) && $row['created_at'] ? $row['created_at'] : (isset($row['timestamp']) ? $row['timestamp'] : null)
		];
	}
}

// Tambahkan juga dari esp32cam_images jika ada
$altItems = [];
if ($conn->query("SHOW TABLES LIKE 'esp32cam_images'")->num_rows > 0) {
    $sqlAlt = "SELECT image_name AS image, `timestamp` AS time FROM esp32cam_images";
    if ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
        $dateEsc = $conn->real_escape_string($filterDate);
        $sqlAlt .= " WHERE DATE(`timestamp`) = '$dateEsc'";
    }
    $sqlAlt .= " ORDER BY `timestamp` DESC";
    if ($r2 = $conn->query($sqlAlt)) {
        while($row = $r2->fetch_assoc()){
            $altItems[] = [ 'image' => (string)$row['image'], 'time' => $row['time'] ];
        }
        $r2->close();
    }
}

// Fallback: tambahkan file dari filesystem (uploads & uploads_processing) agar tetap tampil meskipun DB insert gagal
function collectFilesFromDir($dirPath){
	$items = [];
	if (is_dir($dirPath)) {
		$files = scandir($dirPath);
		foreach($files as $f){
			if ($f === '.' || $f === '..') continue;
			$lower = strtolower($f);
			if (!preg_match('/\.(jpg|jpeg|png)$/i', $lower)) continue;
			$full = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR . $f;
			$mtime = @filemtime($full);
			$items[] = [ 'image' => $f, 'time' => $mtime ? date('Y-m-d H:i:s', $mtime) : null ];
		}
	}
	return $items;
}

$fsItems = array_merge(
	collectFilesFromDir(__DIR__ . '/uploads'),
	collectFilesFromDir(__DIR__ . '/uploads_processing')
);

// Gabungkan DB + FS (hindari duplikasi berdasarkan nama file)
$byName = [];
foreach ($dbItems as $it) { if (!empty($it['image'])) { $byName[$it['image']] = $it; } }
foreach ($altItems as $it) { if (!empty($it['image']) && !isset($byName[$it['image']])) { $byName[$it['image']] = $it; } }
foreach ($fsItems as $it) {
    if (empty($it['image'])) continue;
    if (!isset($byName[$it['image']])) {
        $byName[$it['image']] = $it; // tambahkan jika belum ada
    } else {
        // jika entry sudah ada tapi belum punya time, ambil time dari filesystem
        if (empty($byName[$it['image']]['time']) && !empty($it['time'])) {
            $byName[$it['image']]['time'] = $it['time'];
        }
    }
}

// Terapkan filter tanggal jika ada (kecuali file yang diawali 'detected_')
if ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
	$byName = array_filter($byName, function($it) use ($filterDate) {
		$name = isset($it['image']) ? (string)$it['image'] : '';
		if (strpos($name, 'detected_') === 0) return true; // selalu tampil
		if (!$it['time']) return false;
		return strpos($it['time'], $filterDate) === 0; // prefix match YYYY-mm-dd
	});
}

// Urutkan desc by time
$dbSorted = array_values($byName);
usort($dbSorted, function($a,$b){
	$ta = strtotime($a['time'] ?: '1970-01-01 00:00:00');
	$tb = strtotime($b['time'] ?: '1970-01-01 00:00:00');
	return $tb <=> $ta;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History Capture - CTC Monitoring Employee</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    /* Filter Section */
    .filter-section {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .filter-section form {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .filter-section label {
        font-weight: 500;
        color: var(--dark);
    }

    .filter-section input[type="date"] {
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: var(--border-radius);
        font-size: 0.95rem;
    }

    .filter-section button, .filter-section a {
        padding: 8px 16px;
        border-radius: var(--border-radius);
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .filter-section button {
        background: var(--primary);
        color: white;
        border: none;
    }

    .filter-section button:hover {
        background: var(--primary-dark);
    }

    .filter-section a {
        background: #f1f5f9;
        color: var(--secondary);
        border: 1px solid #cbd5e1;
    }

    .filter-section a:hover {
        background: #e2e8f0;
    }

    /* Capture Grid */
    .capture-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .capture-card {
        background: white;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .capture-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .capture-image {
        height: 200px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
    }

    .capture-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .no-image {
        color: #94a3b8;
        font-size: 3rem;
    }

    .capture-details {
        padding: 15px;
    }

    .capture-time {
        font-size: 0.9rem;
        color: var(--secondary);
        margin-bottom: 5px;
    }

    .capture-filename {
        font-weight: 500;
        color: var(--dark);
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
    }

    .empty-state i {
        font-size: 3rem;
        color: #cbd5e1;
        margin-bottom: 15px;
    }

    .empty-state p {
        color: var(--secondary);
        font-size: 1.1rem;
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
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .user-profile {
            margin-top: 15px;
        }
        
        .filter-section form {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .capture-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
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
        
        .capture-grid {
            grid-template-columns: 1fr;
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
            <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="history.php" class="active"><i class="fas fa-history"></i> <span>History</span></a></li>
            <li><a href="admin_panel.php"><i class="fas fa-user-cog"></i> <span>Admin</span></a></li>
            <li><a href="violation.php"><i class="fas fa-exclamation-triangle"></i> <span>Violations</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>History Capture</h1>
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

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET">
                <label for="date">Filter by Date:</label>
                <input type="date" name="date" id="date" value="<?php echo $filterDate; ?>">
                <button type="submit">Apply Filter</button>
                <a href="history.php">Reset</a>
            </form>
        </div>

        <!-- Capture Grid -->
        <div class="capture-grid">
            <?php 
                $items = $dbSorted;
            ?>
            <?php if(count($items) > 0): ?>
                <?php foreach($items as $row): ?>
                    <?php
                        $img = isset($row['image']) ? (string)$row['image'] : '';
                        $imgSafe = $img !== '' ? basename($img) : '';
                        $timeText = isset($row['time']) ? $row['time'] : '';
                        $buster = urlencode($timeText ?: time());
                        $absUploads = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $imgSafe;
                        $absProc = __DIR__ . DIRECTORY_SEPARATOR . 'uploads_processing' . DIRECTORY_SEPARATOR . $imgSafe;
                        $srcUploads = 'uploads/' . rawurlencode($imgSafe);
                        $srcProc = 'uploads_processing/' . rawurlencode($imgSafe);
                        $src = '';
                        if ($imgSafe !== '' && file_exists($absUploads)) {
                            $src = $srcUploads;
                        } elseif ($imgSafe !== '' && file_exists($absProc)) {
                            $src = $srcProc;
                        }
                    ?>
                    <div class="capture-card">
                        <div class="capture-image">
                            <?php if ($src !== ''): ?>
                                <img src="<?php echo $src; ?>?t=<?php echo $buster; ?>" alt="capture">
                            <?php else: ?>
                                <div class="no-image"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="capture-details">
                            <div class="capture-time"><?php echo htmlspecialchars($timeText); ?></div>
                            <div class="capture-filename" title="<?php echo htmlspecialchars($imgSafe); ?>"><?php echo htmlspecialchars($imgSafe); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No capture data found for this date</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>