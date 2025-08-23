<?php
require_once "includes/db.php";

// Ambil capture terbaru dari tabel esp32cam_images yang belum ditampilkan
$sql = "SELECT id, image_name, description, created_at 
        FROM esp32cam_images 
        WHERE notified = 0 
        ORDER BY created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);

$notifications = [];
while($row = $result->fetch_assoc()){
    $notifications[] = [
        'id' => $row['id'],
        'image' => $row['image_name'],
        'description' => $row['description'],
        'time' => $row['created_at']
    ];
}

// Update supaya tidak muncul lagi
if(count($notifications) > 0){
    $ids = array_column($notifications, 'id');
    $ids_str = implode(',', $ids);
    $conn->query("UPDATE esp32cam_images SET notified=1 WHERE id IN ($ids_str)");
}

// Output JSON
echo json_encode($notifications);
?>
