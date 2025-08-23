<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}

require_once "includes/db.php";
require_once "includes/PHPMailer/PHPMailer.php";
require_once "includes/PHPMailer/SMTP.php";
require_once "includes/PHPMailer/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inisialisasi variabel dengan nilai default
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$employees = [];
$successCount = 0;
$errorCount = 0;
$emailSent = []; // Array untuk melacak email yang sudah dikirim

// Hanya jalankan query jika tanggal disediakan
if($start_date && $end_date){
    $where = "WHERE v.date BETWEEN '$start_date' AND '$end_date'";
    
    $sql = "SELECT e.email, e.name, v.date, v.hr_rating, v.follow_up
            FROM violation v
            JOIN employee e ON v.employee_id = e.employee_id
            $where
            ORDER BY e.email, v.date";
    $result = $conn->query($sql);

    if($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $email = $row['email'];
            if(!isset($employees[$email])) {
                $employees[$email] = [
                    'name' => $row['name'],
                    'violations' => []
                ];
            }
            $employees[$email]['violations'][] = [
                'date' => $row['date'],
                'hr_rating' => $row['hr_rating'],
                'follow_up' => $row['follow_up']
            ];
        }
    }
}

// HTML UI dimulai dari sini
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Email - Employee Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6580);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 10px 10px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .status-success {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: linear-gradient(135deg, #495057, #343a40);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .summary-box {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .logo i {
            font-size: 40px;
            color: #2c3e50;
        }
        .footer {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 0;
            margin-top: 2rem;
            border-radius: 10px 10px 0 0;
        }
        .alert-empty {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="header text-center">
        <div class="container">
            <div class="logo">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h1><i class="fas fa-paper-plane me-2"></i>Laporan Pengiriman Email</h1>
            <p class="lead">Sistem Monitoring Karyawan - PT. Victory Blessings Indonesia</p>
        </div>
    </div>

    <div class="container">
        <div class="summary-box">
            <h5><i class="fas fa-info-circle me-2"></i>Informasi Laporan</h5>
            <p class="mb-0">Periode: <strong><?php echo $start_date ? htmlspecialchars($start_date) : 'Semua'; ?> hingga <?php echo $end_date ? htmlspecialchars($end_date) : 'Semua'; ?></strong></p>
            <p class="mb-0">Total Karyawan: <strong><?php echo count($employees); ?></strong></p>
        </div>

        <?php if(empty($employees)): ?>
            <div class="alert alert-warning alert-empty">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Tidak ada data pelanggaran</strong> untuk periode yang dipilih.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Hasil Pengiriman Email</h5>
                </div>
                <div class="card-body">
                    <?php
                    foreach($employees as $email => $data) {
                        try {
                            // Cek jika email belum dikirim
                            if(!isset($emailSent[$email])) {
                                $emailSent[$email] = true; // Tandai email sudah dikirim

                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'manaluyudhasamuel@gmail.com';
                                $mail->Password = 'ngaf ppes ntpf mtlu';
                                $mail->SMTPSecure = 'tls';
                                $mail->Port = 587;

                                $mail->setFrom('manaluyudhasamuel@gmail.com', 'HR Monitoring');
                                $mail->addAddress($email, $data['name']);
                                $mail->isHTML(true);
                                $mail->Subject = 'Laporan Pelanggaran Anda';

                                $body = "<p>Yth. Sdr. <strong>{$data['name']}</strong>,</p>
                                        <p>Dengan hormat,</p>
                                        <p>Bersama email ini, kami sampaikan rekapitulasi pelanggaran yang tercatat dalam sistem HR kami sebagai berikut:</p>";
                                $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
                                            <tr style='background-color: #f2f2f2;'><th>Tanggal</th><th>Keterangan Pelanggaran</th><th>Tindak Lanjut</th></tr>";
                                
                                foreach($data['violations'] as $v){
                                    $body .= "<tr>
                                                <td>{$v['date']}</td>
                                                <td>{$v['hr_rating']}</td>
                                                <td>{$v['follow_up']}</td>
                                              </tr>";
                                }
                                
                                $body .= "</table>
                                        <p>Mohon untuk diperhatikan. Kami berharap Anda dapat meningkatkan kinerja dan mematuhi peraturan perusahaan yang berlaku.</p>
                                        <p>Apabila terdapat pertanyaan atau klarifikasi, silakan menghubungi tim HR.</p>
                                        <p>Terima kasih atas perhatian dan kerja samanya.</p>
                                        <p>Hormat kami,<br>
                                        Human Resource Departemen<br>
                                        PT. Victory Blessings Indonesia</p>";

                                $mail->Body = $body;
                                $mail->send();
                                
                                echo "<div class='alert alert-success' role='alert'>";
                                echo "<i class='fas fa-check-circle status-success me-2'></i>";
                                echo "<strong>Berhasil dikirim</strong> ke: <a href='mailto:$email'>$email</a> ({$data['name']})";
                                echo "<span class='badge bg-success float-end'>" . count($data['violations']) . " pelanggaran</span>";
                                echo "</div>";
                                $successCount++;
                            }
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger' role='alert'>";
                            echo "<i class='fas fa-exclamation-circle status-error me-2'></i>";
                            echo "<strong>Gagal dikirim</strong> ke: <a href='mailto:$email'>$email</a> ({$data['name']})";
                            echo "<span class='badge bg-danger float-end'>" . count($data['violations']) . " pelanggaran</span>";
                            echo "<div class='mt-2 small'>Error: " . htmlspecialchars($mail->ErrorInfo) . "</div>";
                            echo "</div>";
                            $errorCount++;
                        }
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer text-center">
        <p>&copy; 2025 PT. Victory Blessings Indonesia | Human Resource Department</p>
    </div>

</body>
</html>
