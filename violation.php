<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";

// Filter tanggal jika ada
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where = "";
if($start_date && $end_date){
    $where = "WHERE v.date BETWEEN '$start_date' AND '$end_date'";
}

// Ambil data violation + email
$sql = "SELECT v.*, e.name as employee_name, e.email as employee_email 
        FROM violation v 
        JOIN employee e ON v.employee_id = e.employee_id
        $where
        ORDER BY v.date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Violation Record - CTC Monitoring Employee</title>
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

    /* Table Styles */
    .table-container {
        background: white;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    th {
        background-color: #f8fafc;
        font-weight: 600;
        color: var(--dark);
    }

    tr:hover {
        background-color: #f8fafc;
    }

    .violation-image {
        width: 80px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
    }

    /* Report Buttons */
    .report-buttons {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }

    .report-buttons button {
        padding: 10px 20px;
        border-radius: var(--border-radius);
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pdf-button {
        background: var(--danger);
        color: white;
    }

    .pdf-button:hover {
        background: #dc2626;
    }

    .email-button {
        background: var(--success);
        color: white;
    }

    .email-button:hover {
        background: #059669;
    }

    .empty-state {
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
        
        .report-buttons {
            flex-direction: column;
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
        
        th, td {
            padding: 8px 10px;
            font-size: 0.9rem;
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
            <li><a href="history.php"><i class="fas fa-history"></i> <span>History</span></a></li>
            <li><a href="admin_panel.php"><i class="fas fa-user-cog"></i> <span>Admin</span></a></li>
            <li><a href="violation.php" class="active"><i class="fas fa-exclamation-triangle"></i> <span>Violations</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Violation Record</h1>
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
                <label for="start_date">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                <label for="end_date">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                <button type="submit">Apply Filter</button>
                <a href="violation.php">Reset</a>
            </form>
        </div>

        <!-- Table Pelanggaran -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee Name</th>
                        <th>Email</th>
                        <th>Keterangan Pelanggaran</th>
                        <th>Follow Up</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_email']); ?></td>
                            <td><?php echo htmlspecialchars($row['hr_rating']); ?></td>
                            <td><?php echo htmlspecialchars($row['follow_up']); ?></td>
                            <td>
                                <?php if($row['image']): ?>
                                <img src="uploads_processing/<?php echo htmlspecialchars($row['image']); ?>" class="violation-image" alt="Violation Image">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No violation data found for this date range</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Report Buttons -->
        <div class="report-buttons">
            <form method="get" action="generate_report.php" target="_blank">
                <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="pdf-button">
                    <i class="fas fa-file-pdf"></i> Generate PDF Report
                </button>
            </form>

            <form method="get" action="send_email_report.php" target="_blank">
                <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="email-button">
                    <i class="fas fa-envelope"></i> Send Report to Employees
                </button>
            </form>
        </div>
    </main>
</div>
</body>
</html>