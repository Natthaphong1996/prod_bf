<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ดึงข้อมูลทั้งหมดจากตาราง nail
$sql = "SELECT nail_code, nail_pcsperroll, nail_rollperbox FROM nail";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-custom {
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .container {
            margin-top: 50px;
        }
        .card-title-custom {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .card-text {
            font-size: 1rem;
        }
        .btn-custom {
            font-size: 16px;
            font-weight: bold;
        }
        .highlighted-title {
            background-color: #25d7fd; /* สีพื้นหลังที่ต้องการ */
            padding: 10px;
            border-radius: 5px;
            color: #333; /* สีตัวหนังสือ */
            text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">รายการตะปู</h2>

    <div class="row">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card card-custom">
                        <div class="card-body">
                            <h5 class="highlighted-title card-title card-title-custom">รหัสตะปู: <?php echo $row['nail_code']; ?></h5>
                            <p class="card-text">
                                <strong>จำนวนตะปูต่อม้วน:</strong> <?php echo $row['nail_pcsperroll']; ?><br>
                                <strong>จำนวนม้วนต่อกล่อง:</strong> <?php echo $row['nail_rollperbox']; ?>
                            </p>
                            <div class="text-center">
                                <a href="edit_nail.php?code=<?php echo $row['nail_code']; ?>" class="btn btn-outline-warning btn-sm">แก้ไข</a>
                                <a href="delete_nail.php?code=<?php echo $row['nail_code']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('คุณแน่ใจว่าต้องการลบข้อมูลตะปูนี้หรือไม่?');">ลบ</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center">ไม่พบข้อมูล</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
