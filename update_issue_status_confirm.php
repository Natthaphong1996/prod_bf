<?php
// ไฟล์: update_issue_status_confirm.php
session_start();
header('Content-Type: application/json');

include 'config_db.php';
include 'wip_inventory_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['issue_id']) || !isset($_POST['new_status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit();
}

$issue_id = filter_var($_POST['issue_id'], FILTER_VALIDATE_INT);
$new_status = $conn->real_escape_string(trim($_POST['new_status']));
$issued_by = isset($_POST['issued_by']) ? $conn->real_escape_string(trim($_POST['issued_by'])) : '';

if ($issue_id === false) {
    echo json_encode(['success' => false, 'message' => 'Issue ID ไม่ถูกต้อง']);
    exit();
}

$conn->begin_transaction();

try {
    $stmt_check = $conn->prepare("SELECT issue_status FROM wood_issue WHERE issue_id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $issue_id);
    $stmt_check->execute();
    $current_issue = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$current_issue) {
        throw new Exception("ไม่พบใบเบิก ID: $issue_id นี้ในระบบ");
    }
    
    $current_status = $current_issue['issue_status'];
    $message = 'อัปเดตสถานะเรียบร้อยแล้ว';
    
    if ($new_status === 'เบิกแล้ว') {
        if ($current_status !== 'รอเบิก') {
            throw new Exception("ไม่สามารถเปลี่ยนเป็นสถานะ 'เบิกแล้ว' ได้จากสถานะปัจจุบัน ({$current_status})");
        }
        if (empty($issued_by)) {
            throw new Exception("กรุณาระบุชื่อผู้เบิก");
        }
        
        // --- ตรวจสอบผลลัพธ์จากการตัดสต็อก ---
        $deduction_result = deduct_stock_for_wood_issue($issue_id, $conn);
        
        if ($deduction_result['success'] === false) {
            // --- ★★★ ถ้าเป็น Error สต็อกไม่พอ ให้สร้างข้อความใหม่ ★★★ ---
            if (isset($deduction_result['error_type']) && $deduction_result['error_type'] === 'INSUFFICIENT_STOCK') {
                
                // --- ★★★ สร้าง HTML ที่จัดรูปแบบสวยงาม ★★★ ---
                $error_list_items = '';
                foreach ($deduction_result['details'] as $item) {
                    $error_list_items .= "<li>{$item['part_code']} {$item['part_size']} {$item['part_type']} <strong>ขาดอีก {$item['shortage']} ชิ้น</strong></li>";
                }

                $formatted_message = "
                    <p class='text-start'>สต็อกชิ้นส่วนไม่เพียงพอสำหรับการเบิก:</p>
                    <ul class='list-group list-group-flush text-start'>
                        {$error_list_items}
                    </ul>
                ";
                throw new Exception($formatted_message);

            } else {
                // ถ้าเป็น Error อื่นๆ
                throw new Exception($deduction_result['message']);
            }
        }
        
        // ถ้าตัดสต็อกสำเร็จ ให้อัปเดตสถานะ
        $stmt_update = $conn->prepare("UPDATE wood_issue SET issue_status = ?, issued_by = ?, issue_date = NOW() WHERE issue_id = ?");
        $stmt_update->bind_param("ssi", $new_status, $issued_by, $issue_id);
        $message = 'ยืนยันการเบิกและตัดสต็อกเรียบร้อยแล้ว';
    } else {
        // กรณีเปลี่ยนสถานะอื่นๆ
        $stmt_update = $conn->prepare("UPDATE wood_issue SET issue_status = ? WHERE issue_id = ?");
        $stmt_update->bind_param("si", $new_status, $issue_id);
    }
    
    if (!$stmt_update->execute()) {
        throw new Exception("ไม่สามารถอัปเดตสถานะได้: " . $stmt_update->error);
    }
    $stmt_update->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
