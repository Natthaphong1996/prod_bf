<?php
/**
 * ไฟล์: wip_inventory_functions.php
 * คำอธิบาย: ไฟล์นี้รวบรวมฟังก์ชันสำหรับจัดการสต็อกสินค้า (WIP Inventory)
 * เวอร์ชันนี้ปรับปรุงให้ส่งข้อมูล Error กลับไปเป็น Array ที่มีโครงสร้าง เพื่อให้ง่ายต่อการนำไปแสดงผล
 */

function deduct_stock_for_wood_issue($issue_id, $conn) {
    // 1. ดึงข้อมูลใบเบิกที่จำเป็น
    $stmt_issue = $conn->prepare("SELECT prod_id, quantity FROM wood_issue WHERE issue_id = ?");
    if (!$stmt_issue) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเตรียม SQL (wood_issue): ' . $conn->error];
    }
    $stmt_issue->bind_param("i", $issue_id);
    $stmt_issue->execute();
    $issue_data = $stmt_issue->get_result()->fetch_assoc();
    $stmt_issue->close();

    if (!$issue_data) {
        return ['success' => false, 'message' => "ไม่พบข้อมูลใบเบิก ID: $issue_id"];
    }

    $prod_id = $issue_data['prod_id'];
    $order_quantity = $issue_data['quantity'];

    // 2. ดึงข้อมูล BOM
    $stmt_bom = $conn->prepare("SELECT parts FROM bom WHERE prod_id = ?");
    if (!$stmt_bom) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเตรียม SQL (bom): ' . $conn->error];
    }
    $stmt_bom->bind_param("s", $prod_id);
    $stmt_bom->execute();
    $bom_data = $stmt_bom->get_result()->fetch_assoc();
    $stmt_bom->close();

    if (!$bom_data || empty($bom_data['parts'])) {
        return ['success' => false, 'message' => "ไม่พบข้อมูล BOM สำหรับผลิตภัณฑ์ ID: $prod_id"];
    }

    $parts_in_bom = json_decode($bom_data['parts'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'ข้อมูล BOM (parts) ไม่ใช่รูปแบบ JSON ที่ถูกต้อง'];
    }

    $parts_to_deduct = [];
    foreach ($parts_in_bom as $part) {
        if (!isset($part['part_id']) || !isset($part['quantity']) || (int)$part['quantity'] <= 0) continue;
        
        $parts_to_deduct[] = [
            'part_id' => $part['part_id'],
            'required_qty' => (int)$part['quantity'] * (int)$order_quantity,
        ];
    }

    if (empty($parts_to_deduct)) {
        return ['success' => false, 'message' => "ข้อมูล BOM ของผลิตภัณฑ์ ID: $prod_id ไม่มีรายการชิ้นส่วนที่ต้องเบิก"];
    }
    
    // --- 4. ขั้นตอนการตรวจสอบสต็อก (เวอร์ชันปรับปรุงใหม่ทั้งหมด) ---
    
    $all_part_ids = array_column($parts_to_deduct, 'part_id');
    $placeholders = implode(',', array_fill(0, count($all_part_ids), '?'));
    
    $stock_and_details_map = [];
    $sql_check = "SELECT
                    pl.part_id, pl.part_code, pl.part_type,
                    pl.part_thickness, pl.part_width, pl.part_length,
                    wi.quantity
                  FROM part_list AS pl
                  LEFT JOIN wip_inventory AS wi ON CAST(pl.part_id AS CHAR) = wi.part_id
                  WHERE pl.part_id IN ($placeholders)
                  FOR UPDATE"; 
                  
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        return ['success' => false, 'message' => 'Error preparing bulk stock check: ' . $conn->error];
    }
    $stmt_check->bind_param(str_repeat('i', count($all_part_ids)), ...$all_part_ids);
    $stmt_check->execute();
    $result_stock = $stmt_check->get_result();
    while ($row = $result_stock->fetch_assoc()) {
        $stock_and_details_map[$row['part_id']] = $row;
    }
    $stmt_check->close();

    $insufficient_parts = [];
    foreach ($parts_to_deduct as $part) {
        $part_id = $part['part_id'];
        $qty_to_deduct = $part['required_qty'];
        
        $part_details = $stock_and_details_map[$part_id] ?? null;
        $current_stock = $part_details ? (int)$part_details['quantity'] : 0;

        if ($current_stock < $qty_to_deduct) {
            $insufficient_parts[] = [
                'part_code' => $part_details['part_code'] ?? "ID:{$part_id}",
                'part_size' => isset($part_details) ? "({$part_details['part_thickness']}x{$part_details['part_width']}x{$part_details['part_length']})" : '',
                'part_type' => $part_details['part_type'] ?? '',
                'shortage' => $qty_to_deduct - $current_stock
            ];
        }
    }

    // ★★★ ถ้ามีรายการที่ขาด ให้ส่งข้อมูลกลับไปเป็น Array ★★★
    if (!empty($insufficient_parts)) {
        return [
            'success' => false,
            'error_type' => 'INSUFFICIENT_STOCK', 
            'details' => $insufficient_parts
        ];
    }

    // --- 5. ขั้นตอนการตัดสต็อก (จะทำงานก็ต่อเมื่อทุกรายการมีสต็อกพอ) ---
    foreach ($parts_to_deduct as $part) {
        $part_id_str = (string)$part['part_id'];
        $qty_to_deduct = $part['required_qty'];

        $stmt_deduct = $conn->prepare("UPDATE wip_inventory SET quantity = quantity - ? WHERE part_id = ?");
        if (!$stmt_deduct) {
            return ['success' => false, 'message' => 'Error preparing stock deduction: ' . $conn->error];
        }
        
        $stmt_deduct->bind_param("is", $qty_to_deduct, $part_id_str);
        if (!$stmt_deduct->execute()) {
             return ['success' => false, 'message' => 'ไม่สามารถตัดสต็อกสำหรับชิ้นส่วน ID ' . $part_id_str . ' ได้: ' . $stmt_deduct->error];
        }
        $stmt_deduct->close();
    }

    return ['success' => true, 'message' => 'ตัดสต็อกเรียบร้อยแล้ว'];
}
?>
