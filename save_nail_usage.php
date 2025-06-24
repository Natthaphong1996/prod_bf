<?php
session_start();
require_once 'config_db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_id = $_POST['job_id'];
    $nail_ids = $_POST['nail_id'];
    $quantities = $_POST['quantity'];
    
    $nails_data = [];

    foreach ($nail_ids as $index => $nail_id) {
        $qty = $quantities[$index];
        if (!empty($nail_id) && is_numeric($qty) && $qty > 0) {
            $nails_data[] = [
                "nail_id" => $nail_id,
                "qty" => $qty
            ];
        }
    }

    if (!empty($job_id) && count($nails_data) > 0) {
        $nails_json = json_encode($nails_data, JSON_UNESCAPED_UNICODE);
        
        $sql = "INSERT INTO nail_usage_log (job_id, nails) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $job_id, $nails_json);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "บันทึกการเบิกตะปูเรียบร้อยแล้ว"]);
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการบันทึก"]);
        }

        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "ข้อมูลไม่ครบถ้วน"]);
    }

    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>
