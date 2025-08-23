<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit;
}
require_once "includes/db.php";
require_once "includes/fpdf.php";

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where = "";
if($start_date && $end_date){
    $where = "WHERE v.date BETWEEN '$start_date' AND '$end_date'";
}

$sql = "SELECT v.*, e.name as employee_name, e.email as employee_email, v.image as violation_image
        FROM violation v
        JOIN employee e ON v.employee_id = e.employee_id
        $where
        ORDER BY v.date DESC";

$result = $conn->query($sql);

// Add signature section at the bottom of the report
class PDF extends FPDF {
    // Menambahkan header
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'Laporan Pelanggaran Karyawan',0,1,'C');
        $this->Ln(5);
    }

    // Menambahkan footer dengan informasi history
    function Footer() {
        // Posisi footer 15mm dari bawah
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);

        // Menambahkan nomor halaman
        $this->Cell(0,10,'Halaman '.$this->PageNo(),0,0,'C');

        // Menambahkan informasi sejarah laporan di footer
        $this->Ln(5);  // Menambahkan jarak baris baru
        $this->Cell(0,10,'Laporan dibuat pada: '.date('Y-m-d H:i:s'),0,0,'C');
        $this->Ln(5);
        //$this->Cell(0,10,'Dibuat oleh: '.$_SESSION['admin_id'],0,0,'C');  // Menampilkan ID admin yang membuat laporan
    }

    // Custom function to handle the signature
    function AddSignature($signatureImagePath, $signerName) {
        $this->SetY(-80);  // Move up from the bottom to position the signature
        $this->SetX(-90);  // Set the X position for the right alignment
        
        // Signature label
        $this->Cell(0, 10, 'HR Departement', 0, 1, 'R');  // Right aligned label
        $this->Ln(5);
        
        // Insert the signature image
        if (file_exists($signatureImagePath)) {
            // Get the page width
            $pageWidth = $this->GetPageWidth();
            
            // Calculate X position for the right edge (subtract image width from total page width)
            $imageWidth = 30;  // Width of the signature image (in this case, 30 units)
            $xPosition = $pageWidth - $imageWidth - 10;  // 10 units padding from the right edge
            
            // Set the Y position for the signature
            $this->Image($signatureImagePath, $xPosition, $this->GetY(), $imageWidth);  // Position the image at the right edge
            $this->Ln(20);  // Add some space after the image
        } else {
            $this->Cell(0, 10, 'Signature image not found!', 0, 1, 'C');
        }
        
        // Add name of the signer
        $this->Cell(0, 10, $signerName, 0, 1, 'R');  // Right aligned signer's name
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AddPage('L', 'A4');
$pdf->SetFont('Arial','B',12);

// Header Table
$pdf->Cell(30,10,'Tanggal',1);
$pdf->Cell(50,10,'Nama Karyawan',1);
$pdf->Cell(80,10,'Email',1);
$pdf->Cell(60,10,'Keterangan Pelanggaran',1);
$pdf->Cell(60,10,'Tindak Lanjut',1);
$pdf->Ln();

$pdf->SetFont('Arial','',12);
if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $pdf->Cell(30,10,$row['date'],1);
        $pdf->Cell(50,10,$row['employee_name'],1);
        $pdf->Cell(80,10,$row['employee_email'],1);
        $pdf->Cell(60,10,$row['hr_rating'],1);
        $pdf->Cell(60,10,$row['follow_up'],1);
        $pdf->Ln();

        // Check if image exists and has a valid filename
        if(!empty($row['violation_image'])){
            $imagePath = 'uploads/' . $row['violation_image'];
            
            // Check if the file exists and is an image
            if(file_exists($imagePath) && exif_imagetype($imagePath)){
                $pdf->Image($imagePath, 10, $pdf->GetY(), 50);  // Adjust size as needed
                $pdf->Ln(60);  // Add some space before the next entry
            }
        }
    }
} else {
    $pdf->Cell(300,10,'Tidak ada data',1,0,'C');
}

// Call the signature function at the end
$pdf->AddSignature('assets/images/signature.png', 'Alexa Azkanio');  // Update with actual signature image and name

$pdf->Output('D', 'Laporan_Pelanggaran.pdf');
exit;
?>
