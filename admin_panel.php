<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";
require_once "includes/functions.php";

// Ambil daftar employee untuk dropdown
$employeeResult = $conn->query("SELECT * FROM employee");

// Ambil daftar gambar dari ESP32-CAM
$imageResult = $conn->query("SELECT * FROM esp32cam_images ORDER BY timestamp DESC");


if(isset($_POST['submit'])){
    $admin_id = $_SESSION['admin_id'];
    $employee_id = $_POST['employee_id']; // ini isinya = employee.id
    $hr_rating = $_POST['hr_rating'];
    $custom_violation = isset($_POST['custom_violation']) ? $_POST['custom_violation'] : '';
    $follow_up = $_POST['follow_up'];
    $image = $_POST['image'];
    $date = date("Y-m-d");

    // Use custom violation if "Other" is selected
    if ($hr_rating === "Lainnya" && !empty($custom_violation)) {
        $hr_rating = $custom_violation;
    }

    // Simpan di tabel violation
    $stmt = $conn->prepare("INSERT INTO violation (employee_id, hr_rating, follow_up, image, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $employee_id, $hr_rating, $follow_up, $image, $date);

    if($stmt->execute()){
        // Ambil email karyawan sesuai employee.id
        $empRes = $conn->query("SELECT email, name FROM employee WHERE employee_id='$employee_id'");
        $emp = $empRes->fetch_assoc();

        if($emp){ // cek biar gak null
            // Kirim email
            $subject = "Notification Pelanggaran";
            $message = "Halo ".$emp['name'].",<br><br>Anda tercatat melakukan pelanggaran. Silahkan cek detail:<br>
                        <b>HR Rating:</b> $hr_rating<br>
                        <b>Tindak Lanjut:</b> $follow_up<br>
                        <b>Gambar:</b><br><img src='http://localhost/monitoring/uploads/$image' width='300'><br><br>Terima kasih.";
            sendEmail($emp['email'], $subject, $message);
        }

        $success = "Data berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan data!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Input Panel - CTC Monitoring</title>
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
        --border-radius: 12px;
        --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
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
        transition: var(--transition);
    }

    .sidebar-header {
        padding: 24px;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }

    .sidebar-header h2 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
        letter-spacing: 0.5px;
    }

    .sidebar ul {
        list-style: none;
        padding: 20px 0;
    }

    .sidebar ul li {
        margin: 6px 0;
    }

    .sidebar ul li a {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        color: var(--secondary);
        text-decoration: none;
        transition: var(--transition);
        font-weight: 500;
        border-left: 4px solid transparent;
    }

    .sidebar ul li a i {
        margin-right: 14px;
        font-size: 20px;
        width: 24px;
        text-align: center;
    }

    .sidebar ul li a:hover, 
    .sidebar ul li a.active {
        background: #f1f5f9;
        color: var(--primary);
        border-left-color: var(--primary);
    }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        padding: 30px;
        transition: var(--transition);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        letter-spacing: -0.5px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        background: white;
        padding: 12px 20px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        transition: var(--transition);
    }

    .user-profile:hover {
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .user-profile img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 12px;
        object-fit: cover;
    }

    /* Form Styles */
    .input-panel {
        background: white;
        border-radius: var(--border-radius);
        padding: 35px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        transition: var(--transition);
        max-width: 1000px;
        margin: 0 auto;
    }

    .input-panel:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .panel-header {
        margin-bottom: 30px;
        text-align: center;
    }

    .panel-header h2 {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .panel-header p {
        color: var(--secondary);
        font-size: 1rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--dark);
        font-size: 1rem;
    }

    .form-group select, 
    .form-group input {
        width: 100%;
        padding: 16px 20px;
        border: 1px solid #cbd5e1;
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
        background-color: white;
    }

    .form-group select:focus, 
    .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .form-group .input-icon {
        position: relative;
    }

    .form-group .input-icon i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
    }

    .form-group .input-icon select,
    .form-group .input-icon input {
        padding-left: 50px;
    }

    .custom-violation {
        margin-top: 15px;
        display: none;
    }

    .custom-violation.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .image-selection {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .image-option {
        position: relative;
        cursor: pointer;
        border-radius: var(--border-radius);
        overflow: hidden;
        transition: var(--transition);
        border: 2px solid transparent;
    }

    .image-option:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .image-option.selected {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }

    .image-option img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .image-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }

    .image-option .checkmark {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 24px;
        height: 24px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: var(--transition);
    }

    .image-option.selected .checkmark {
        opacity: 1;
        background: var(--primary);
    }

    .image-option .checkmark i {
        color: white;
        font-size: 14px;
    }

    .image-option .filename {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 5px;
        font-size: 11px;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
        border: none;
        padding: 16px 28px;
        border-radius: var(--border-radius);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        width: 100%;
        justify-content: center;
        margin-top: 20px;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(37, 99, 235, 0.2);
    }

    .alert {
        padding: 16px 20px;
        border-radius: var(--border-radius);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-success {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .sidebar {
            width: 80px;
            overflow: visible;
        }
        
        .sidebar-header h2, .sidebar ul li a span {
            display: none;
        }
        
        .sidebar ul li a {
            justify-content: center;
            padding: 18px;
            border-left: none;
            border-radius: 8px;
            margin: 0 10px;
        }
        
        .sidebar ul li a i {
            margin-right: 0;
            font-size: 22px;
        }
        
        .sidebar ul li a.active {
            border-left: none;
            background: #e6f0ff;
        }
        
        .main-content {
            margin-left: 80px;
            padding: 25px;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .form-group.full-width {
            grid-column: span 1;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }
        
        .user-profile {
            width: 100%;
            justify-content: center;
        }
        
        .input-panel {
            padding: 25px;
        }
        
        .panel-header h2 {
            font-size: 1.6rem;
        }
        
        .image-selection {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .sidebar {
            width: 0;
        }
        
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
        
        .input-panel {
            padding: 20px 15px;
        }
        
        .form-group select, 
        .form-group input {
            padding: 14px 16px;
        }
        
        .btn-primary {
            padding: 14px 20px;
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
            <li><a href="history.php"><i class="fas fa-history"></i> <span>History Capture</span></a></li>
            <li><a href="admin_panel.php" class="active"><i class="fas fa-user-cog"></i> <span>Admin Input</span></a></li>
            <li><a href="violation.php"><i class="fas fa-exclamation-triangle"></i> <span>Violation Record</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Account Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Admin Input Panel</h1>
            <div class="user-profile">
                <div class="avatar">
                    <i class="fas fa-user-circle" style="font-size: 36px; color: #64748b; margin-right: 12px;"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 15px;">Sem</div>
                    <div style="font-size: 13px; color: #64748b;">manaluyudhasamuel@gmail.com</div>
                </div>
            </div>
        </div>

        <div class="input-panel">
            <div class="panel-header">
                <h2>Input Violation Record</h2>
                <p>Record employee violations and send notifications</p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-user"></i> Nama Pelanggar</label>
                        <select name="employee_id" id="employee_id" required>
                            <option value="">-- Pilih Karyawan --</option>
                            <?php while($emp = $employeeResult->fetch_assoc()){ ?>
                                <option value="<?php echo $emp['employee_id']; ?>"><?php echo $emp['name']; ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="hr_rating"><i class="fas fa-exclamation-circle"></i> Keterangan Pelanggaran</label>
                        <select name="hr_rating" id="hr_rating" required>
                            <option value="">-- Pilih Jenis Pelanggaran --</option>
                            <option value="Tidur">Tidur</option>
                            <option value="Main Game">Main Game</option>
                            <option value="Menggunakan Ponsel Lama">Menggunakan Ponsel (Lama)</option>
                            <option value="Menonton (diluar pekerjaan)">Menonton (diluar pekerjaan)</option>
                            <option value="Lainnya">Lainnya (tulis di bawah)</option>
                        </select>
                        
                        <div id="customViolationContainer" class="custom-violation">
                            <label for="custom_violation" style="margin-top: 15px;">Jenis Pelanggaran Lainnya</label>
                            <input type="text" id="custom_violation" name="custom_violation" placeholder="Tuliskan jenis pelanggaran">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="follow_up"><i class="fas fa-clipboard-check"></i> Tindak Lanjut</label>
                        <select name="follow_up" id="follow_up" required>
                            <option value="">-- Pilih Tindak Lanjut --</option>
                            <option value="Peringatan">Peringatan</option>
                            <option value="SP 1">SP 1</option>
                            <option value="SP 2">SP 2</option>
                            <option value="SP 3">SP 3</option>
                            <option value="PHK">PHK</option>
                        </select>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label><i class="fas fa-image"></i> Pilih Gambar Bukti</label>
                    <div class="image-selection">
                        <?php
                        $dir = __DIR__ . "/uploads_processing";
                        if (is_dir($dir)) {
                            $files = scandir($dir);
                            foreach ($files as $file) {
                                if ($file !== "." && $file !== "..") {
                                    $filePath = "uploads_processing/" . $file;
                                    echo "
                                    <label class='image-option'>
                                        <input type='radio' name='image' value='$file' required>
                                        <img src='$filePath' alt='$file'>
                                        <span class='checkmark'><i class='fas fa-check'></i></span>
                                        <span class='filename'>$file</span>
                                    </label>
                                    ";
                                }
                            }
                        }
                        ?>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Violation Record
                </button>
            </form>
        </div>
    </main>
</div>

<script>
    // Toggle custom violation input
    document.getElementById('hr_rating').addEventListener('change', function() {
        const customContainer = document.getElementById('customViolationContainer');
        if (this.value === 'Lainnya') {
            customContainer.classList.add('active');
        } else {
            customContainer.classList.remove('active');
        }
    });

    // Image selection styling
    document.querySelectorAll('.image-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.image-option').forEach(option => {
                option.classList.remove('selected');
            });
            if (this.checked) {
                this.parentElement.classList.add('selected');
            }
        });
    });
</script>
</body>
</html> 