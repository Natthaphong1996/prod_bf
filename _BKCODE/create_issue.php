<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// ตรวจสอบว่า session มีค่า user_id หรือไม่
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in!";
    exit;
}

// รับค่าจากฟอร์ม
$job_id = $_POST['job_id'];  // รับค่า job_id ที่ผู้ใช้กรอกเอง
$job_type = $_POST['job_type'];  
$product_code = $_POST['prod_code'];  // ใช้ product_code แทน
$product_id = $_POST['prod_id'];  // ใช้ product_code แทน
$quantity = $_POST['quantity'];
$wood_wastage = $_POST['wood_wastage'];
$wood_type = $_POST['wood_type'];
$want_receive = $_POST['want_receive']; // วันที่ที่ผู้ใช้เลือก
$remark = $_POST['remark']; // วันที่ที่ผู้ใช้เลือก

if($job_type == 'งานเคลม'||$job_type == 'งานพาเลทไม้อัด'){
    $issue_status = 'เบิกแล้ว';
}else{
    $issue_status = 'รอยืนยันงาน';
}

// ตรวจสอบว่า prod_id เป็นค่าว่างหรือไม่
if (empty($product_id)) {
    echo "<script>
            alert('กรุณากรอกข้อมูล PRODUCT CODE ให้ถูกต้อง');
            window.location.href = 'planning_order.php';
          </script>";
    exit; // หยุดการทำงานที่เหลือ
}

// ดึงข้อมูล `thainame` จาก `prod_user` โดยใช้ `user_id` ที่ได้จาก session
$user_id = $_SESSION['user_id']; // ดึง user_id จาก session

$sql_user = "SELECT thainame FROM prod_user WHERE user_id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id); // ใช้ user_id จาก session
$stmt->execute();
$result_user = $stmt->get_result();
$user = $result_user->fetch_assoc();
$thainame = $user['thainame'];

// ตรวจสอบว่า job_id ซ้ำในฐานข้อมูลหรือไม่
$sql_check = "SELECT COUNT(*) AS count FROM wood_issue WHERE job_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $job_id); // ใช้ job_id ที่รับจากฟอร์ม
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$row_check = $result_check->fetch_assoc();

if ($row_check['count'] > 0) {
    // ถ้า job_id ซ้ำ ให้แสดงข้อความแจ้งเตือน
    echo "<script>
            alert('หมายเลข JOB นี้มีอยู่ในระบบแล้ว กรุณากรอกหมายเลข JOB ใหม่');
            window.location.href = 'planning_order.php';
          </script>";
    exit; // หยุดการทำงานที่เหลือ
}

// บันทึกข้อมูลลงตาราง wood_issue พร้อมกับค่า `create_by`
$query = "INSERT INTO wood_issue (job_id, job_type, prod_id, product_code, quantity, wood_wastage, wood_type, issue_status, want_receive, remark, create_by)
          VALUES ('$job_id', '$job_type', '$product_id', '$product_code', $quantity, $wood_wastage, '$wood_type', '$issue_status', '$want_receive', '$remark', '$thainame')";

if ($conn->query($query) === TRUE) {
    // ดึงค่า issue_id ที่พึ่งบันทึกจากฐานข้อมูล
    $issue_id = $conn->insert_id;

    // ใช้ JavaScript เพื่อเปิด generate_issued_pdf.php ในแท็บใหม่
    echo '<script>window.open("generate_issued_pdf.php?issue_id=' . $issue_id . '", "_blank");</script>';
    echo "<script>
            alert('หมายเลข JOB นี้ถูกบันทึกเรียบร้อย');
            window.location.href = 'planning_order.php';
          </script>";
    exit; // หยุดการทำงานที่เหลือ
} else {
    echo "Error: " . $query . "<br>" . $conn->error;
}

$conn->close();
?>
