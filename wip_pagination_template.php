<?php
// ไฟล์: wip_pagination_template.php
// คำอธิบาย: Template สำหรับแสดงผล Pagination ที่รองรับตัวกรอง
// ตัวแปรที่ต้องการ: $total_pages, $page, $search_query, และ $pagination_params (array)

// สร้าง query string สำหรับ URL ของ pagination
$query_params = [
    'search_query' => $search_query ?? ''
];

// รวมพารามิเตอร์เพิ่มเติมจากหน้าหลัก (เช่น status_filter)
if (isset($pagination_params) && is_array($pagination_params)) {
    $query_params = array_merge($query_params, $pagination_params);
}

// สร้าง string จาก array ของพารามิเตอร์
$http_query = http_build_query($query_params);

?>
<?php if (isset($total_pages) && $total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <!-- Previous Button -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $http_query ?>">ก่อนหน้า</a>
            </li>

            <?php 
            // Logic for Ellipsis Pagination
            $window = 2;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)):
            ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= $http_query ?>"><?= $i ?></a>
                    </li>
            <?php 
                elseif ($i == $page - $window - 1 || $i == $page + $window + 1):
            ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php 
                endif;
            endfor; 
            ?>

            <!-- Next Button -->
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $http_query ?>">ถัดไป</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

