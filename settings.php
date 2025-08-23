<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";
require_once "includes/functions.php";

$admin_id = $_SESSION['admin_id'];

// Ambil data admin
$adminRes = $conn->query("SELECT * FROM admin WHERE id='$admin_id'");
$admin = $adminRes->fetch_assoc();

if(isset($_POST['update'])){
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password']; // Kosong jika tidak ganti

    if($password){
        $hashed = hashPassword($password);
        $sql = "UPDATE admin SET username=?, email=?, password=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $username, $email, $hashed, $admin_id);
    } else {
        $sql = "UPDATE admin SET username=?, email=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $username, $email, $admin_id);
    }

    if($stmt->execute()){
        $success = "Account updated successfully!";
    } else {
        $error = "Failed to update account!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Settings - CTC Monitoring Employee</title>
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
    .settings-container {
        display: flex;
        justify-content: center;
    }

    .settings-form {
        background: white;
        border-radius: var(--border-radius);
        padding: 35px;
        box-shadow: var(--box-shadow);
        border: 1px solid #e2e8f0;
        width: 100%;
        max-width: 700px;
        transition: var(--transition);
    }

    .settings-form:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .form-header {
        margin-bottom: 30px;
        text-align: center;
    }

    .form-header h2 {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .form-header p {
        color: var(--secondary);
        font-size: 1rem;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--dark);
        font-size: 1rem;
    }

    .form-group input {
        width: 100%;
        padding: 16px 20px;
        border: 1px solid #cbd5e1;
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
    }

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

    .form-group .input-icon input {
        padding-left: 50px;
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
        margin-top: 10px;
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
        
        .settings-form {
            padding: 25px;
        }
        
        .form-header h2 {
            font-size: 1.6rem;
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
        
        .settings-form {
            padding: 20px 15px;
        }
        
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
            <li><a href="history.php"><i class="fas fa-history"></i> <span>History</span></a></li>
            <li><a href="admin_panel.php"><i class="fas fa-user-cog"></i> <span>Admin</span></a></li>
            <li><a href="violation.php"><i class="fas fa-exclamation-triangle"></i> <span>Violations</span></a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Account Settings</h1>
            <div class="user-profile">
                <div class="avatar">
                    <i class="fas fa-user-circle" style="font-size: 36px; color: #64748b; margin-right: 12px;"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 15px;"><?php echo htmlspecialchars($admin['username']); ?></div>
                    <div style="font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($admin['email']); ?></div>
                </div>
            </div>
        </div>

        <div class="settings-container">
            <div class="settings-form">
                <div class="form-header">
                    <h2>Update Your Account</h2>
                    <p>Manage your account settings and preferences</p>
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
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter new password">
                        </div>
                    </div>

                    <button type="submit" name="update" class="btn-primary">
                        <i class="fas fa-save"></i> Update Account
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>