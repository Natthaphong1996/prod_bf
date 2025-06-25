<?php
// ไฟล์: part_demand_summary.php
session_start();
include 'config_db.php';
include 'navbar.php';

// ตรวจสอบ session ของผู้ใช้
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// --- 1. ดึงข้อมูลใบเบิกทั้งหมดที่อยู่ในสถานะรอผลิต ---
$sql_jobs = "SELECT prod_id, quantity FROM wood_issue WHERE issue_status IN ('สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก')";
$result_jobs = $conn->query($sql_jobs);

$total_part_demand = [];

if ($result_jobs->num_rows > 0) {
    while ($job = $result_jobs->fetch_assoc()) {
        // --- 2. สำหรับแต่ละใบเบิก ดึงข้อมูล BOM ---
        $stmt_bom = $conn->prepare("SELECT parts FROM bom WHERE prod_id = ?");
        if (!$stmt_bom) continue;
        
        $stmt_bom->bind_param("s", $job['prod_id']);
        $stmt_bom->execute();
        $bom_data = $stmt_bom->get_result()->fetch_assoc();
        $stmt_bom->close();

        if ($bom_data && !empty($bom_data['parts'])) {
            $parts_in_bom = json_decode($bom_data['parts'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // --- 3. คำนวณและรวบรวมยอด Part ที่ต้องใช้ทั้งหมด ---
                foreach ($parts_in_bom as $part) {
                    if (!isset($part['part_id']) || (int)$part['quantity'] <= 0) continue;
                    
                    $part_id = $part['part_id'];
                    $required_qty = (int)$part['quantity'] * (int)$job['quantity'];
                    
                    // เพิ่มยอดที่ต้องการเข้าไปใน Array หลัก
                    $total_part_demand[$part_id] = ($total_part_demand[$part_id] ?? 0) + $required_qty;
                }
            }
        }
    }
}

$summary_data = [];
if (!empty($total_part_demand)) {
    // --- 4. ดึงข้อมูลรายละเอียดและสต็อกของ Part ทั้งหมดที่ต้องการ ---
    $all_part_ids = array_keys($total_part_demand);
    $placeholders = implode(',', array_fill(0, count($all_part_ids), '?'));
    
    $sql_details = "SELECT
                        pl.part_id, pl.part_code, pl.part_type,
                        pl.part_thickness, pl.part_width, pl.part_length,
                        wi.quantity AS current_stock
                    FROM part_list AS pl
                    LEFT JOIN wip_inventory AS wi ON CAST(pl.part_id AS CHAR) = wi.part_id
                    WHERE pl.part_id IN ($placeholders)";
                    
    $stmt_details = $conn->prepare($sql_details);
    $stmt_details->bind_param(str_repeat('i', count($all_part_ids)), ...$all_part_ids);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();

    while ($row = $result_details->fetch_assoc()) {
        $part_id = $row['part_id'];
        $total_required = $total_part_demand[$part_id];
        $current_stock = (int)($row['current_stock'] ?? 0);
        $shortage = $total_required - $current_stock;

        $summary_data[] = [
            'part_id' => $part_id,
            'part_code' => $row['part_code'],
            'part_type' => $row['part_type'],
            'part_size' => "{$row['part_thickness']}x{$row['part_width']}x{$row['part_length']}",
            'total_required' => $total_required,
            'current_stock' => $current_stock,
            'shortage' => ($shortage > 0) ? $shortage : 0
        ];
    }
}

// --- 5. จัดการการค้นหา ---
$search_query = isset($_GET['search_query']) ? strtolower(trim($_GET['search_query'])) : '';
if (!empty($search_query)) {
    $summary_data = array_filter($summary_data, function($item) use ($search_query) {
        return strpos(strtolower($item['part_code']), $search_query) !== false || 
               strpos(strtolower($item['part_type']), $search_query) !== false;
    });
}

// --- 6. จัดการการแบ่งหน้า ---
$limit = 25;
$total_rows = count($summary_data);
$total_pages = ceil($total_rows / $limit);
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$paginated_data = array_slice($summary_data, $offset, $limit);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปยอด Part ที่ต้องใช้ทั้งหมด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .container { background-color: #fff; border-radius: 8px; padding: 2rem; margin-top: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .shortage { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-bar-chart-line-fill"></i> สรุปยอด Part ที่ต้องใช้ทั้งหมด</h1>
        </div>

        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search_query" class="form-control" placeholder="ค้นหา รหัสชิ้นส่วน หรือ ประเภท..." value="<?= htmlspecialchars($search_query) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark text-center">
                    <tr>
                        <th>รหัสชิ้นส่วน (Part Code)</th>
                        <th>รายละเอียด</th>
                        <th>จำนวนที่ต้องใช้ทั้งหมด</th>
                        <th>จำนวนในคลัง</th>
                        <th>ยอดที่ต้องสั่งเพิ่ม</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if (!empty($paginated_data)): ?>
                        <?php foreach($paginated_data as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['part_code']) ?></td>
                                <td class="text-start">
                                    <?= htmlspecialchars($item['part_type']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['part_size']) ?></small>
                                </td>
                                <td><?= number_format($item['total_required']) ?></td>
                                <td><?= number_format($item['current_stock']) ?></td>
                                <td class="shortage"><?= number_format($item['shortage']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">ไม่พบข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php include 'wip_pagination_template.php'; ?>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
