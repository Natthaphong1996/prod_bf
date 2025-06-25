<?php
// ไฟล์: wip_inventory.php
session_start();
include 'config_db.php';
// ตรวจสอบ session ของผู้ใช้
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}
include 'navbar.php';

// --- ส่วนของการค้นหาและแบ่งหน้า (Pagination) ---
$search_term = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$limit = 25; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// สร้างเงื่อนไขการค้นหา
$where_clause = '';
if (!empty($search_term)) {
    $where_clause = "WHERE pl.part_code LIKE ? OR pl.part_type LIKE ?";
    $search_param = "%$search_term%";
}

// --- คำสั่ง SQL สำหรับดึงข้อมูล โดย JOIN ตาราง part_list และ wip_inventory ---
$sql_base = "FROM part_list AS pl LEFT JOIN wip_inventory AS wi ON pl.part_id = wi.part_id " . $where_clause;

// นับจำนวนข้อมูลทั้งหมด
$sql_count = "SELECT COUNT(pl.part_id) " . $sql_base;
$stmt_count = $conn->prepare($sql_count);
if (!empty($search_term)) {
    $stmt_count->bind_param("ss", $search_param, $search_param);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);
$stmt_count->close();

// ดึงข้อมูลมาแสดงผล
$sql = "SELECT pl.part_id, pl.part_code, pl.part_type, pl.part_thickness, pl.part_width, pl.part_length, wi.quantity " . $sql_base . " ORDER BY pl.part_id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($search_term)) {
    $stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คลังชิ้นส่วน (WIP Inventory)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .container { background-color: #fff; border-radius: 8px; padding: 2rem; margin-top: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-box-seam"></i> คลังชิ้นส่วน (WIP Inventory)</h1>
        </div>

        <!-- Search Form -->
        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="ค้นหา รหัสชิ้นส่วน หรือ ประเภท..." value="<?= htmlspecialchars($search_term) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
            </div>
        </form>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark text-center">
                    <tr>
                        <th>รหัสชิ้นส่วน (Part Code)</th>
                        <th>ประเภท</th>
                        <th>ขนาด (หนาxกว้างxยาว)</th>
                        <th>จำนวนคงคลัง (Stock)</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['part_code']) ?></td>
                                <td><?= htmlspecialchars($row['part_type']) ?></td>
                                <td><?= htmlspecialchars($row['part_thickness'] . 'x' . $row['part_width'] . 'x' . $row['part_length']) ?></td>
                                <td><strong><?= number_format($row['quantity'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">ไม่พบข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php 
            $pagination_params = ['search' => $search_term];
            include 'wip_pagination_template.php'; 
        ?>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
