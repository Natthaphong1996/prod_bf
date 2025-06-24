<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งค่ารหัสสินค้ามาหรือไม่
if (isset($_GET['code'])) {
    $nail_code = $_GET['code'];

    // ลบข้อมูลออกจากฐานข้อมูล
    $sql = "DELETE FROM nail WHERE nail_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nail_code);

    if ($stmt->execute()) {
        $_SESSION['success'] = "ลบข้อมูลตะปูสำเร็จ!";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบข้อมูล!";
    }
} else {
    $_SESSION['error'] = "ไม่พบรหัสสินค้าที่ต้องการลบ!";
}

header("Location: nail_list.php");
exit();
?>
