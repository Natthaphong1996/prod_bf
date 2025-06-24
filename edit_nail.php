<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งฟอร์มแก้ไขเข้ามาหรือไม่
if (isset($_POST['update_nail'])) {
    $nail_code = $_POST['nail_code'];
    $nail_pcsperroll = $_POST['nail_pcsperroll'];
    $nail_rollperbox = $_POST['nail_rollperbox'];

    // อัปเดตข้อมูลในฐานข้อมูล
    $sql = "UPDATE nail SET nail_pcsperroll = ?, nail_rollperbox = ? WHERE nail_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $nail_pcsperroll, $nail_rollperbox, $nail_code);

    if ($stmt->execute()) {
        $_SESSION['success'] = "แก้ไขข้อมูลสำเร็จ!";
        header("Location: nail_list.php");
        exit();
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล!";
    }
}

// ดึงข้อมูลจากฐานข้อมูลเพื่อนำไปแสดงในฟอร์มแก้ไข
if (isset($_GET['code'])) {
    $nail_code = $_GET['code'];
    $sql = "SELECT * FROM nail WHERE nail_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nail_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $nail = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "ไม่พบข้อมูลตะปู!";
        header("Location: nail_list.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Nail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Edit Nail</h2>

    <!-- แสดงข้อความแจ้งเตือน -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="edit_nail.php" method="POST">
        <div class="mb-3">
            <label for="nail_code" class="form-label">Nail Code</label>
            <input type="text" class="form-control" id="nail_code" name="nail_code" value="<?php echo $nail['nail_code']; ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="nail_pcsperroll" class="form-label">Pcs per Roll</label>
            <input type="number" class="form-control" id="nail_pcsperroll" name="nail_pcsperroll" value="<?php echo $nail['nail_pcsperroll']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="nail_rollperbox" class="form-label">Rolls per Box</label>
            <input type="number" class="form-control" id="nail_rollperbox" name="nail_rollperbox" value="<?php echo $nail['nail_rollperbox']; ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" name="update_nail">Update Nail</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
