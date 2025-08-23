<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

file_put_contents("debug_log.txt", "Script started\n", FILE_APPEND);

require_once __DIR__ . '/includes/db.php';

// Tentukan mode operasi
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'auto';

try {
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $cwd = getcwd();
    $realUploadDir = @realpath($uploadDir) ?: 'N/A';
    file_put_contents("debug_log.txt", "Upload dir: $uploadDir (real: $realUploadDir) CWD: $cwd\n", FILE_APPEND);

    if (!is_dir($uploadDir)) {
        file_put_contents("debug_log.txt", "Folder belum ada, coba mkdir\n", FILE_APPEND);
        if (!mkdir($uploadDir, 0777, true)) {
            file_put_contents("debug_log.txt", "❌ Gagal membuat folder uploads\n", FILE_APPEND);
            http_response_code(500);
            echo "ERROR";
            exit;
        }
    } else {
        file_put_contents("debug_log.txt", "Folder sudah ada\n", FILE_APPEND);
    }

    if (!is_writable($uploadDir)) {
        file_put_contents("debug_log.txt", "❌ Folder tidak bisa ditulis: $uploadDir\n", FILE_APPEND);
        http_response_code(500);
        echo "ERROR: Folder uploads tidak bisa ditulis";
        exit;
    }

    $filename = "capture_" . date("Y-m-d_H-i-s") . ".jpg";
    $filepath = $uploadDir . $filename;
    $realFilePath = $filepath;
    file_put_contents("debug_log.txt", "Filepath tujuan: $filepath\n", FILE_APPEND);

    // Mode AUTO: Ambil gambar dari ESP32-CAM (seperti kode lama)
    if ($mode === 'auto') {
        $ts = time();
        $urls = [
            "http://10.39.183.57:81/shot.jpg?_cb={$ts}",
            "http://10.39.183.57:81/capture?_cb={$ts}",
            "http://10.39.183.57/shot.jpg?_cb={$ts}",
            "http://10.39.183.57/capture?_cb={$ts}"
        ];

        function fetch_snapshot($url) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: ESP32-CAPTURE/1.0',
                    'Connection: close'
                ]);
                $data = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return [$data, $err, $code];
            } else {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 6,
                        'header' => "User-Agent: ESP32-CAPTURE/1.0\r\nConnection: close\r\n"
                    ]
                ]);
                $data = @file_get_contents($url, false, $context);
                $code = 0;
                if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                    $code = (int)$m[1];
                }
                $err = $data === false ? 'file_get_contents failed' : '';
                return [$data, $err, $code];
            }
        }

        function fetch_snapshot_multi($urls) {
            if (!function_exists('curl_multi_init')) {
                return [false, '', 'curl_multi not available'];
            }
            $mh = curl_multi_init();
            $handles = [];
            foreach ($urls as $url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: ESP32-CAPTURE/1.0',
                    'Connection: close'
                ]);
                curl_multi_add_handle($mh, $ch);
                $key = function_exists('spl_object_id') ? spl_object_id($ch) : spl_object_hash($ch);
                $handles[$key] = [$ch, $url];
            }
            $start = microtime(true);
            do {
                $status = curl_multi_exec($mh, $active);
                if ($status > 0) break;
                while ($info = curl_multi_info_read($mh)) {
                    $ch = $info['handle'];
                    $key = function_exists('spl_object_id') ? spl_object_id($ch) : spl_object_hash($ch);
                    list($h, $url) = $handles[$key];
                    $data = curl_multi_getcontent($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_multi_remove_handle($mh, $ch);
                    unset($handles[$key]);
                    if ($data !== false && $code >= 200 && $code < 300 && strlen($data) > 100 && ord($data[0]) === 0xFF && ord($data[1]) === 0xD8) {
                        curl_multi_close($mh);
                        return [$data, $url, ''];
                    }
                }
                if ($active) {
                    curl_multi_select($mh, 0.2);
                }
            } while ($active && (microtime(true) - $start) < 7.0);
            // Cleanup
            foreach ($handles as $pair) {
                curl_multi_remove_handle($mh, $pair[0]);
            }
            curl_multi_close($mh);
            return [false, '', 'timeout'];
        }

        $imageData = false;
        $usedUrl = '';
        $lastErr = '';
        $lastCode = 0;
        if (function_exists('curl_multi_init')) {
            file_put_contents("debug_log.txt", "Mulai parallel fetch...\n", FILE_APPEND);
            list($data, $url, $err) = fetch_snapshot_multi($urls);
            if ($data !== false) {
                $imageData = $data;
                $usedUrl = $url;
            } else {
                $lastErr = $err;
            }
        } else {
            foreach ($urls as $u) {
                file_put_contents("debug_log.txt", "Coba ambil gambar dari: $u\n", FILE_APPEND);
                list($data, $err, $code) = fetch_snapshot($u);
                if ($data !== false && $code >= 200 && $code < 300 && strlen($data) > 100 && ord($data[0]) === 0xFF && ord($data[1]) === 0xD8) {
                    $imageData = $data;
                    $usedUrl = $u;
                    break;
                }
                $lastErr = $err;
                $lastCode = $code;
            }
        }

        if ($imageData === false) {
            file_put_contents("debug_log.txt", "❌ Gagal ambil gambar dari semua URL. HTTP:$lastCode ERR:$lastErr\n", FILE_APPEND);
            http_response_code(500);
            echo "ERROR: Gagal ambil gambar dari kamera. Pastikan ESP32-CAM online (HTTP:$lastCode, ERR:$lastErr)";
            exit;
        }
    } 
    // Mode MANUAL: Terima gambar dari ESP32-CAM via POST
    else if ($mode === 'manual' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents("debug_log.txt", "Mode manual: Menerima gambar via POST\n", FILE_APPEND);
        
        // Cek jika data POST adalah gambar JPEG
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($contentType, 'image/jpeg') !== false) {
            $imageData = file_get_contents('php://input');
            if (empty($imageData)) {
                file_put_contents("debug_log.txt", "❌ Tidak ada data gambar diterima\n", FILE_APPEND);
                http_response_code(400);
                echo "ERROR: Tidak ada data gambar";
                exit;
            }
            
            // Verifikasi bahwa ini adalah gambar JPEG yang valid
            if (strlen($imageData) < 100 || ord($imageData[0]) !== 0xFF || ord($imageData[1]) !== 0xD8) {
                file_put_contents("debug_log.txt", "❌ Data yang diterima bukan gambar JPEG valid\n", FILE_APPEND);
                http_response_code(400);
                echo "ERROR: Data bukan gambar JPEG valid";
                exit;
            }
            
            file_put_contents("debug_log.txt", "✅ Diterima gambar JPEG via POST, ukuran: " . strlen($imageData) . " bytes\n", FILE_APPEND);
        } else {
            file_put_contents("debug_log.txt", "❌ Content-Type tidak valid: $contentType\n", FILE_APPEND);
            http_response_code(400);
            echo "ERROR: Content-Type harus image/jpeg";
            exit;
        }
    } else {
        file_put_contents("debug_log.txt", "❌ Mode tidak dikenali: $mode\n", FILE_APPEND);
        http_response_code(400);
        echo "ERROR: Mode tidak valid";
        exit;
    }

    $written = @file_put_contents($filepath, $imageData);
    if ($written === false) {
        file_put_contents("debug_log.txt", "❌ Gagal simpan gambar ke $filepath\n", FILE_APPEND);
        http_response_code(500);
        echo "ERROR: Gagal simpan gambar ke server";
        exit;
    }
    clearstatcache(true, $filepath);
    $exists = file_exists($filepath) ? 'yes' : 'no';
    $size = @filesize($filepath);
    file_put_contents("debug_log.txt", "✅ Tersimpan: bytes=$written exists=$exists size=$size path=$filepath\n", FILE_APPEND);

    // Salin sebagai latest.jpg
    @copy($filepath, $uploadDir . 'latest.jpg');

    // Salin juga ke uploads_processing agar riwayat selalu punya file persisten
    $uploadProcessingDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads_processing' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadProcessingDir)) {
        @mkdir($uploadProcessingDir, 0777, true);
    }
    @copy($filepath, $uploadProcessingDir . $filename);

    // Log isi folder uploads (maks 10 entri)
    $files = @scandir($uploadDir) ?: [];
    $files = array_values(array_diff($files, [".", ".."]));
    file_put_contents("debug_log.txt", "Isi folder uploads (max 10): " . implode(", ", array_slice($files, 0, 10)) . " (total=" . count($files) . ")\n", FILE_APPEND);

    // Simpan ke DB (tabel capture) bila ada
    if (isset($conn)) {
        $escaped = $conn->real_escape_string($filename);
        // Deteksi kolom waktu
        $hasCreatedAt = false;
        $hasTimestamp = false;
        if ($res = $conn->query("SHOW COLUMNS FROM `capture` LIKE 'created_at'")) {
            $hasCreatedAt = $res->num_rows > 0; $res->close();
        }
        if ($res = $conn->query("SHOW COLUMNS FROM `capture` LIKE 'timestamp'")) {
            $hasTimestamp = $res->num_rows > 0; $res->close();
        }
        $cols = ['image'];
        $vals = ["'$escaped'"];
        if ($hasCreatedAt) { $cols[] = 'created_at'; $vals[] = 'NOW()'; }
        if ($hasTimestamp) { $cols[] = '`timestamp`'; $vals[] = 'NOW()'; }
        $sql = "INSERT INTO `capture` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        if (!$conn->query($sql)) {
            file_put_contents("debug_log.txt", "DB insert error: " . $conn->error . " SQL: $sql\n", FILE_APPEND);
        } else {
            $id = $conn->insert_id;
            file_put_contents("debug_log.txt", "DB insert ok id=$id file=$escaped\n", FILE_APPEND);
        }
    }

    if ($mode === 'auto') {
        file_put_contents("debug_log.txt", "✅ Capture sukses dari $usedUrl: $filename\n", FILE_APPEND);
    } else {
        file_put_contents("debug_log.txt", "✅ Upload sukses via POST: $filename\n", FILE_APPEND);
    }
    
    echo $filename;

} catch (Exception $e) {
    file_put_contents("debug_log.txt", "‼️ Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "ERROR";
}
?>