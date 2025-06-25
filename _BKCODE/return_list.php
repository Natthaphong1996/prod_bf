<?php
include('config_db.php');

// กำหนดจำนวนรายการที่จะแสดงในแต่ละหน้า
$items_per_page = 15;

// ตรวจสอบว่าผู้ใช้เลือกหน้าที่เท่าไหร่
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
//$offset = ($current_page - 1) * $items_per_page;  // การคำนวณ OFFSET สำหรับ SQL
$offset = 0;  // การคำนวณ OFFSET สำหรับ SQL

// ค้นหาข้อมูล JOB ที่มีใน wood_issue
$search_query = "";
if (isset($_POST['search']) && !empty($_POST['search'])) {
    $search_value = $_POST['search'];
    // ค้นหาตาม job_id และสถานะที่กำหนด
    $jobs_query = "SELECT DISTINCT job_id FROM wood_issue WHERE (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ') AND job_id like '%$search_value%' ORDER BY creation_date DESC LIMIT $items_per_page OFFSET $offset";
    
    // คำนวณจำนวน JOB ที่ตรงกับคำค้นหา
    $total_jobs_query = "SELECT COUNT(DISTINCT job_id) AS total FROM wood_issue WHERE job_id like '%$search_value%' AND (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ')";
} else {
    // หากไม่มีการค้นหา ให้แสดงข้อมูลทั้งหมดที่ตรงกับสถานะ
    $jobs_query = "SELECT DISTINCT job_id FROM wood_issue WHERE issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ' ORDER BY creation_date DESC LIMIT $items_per_page OFFSET $offset";
    
    // คำนวณจำนวนทั้งหมดของ JOB ที่ตรงกับสถานะ
    $total_jobs_query = "SELECT COUNT(DISTINCT job_id) AS total FROM wood_issue WHERE issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ'";
}

// คำสั่ง SQL สำหรับดึงข้อมูล JOB ตามที่ค้นหาและจำกัดจำนวนผลลัพธ์
$jobs_result = mysqli_query($conn, $jobs_query);

// คำนวณจำนวนทั้งหมดของ JOB
$total_jobs_result = mysqli_query($conn, $total_jobs_query);
$total_jobs_row = mysqli_fetch_assoc($total_jobs_result);
$total_jobs = $total_jobs_row['total'];

// คำนวณจำนวนหน้าทั้งหมด
$total_pages = ceil($total_jobs / $items_per_page);  // คำนวณจำนวนหน้าตามจำนวน JOB ที่ค้นพบ
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- เพิ่มการตั้งค่าการแสดงผลบนมือถือ -->
    <title>รายการการรับไม้คืน</title>
    <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" /> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<?php include('navbar.php'); ?>

