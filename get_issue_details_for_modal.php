<?php
// ไฟล์: get_issue_details_for_modal.php
header('Content-Type: application/json');
require_once('config_db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['issue_id']) || !is_numeric($_POST['issue_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit();
}
$issue_id = (int)$_POST['issue_id'];

$response = ['success' => false, 'message' => 'เกิดข้อผิดพลาดที่ไม่รู้จัก'];

try {
    // --- 1. ★★★ แก้ไข: เพิ่ม wi.job_id ในคำสั่ง SELECT ★★★ ---
    $sql_main = "SELECT 
                    wi.prod_id, wi.product_code, wi.quantity AS order_quantity, wi.job_id,
                    pl.thickness, pl.width, pl.length, pl.prod_type
                 FROM wood_issue AS wi
                 LEFT JOIN prod_list AS pl ON CAST(wi.prod_id AS UNSIGNED) = pl.prod_id
                 WHERE wi.issue_id = ?";
    
    $stmt_main = $conn->prepare($sql_main);
    if (!$stmt_main) throw new Exception("SQL Error (main): " . $conn->error);
    $stmt_main->bind_param("i", $issue_id);
    $stmt_main->execute();
    $main_data = $stmt_main->get_result()->fetch_assoc();
    $stmt_main->close();

    if (!$main_data) {
        throw new Exception("ไม่พบข้อมูลใบเบิก ID: $issue_id");
    }

    // --- 2. ดึงข้อมูล BOM ---
    $stmt_bom = $conn->prepare("SELECT parts FROM bom WHERE prod_id = ?");
    if (!$stmt_bom) throw new Exception("SQL Error (bom): " . $conn->error);
    $stmt_bom->bind_param("s", $main_data['prod_id']);
    $stmt_bom->execute();
    $bom_data = $stmt_bom->get_result()->fetch_assoc();
    $stmt_bom->close();

    if (!$bom_data || empty($bom_data['parts'])) {
        throw new Exception("ไม่พบข้อมูล BOM สำหรับผลิตภัณฑ์นี้");
    }

    $parts_in_bom = json_decode($bom_data['parts'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("ข้อมูล BOM (parts) ไม่ใช่รูปแบบ JSON ที่ถูกต้อง");
    }

    $part_details_list = [];
    $part_ids = array_column($parts_in_bom, 'part_id');

    if (!empty($part_ids)) {
        // --- 3. ดึงข้อมูล Part และสต็อกคงคลัง ---
        $part_data_map = [];
        $placeholders = implode(',', array_fill(0, count($part_ids), '?'));
        $types = str_repeat('i', count($part_ids));
        
        $sql_parts = "SELECT 
                        pl.part_id, pl.part_code, pl.part_thickness, pl.part_width, pl.part_length, pl.part_type,
                        wi.quantity AS current_stock
                      FROM part_list AS pl
                      LEFT JOIN wip_inventory AS wi ON CAST(pl.part_id AS CHAR) = wi.part_id
                      WHERE pl.part_id IN ($placeholders)";
        
        $stmt_parts = $conn->prepare($sql_parts);
        if (!$stmt_parts) throw new Exception("SQL Error (parts): " . $conn->error);
        $stmt_parts->bind_param($types, ...$part_ids);
        $stmt_parts->execute();
        $result_parts = $stmt_parts->get_result();
        while ($row = $result_parts->fetch_assoc()) {
            $part_data_map[$row['part_id']] = $row;
        }
        $stmt_parts->close();
        
        // --- 4. ประกอบข้อมูลเพื่อส่งกลับ ---
        foreach ($parts_in_bom as $bom_part) {
            if (!isset($bom_part['part_id']) || (int)($bom_part['quantity'] ?? 0) <= 0) continue;
            $part_id = $bom_part['part_id'];
            if (isset($part_data_map[$part_id])) {
                $part_info = $part_data_map[$part_id];
                $part_details_list[] = [
                    'part_code'     => $part_info['part_code'] ?? 'N/A',
                    'part_type'     => $part_info['part_type'] ?? '',
                    'part_size'     => ($part_info['part_thickness'] ?? 'N/A') . 'x' . ($part_info['part_width'] ?? 'N/A') . 'x' . ($part_info['part_length'] ?? 'N/A'),
                    'required_qty'  => (int)$bom_part['quantity'] * (int)$main_data['order_quantity'],
                    'current_stock' => (int)($part_info['current_stock'] ?? 0)
                ];
            }
        }
    }

    if (empty($part_details_list)) {
        throw new Exception("ไม่สามารถสร้างรายการเบิกได้ เนื่องจากไม่พบข้อมูลชิ้นส่วนที่ถูกต้องใน BOM หรือในคลัง");
    }

    // --- 5. ★★★ แก้ไข: เพิ่ม job_id เข้าไปในข้อมูลที่ส่งกลับ ★★★ ---
    $response['success'] = true;
    $response['message'] = 'ดึงข้อมูลสำเร็จ';
    $response['data'] = [
        'job_id'         => $main_data['job_id'], // เพิ่ม job_id
        'product_code'   => $main_data['product_code'],
        'product_type'   => $main_data['prod_type'] ?? 'N/A',
        'product_size'   => ($main_data['thickness'] ?? 'N/A') . 'x' . ($main_data['width'] ?? 'N/A') . 'x' . ($main_data['length'] ?? 'N/A'),
        'order_quantity' => $main_data['order_quantity'],
        'parts'          => $part_details_list
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
