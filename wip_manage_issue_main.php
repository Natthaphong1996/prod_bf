<?php
// ไฟล์: wip_manage_issue_main.php
session_start();
include 'config_db.php';

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}
include 'navbar.php';

// --- ส่วนของการค้นหาและแบ่งหน้า (Pagination) ---
$search_query = isset($_GET['search_query']) ? $conn->real_escape_string(trim($_GET['search_query'])) : '';
$status_filter_request = isset($_GET['status_filter']) ? $conn->real_escape_string(trim($_GET['status_filter'])) : 'all';

$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- สร้างเงื่อนไข WHERE และ Binding Parameters แบบไดนามิก ---
$where_conditions = [];
$bind_types = '';
$bind_values = [];

if ($status_filter_request === 'all') {
    $where_conditions[] = "issue_status IN ('สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก', 'เบิกแล้ว', 'ยกเลิก')";
} else {
    $where_conditions[] = "issue_status = ?";
    $bind_types .= 's';
    $bind_values[] = $status_filter_request;
}

if (!empty($search_query)) {
    $where_conditions[] = "(job_id LIKE ? OR product_code LIKE ?)";
    $bind_types .= 'ss';
    $search_param = "%$search_query%";
    $bind_values[] = $search_param;
    $bind_values[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// --- การนับจำนวนข้อมูลทั้งหมดสำหรับ Pagination ---
$sql_count = "SELECT COUNT(*) FROM wood_issue " . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if (!empty($bind_types)) {
    $stmt_count->bind_param($bind_types, ...$bind_values);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);
$stmt_count->close();

// --- การดึงข้อมูลมาแสดงผลตามหน้าปัจจุบัน ---
$sql = "SELECT issue_id, job_id, product_code, quantity, want_receive, create_by, issued_by, issue_date, issue_status FROM wood_issue " . $where_clause . " ORDER BY FIELD(issue_status, 'รอเบิก', 'กำลังเตรียมไม้', 'สั่งไม้', 'เบิกแล้ว', 'ยกเลิก'), issue_id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

$final_bind_types = $bind_types . 'ii';
$final_bind_values = [...$bind_values, $limit, $offset];

if (!empty($bind_types)) {
    $stmt->bind_param($final_bind_types, ...$final_bind_values);
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
    <title>จัดการใบเบิกไม้ (WIP)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; }
        .container { background-color: #ffffff; border-radius: 8px; padding: 2rem; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .filter-form { border: 1px solid #dee2e6; padding: 1.5rem; border-radius: .5rem; background-color: #f8f9fa; }
        .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
        .badge.bg-status-สั่งไม้ { background-color: #0dcaf0 !important; color: #000 !important; }
        .badge.bg-status-กำลังเตรียมไม้ { background-color: #ffc107 !important; color: #000 !important; }
        .badge.bg-status-รอเบิก { background-color: #fd7e14 !important; }
        .badge.bg-status-เบิกแล้ว { background-color: #0d6efd !important; }
        .badge.bg-status-ยกเลิก { background-color: #dc3545 !important; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4"><i class="bi bi-card-checklist"></i> จัดการใบเบิกไม้ (WIP)</h1>
        
        <form method="get" class="filter-form mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search_query" class="form-label">ค้นหา Job/รหัสสินค้า</label>
                    <input type="text" name="search_query" id="search_query" class="form-control" placeholder="กรอกข้อมูล..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
                <div class="col-md-5">
                    <label for="status_filter" class="form-label">กรองตามสถานะ</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="all" <?= $status_filter_request == 'all' ? 'selected' : '' ?>>-- ทุกสถานะที่เกี่ยวข้อง --</option>
                        <option value="สั่งไม้" <?= $status_filter_request == 'สั่งไม้' ? 'selected' : '' ?>>สั่งไม้</option>
                        <option value="กำลังเตรียมไม้" <?= $status_filter_request == 'กำลังเตรียมไม้' ? 'selected' : '' ?>>กำลังเตรียมไม้</option>
                        <option value="รอเบิก" <?= $status_filter_request == 'รอเบิก' ? 'selected' : '' ?>>รอเบิก</option>
                        <option value="เบิกแล้ว" <?= $status_filter_request == 'เบิกแล้ว' ? 'selected' : '' ?>>เบิกแล้ว</option>
                        <option value="ยกเลิก" <?= $status_filter_request == 'ยกเลิก' ? 'selected' : '' ?>>ยกเลิก</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-filter"></i> กรองข้อมูล</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr class="text-center">
                        <th>Job ID</th>
                        <th>รหัสสินค้า</th>
                        <th>จำนวน</th>
                        <th>สถานะ</th>
                        <th>ผู้เบิก/วันที่เบิก</th>
                        <th>เอกสาร</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if ($result && $result->num_rows > 0) : ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr id="row-<?= $row['issue_id'] ?>">
                                <td><?= htmlspecialchars($row['job_id']) ?></td>
                                <td><?= htmlspecialchars($row['product_code']) ?></td>
                                <td><?= htmlspecialchars($row['quantity']) ?></td>
                                <td class="status-cell">
                                    <span class="badge bg-status-<?= str_replace(' ', '-', $row['issue_status']) ?> p-2 fs-6"><?= htmlspecialchars($row['issue_status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($row['issued_by'])): ?>
                                        <i class="bi bi-person-check-fill text-success"></i> <?= htmlspecialchars($row['issued_by']) ?><br>
                                        <small class="text-muted"><i class="bi bi-clock"></i> <?= date('d-m-Y H:i', strtotime($row['issue_date'])) ?></small>
                                    <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['issue_status'] != 'ยกเลิก'): ?>
                                        <!-- ★★★ แก้ไขตรงนี้: เปลี่ยนจาก id เป็น issue_id ★★★ -->
                                        <a href="generate_issued_pdf.php?issue_id=<?= $row['issue_id'] ?>" target="_blank" class="btn btn-secondary btn-sm">
                                            <i class="bi bi-printer"></i> PDF
                                        </a>
                                    <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                    <?php 
                                        switch ($row['issue_status']) {
                                            case 'สั่งไม้':
                                                echo '<button type="button" class="btn btn-primary" onclick="confirmStatusChange('.$row['issue_id'].', \'สั่งไม้\', \'กำลังเตรียมไม้\')"><i class="bi bi-box-seam"></i> เป็นกำลังเตรียมไม้</button>';
                                                break;
                                            case 'กำลังเตรียมไม้':
                                                echo '<button type="button" class="btn btn-primary" onclick="confirmStatusChange('.$row['issue_id'].', \'กำลังเตรียมไม้\', \'รอเบิก\')"><i class="bi bi-hourglass-split"></i> เป็นรอเบิก</button>';
                                                break;
                                            case 'รอเบิก':
                                                echo '<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#issuedByModal" data-issue-id="'.$row['issue_id'].'"><i class="bi bi-check-circle"></i> เบิกแล้ว</button>';
                                                break;
                                        }
                                        if (in_array($row['issue_status'], ['สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก'])) {
                                            echo '<button type="button" class="btn btn-danger mt-1" onclick="confirmStatusChange('.$row['issue_id'].', \''.$row['issue_status'].'\', \'ยกเลิก\')"><i class="bi bi-x-circle"></i> ยกเลิกงาน</button>';
                                        }
                                    ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="7" class="text-center">ไม่พบข้อมูลตามเงื่อนไขที่กำหนด</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php 
            $pagination_params = ['status_filter' => $status_filter_request];
            include 'wip_pagination_template.php'; 
        ?>
    </div>
    
    <div class="modal fade" id="issuedByModal" tabindex="-1" aria-labelledby="issuedByModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="issuedByModalLabel">ยืนยันการเบิก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="issuedByForm" onsubmit="submitIssuedBy(event);">
                        <input type="hidden" id="modal-issue-id-เบิกแล้ว" name="issue_id">
                        <div class="mb-3">
                            <label for="issued_by_name" class="form-label">ชื่อผู้เบิก:</label>
                            <input type="text" class="form-control" id="issued_by_name" name="issued_by" required>
                        </div>
                        <button type="submit" class="d-none">Submit</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="issuedByForm" class="btn btn-success"><i class="bi bi-check-circle-fill"></i> ยืนยันการเบิก</button>
                </div>
            </div>
        </div>
    </div>

     <!-- ... โค้ด HTML ส่วนบนยังคงเหมือนเดิมทั้งหมด ... -->
<?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- ส่วนของ JavaScript ทั้งหมดที่อัปเดตแล้ว ---
        const issuedByModal = document.getElementById('issuedByModal');
        if (issuedByModal) {
            issuedByModal.addEventListener('show.bs.modal', function (event) {
                document.getElementById('issuedByForm').reset();
                const button = event.relatedTarget;
                const issueId = button.getAttribute('data-issue-id');
                issuedByModal.querySelector('#modal-issue-id-เบิกแล้ว').value = issueId;
            });
        }

        function submitIssuedBy(event) {
            event.preventDefault(); 
            const form = document.getElementById('issuedByForm');
            const issueId = form.querySelector('#modal-issue-id-เบิกแล้ว').value;
            const issuedByName = form.querySelector('#issued_by_name').value;

            if (issuedByName.trim() === '') {
                Swal.fire('ข้อผิดพลาด', 'กรุณากรอกชื่อผู้เบิก', 'error');
                return;
            }

            const modalInstance = bootstrap.Modal.getInstance(issuedByModal);
            modalInstance.hide();
            
            showIssueDetailsInModal(issueId, issuedByName);
        }

        function showIssueDetailsInModal(issueId, issuedByName) {
             Swal.fire({
                title: 'กำลังดึงข้อมูลใบเบิก...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'get_issue_details_for_modal.php',
                type: 'POST',
                data: { issue_id: issueId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let partsHtml = '<ul class="list-group list-group-flush text-start mt-3">';
                        data.parts.forEach(part => {
                            const stockColor = parseInt(part.current_stock) < parseInt(part.required_qty) ? 'text-danger fw-bold' : 'text-success';
                            partsHtml += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        ${part.part_code} <small class="text-primary">(${part.part_type})</small><br>
                                        <small class="text-muted">${part.part_size}</small>
                                    </div>
                                    <span class="badge bg-light text-dark p-2 ${stockColor}">
                                        เบิก: ${part.required_qty} / คลัง: ${part.current_stock}
                                    </span>
                                </li>`;
                        });
                        partsHtml += '</ul>';

                        const confirmationHtml = `
                            <div style="text-align: left;">
                                <strong>สินค้า:</strong> ${data.product_code}<br>
                                <strong>ประเภท:</strong> ${data.product_type}<br>
                                <strong>ขนาด:</strong> ${data.product_size}<br>
                                <strong>จำนวนสั่งผลิต:</strong> ${data.order_quantity}
                                <hr>
                                <strong>รายการ Part ที่จะเบิก:</strong>
                                ${partsHtml}
                                <hr>
                                <p class="text-center text-danger fw-bold mt-3">การดำเนินการนี้จะทำการตัดสต็อกวัตถุดิบทันที!</p>
                            </div>`;

                        // --- ★★★ แก้ไข: เปลี่ยน title ให้แสดง Job ID ★★★ ---
                        Swal.fire({
                            title: `ยืนยันการเบิก Job ID: ${data.job_id}?`,
                            html: confirmationHtml,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'ยืนยันการเบิก',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                updateStatus(issueId, 'เบิกแล้ว', issuedByName);
                            }
                        });

                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                    }
                },
                error: function() {
                     Swal.fire('การเชื่อมต่อล้มเหลว', 'ไม่สามารถดึงข้อมูลรายละเอียดใบเบิกได้', 'error');
                }
            });
        }
        
        function confirmStatusChange(issueId, currentStatus, newStatus) {
            let confirmMessage = `คุณต้องการเปลี่ยนสถานะเป็น "${newStatus}" ใช่หรือไม่?`;
            let iconType = 'warning';
            if (newStatus === 'ยกเลิก') {
                confirmMessage += '<br><strong class="text-danger">การดำเนินการนี้ไม่สามารถย้อนกลับได้</strong>';
                iconType = 'error';
            }
            Swal.fire({
                title: 'ยืนยันการเปลี่ยนแปลง',
                html: confirmMessage,
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateStatus(issueId, newStatus);
                }
            });
        }

        function updateStatus(issueId, newStatus, issuedBy = '') {
            Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            let postData = { issue_id: issueId, new_status: newStatus };
            if (issuedBy !== '') {
                postData.issued_by = issuedBy;
            }
            $.ajax({
                url: 'update_issue_status_confirm.php',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: response.message }).then(() => { location.reload(); });
                    } else {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'เกิดข้อผิดพลาด!', 
                            html: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'การเชื่อมต่อล้มเหลว', text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้' });
                }
            });
        }
    </script>
</body>
</html>