<div class="container">
    <br>
    <h2>รายการการรับไม้คืน</h2>

    <!-- ช่องค้นหา -->
    <form method="POST" class="mb-3">
        <div class="form-group">
            <label for="search" class="mb-3">ค้นหาหมายเลข JOB</label><br>
            <input type="text" class="form-control" id="search" name="search" placeholder="ใส่หมายเลข JOB" value="<?php echo isset($search_value) ? $search_value : ''; ?>">
        </div>
        <button type="submit" class="btn btn-primary mt-3">ค้นหา</button>
    </form>

    <!-- รายการ JOB ที่มีใน wood_issue -->
    <div class="row">
        <?php
        if (mysqli_num_rows($jobs_result) > 0) {
            while ($row = mysqli_fetch_assoc($jobs_result)) {
                $job_id = $row['job_id'];

                // ดึงข้อมูล issue_status จาก wood_issue ตาม job_id
                $status_query = "SELECT issue_status FROM wood_issue WHERE job_id = '$job_id' LIMIT 1";
                $status_result = mysqli_query($conn, $status_query);
                $status_row = mysqli_fetch_assoc($status_result);
                $issue_status = $status_row['issue_status']; // กำหนดค่าให้กับ issue_status

                // เลือกไอคอนและสีตามสถานะ
                switch ($issue_status) {
                    case 'สั่งไม้':
                        $icon = 'fas fa-cogs text-warning';  // ไอคอนที่ใช้สำหรับ 'สั่งไม้' สีเหลือง
                        break;
                    case 'กำลังเตรียมไม้':
                        $icon = 'fas fa-box-open text-info';  // ไอคอนที่ใช้สำหรับ 'กำลังเตรียมไม้' สีฟ้า
                        break;
                    case 'รอเบิก':
                        $icon = 'fas fa-clock text-secondary';  // ไอคอนที่ใช้สำหรับ 'รอเบิก' สีเทา
                        break;
                    case 'เบิกแล้ว':
                        $icon = 'fas fa-info-circle text-info';  // ไอคอนที่ใช้สำหรับ 'เบิกแล้ว' สีเขียว
                        break;
                    case 'ยกเลิก':
                        $icon = 'fas fa-ban text-danger';  // ไอคอนที่ใช้สำหรับ 'ยกเลิก' สีแดง
                        break;
                    case 'เสร็จเรียบร้อย':
                        $icon = 'fas fa-thumbs-up text-success';  // ไอคอนที่ใช้สำหรับ 'เสร็จเรียบร้อย' สีเขียวเข้ม
                        break;
                    default:
                        $icon = 'fas fa-question-circle text-muted';  // ไอคอนสำหรับกรณีที่ไม่มีสถานะ
                        break;
                }

                // ดึงข้อมูลจาก wood_issue ตาม job_id
                $product_codes_query = "SELECT product_code FROM wood_issue WHERE job_id = '$job_id'";
                $product_codes_result = mysqli_query($conn, $product_codes_query);

                // ดึงข้อมูลจาก prod_list ตาม product_code
                $prod_details = [];
                while ($product_row = mysqli_fetch_assoc($product_codes_result)) {
                    $product_code = $product_row['product_code'];

                    // ดึงรายละเอียดจาก prod_list ตาม product_code
                    $prod_query = "SELECT prod_partno, code_cus_size, prod_description FROM prod_list WHERE prod_code = '$product_code'";
                    $prod_result = mysqli_query($conn, $prod_query);
                    if ($prod_row = mysqli_fetch_assoc($prod_result)) {
                        $prod_details[] = [
                            'prod_partno' => $prod_row['prod_partno'],
                            'code_cus_size' => $prod_row['code_cus_size'],
                            'prod_description' => $prod_row['prod_description']
                        ];
                    }
                }
                ?>

                <!-- ปรับการแสดงผลให้รองรับมือถือ -->
                <div class="col-md-4 col-sm-12 mb-4"> <!-- เพิ่ม col-sm-12 เพื่อให้บนมือถือแสดงในคอลัมน์เดียว -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                หมายเลข JOB : <?php echo $job_id; ?>
                            </h5>

                            <!-- แสดงข้อมูล prod_details -->
                            <?php foreach ($prod_details as $prod) { ?>
                                <p class="card-text"><strong>สถานะ : <i class="<?php echo $icon; ?>"></i></strong> <?php echo $issue_status; ?></p>
                                <p class="card-text"><strong>PRODUCT CODE FG : </strong> <?php echo $product_code; ?></p>
                                <p class="card-text"><strong>Part NO. : </strong> <?php echo $prod['prod_partno']; ?></p>
                                <p class="card-text"><strong>Code พิเศษ : </strong> <?php echo $prod['code_cus_size']; ?></p>
                                <p class="card-text"><strong>คำอธิบาย : </strong> <?php echo $prod['prod_description']; ?></p>
                            <?php } ?>

                            <a href="return_wood.php?job_code=<?php echo $job_id; ?>" class="btn btn-success">คืนไม้</a>

                        </div>
                    </div>
                </div>

                <?php
            }
        } else {
            echo "<p>ไม่พบข้อมูลที่ค้นหา</p>";
        }
        ?>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation example">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">ก่อนหน้า</a>
            </li>

            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php } ?>

            <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">ถัดไป</a>
            </li>
        </ul>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
