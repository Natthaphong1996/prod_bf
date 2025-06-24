<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งฟอร์มเข้ามาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_nail'])) {
    $nail_code = $_POST['nail_code'];
    $nail_pcsperroll = $_POST['nail_pcsperroll'];
    $nail_rollperbox = $_POST['nail_rollperbox'];

    // ตรวจสอบว่า nail_code ซ้ำหรือไม่
    $check_sql = "SELECT COUNT(*) FROM nail WHERE nail_code = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $nail_code);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $_SESSION['error'] = "รหัสตะปูนี้มีอยู่ในระบบแล้ว!";
    } else {
        // เพิ่มข้อมูลตะปูใหม่ลงในฐานข้อมูล
        $sql = "INSERT INTO nail (nail_code, nail_pcsperroll, nail_rollperbox) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $nail_code, $nail_pcsperroll, $nail_rollperbox);

        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มข้อมูลตะปูสำเร็จ!";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล!";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มข้อมูลตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">เพิ่มข้อมูลตะปูใหม่</h2>

    <!-- แสดงข้อความแจ้งเตือน -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php elseif (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มสำหรับเพิ่มข้อมูลตะปู -->
    <form action="add_nail.php" method="POST">
        <div class="mb-3">
            <label for="nail_code" class="form-label">รหัสตะปู</label>
            <input type="text" class="form-control" id="nail_code" name="nail_code" required>
        </div>
        <div class="mb-3">
            <label for="nail_pcsperroll" class="form-label">จำนวนต่อม้วน</label>
            <input type="number" class="form-control" id="nail_pcsperroll" name="nail_pcsperroll">
        </div>
        <div class="mb-3">
            <label for="nail_rollperbox" class="form-label">จำนวนม้วนต่อกล่อง</label>
            <input type="number" class="form-control" id="nail_rollperbox" name="nail_rollperbox">
        </div>
        <button type="submit" class="btn btn-primary" name="add_nail">เพิ่มตะปู</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
