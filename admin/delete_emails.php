<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../functions.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = getDBConnection();

    if ($action === 'delete_single') {
        $email_id = intval($_POST['email_id']);
        try {
            $stmt = $conn->prepare("DELETE FROM generated_emails WHERE id = :id");
            $stmt->execute(['id' => $email_id]);
            $success_msg = "Xoá email thành công!";
        } catch (PDOException $e) {
            $error_msg = "Lỗi: " . $e->getMessage();
        }
    } elseif ($action === 'delete_multiple') {
        $email_ids = $_POST['email_ids'] ?? [];
        if (!empty($email_ids)) {
            try {
                $conn->beginTransaction();
                $placeholders = str_repeat('?,', count($email_ids) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM generated_emails WHERE id IN ($placeholders)");
                $stmt->execute($email_ids);
                $deleted_count = $stmt->rowCount();
                $conn->commit();
                $success_msg = "Đã xoá $deleted_count email được chọn!";
            } catch (Exception $e) {
                $conn->rollBack();
                $error_msg = "Lỗi: " . $e->getMessage();
            }
        } else {
            $error_msg = "Hãy chọn ít nhất 1 email để xoá!";
        }
    } elseif ($action === 'delete_all') {
        try {
            $count = $conn->query("SELECT COUNT(*) FROM generated_emails")->fetchColumn();
            $conn->exec("DELETE FROM generated_emails");
            $success_msg = "Đã xoá toàn bộ email ($count email)!";
        } catch (PDOException $e) {
            $error_msg = "Lỗi: " . $e->getMessage();
        }
    } elseif ($action === 'delete_old') {
        $days = intval($_POST['days'] ?? 7);
        try {
            $stmt = $conn->prepare("DELETE FROM generated_emails WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->execute(['days' => $days]);
            $deleted_count = $stmt->rowCount();
            $success_msg = "Đã xoá $deleted_count email cũ hơn $days ngày!";
        } catch (PDOException $e) {
            $error_msg = "Lỗi: " . $e->getMessage();
        }
    } elseif ($action === 'delete_by_pattern') {
        $pattern = trim($_POST['pattern'] ?? '');
        if (!empty($pattern)) {
            try {
                $stmt = $conn->prepare("DELETE FROM generated_emails WHERE email_address LIKE :pattern");
                $stmt->execute(['pattern' => "%$pattern%"]);
                $deleted_count = $stmt->rowCount();
                $success_msg = "Đã xoá $deleted_count email chứa mẫu '$pattern'!";
            } catch (PDOException $e) {
                $error_msg = "Lỗi: " . $e->getMessage();
            }
        } else {
            $error_msg = "Mẫu tìm kiếm không được để trống!";
        }
    }
}

$conn = getDBConnection();
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM generated_emails")->fetchColumn(),
    'today' => $conn->query("SELECT COUNT(*) FROM generated_emails WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'this_week' => $conn->query("SELECT COUNT(*) FROM generated_emails WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'old' => $conn->query("SELECT COUNT(*) FROM generated_emails WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$search_sql = '';
$search_params = [];

if (!empty($search)) {
    $search_sql = "WHERE email_address LIKE :search";
    $search_params[':search'] = "%$search%";
}

$total_stmt = $conn->prepare("SELECT COUNT(*) FROM generated_emails $search_sql");
$total_stmt->execute($search_params);
$total_count = $total_stmt->fetchColumn();
$total_pages = max(1, ceil($total_count / $per_page));

$sql = "SELECT *, UNIX_TIMESTAMP(created_at) AS timestamp FROM generated_emails $search_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
if (!empty($search_params)) {
    $stmt->bindValue(':search', $search_params[':search'], PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$generated_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xoá email - Bảng quản trị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }

        .admin-wrapper {
            max-width: 1100px;
            margin: 0 auto;
        }

        .delete-section {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: .5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
            margin-bottom: 1.5rem;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            text-decoration: none;
        }

        .table-container {
            background: #ffffff;
            border-radius: .5rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="admin-wrapper mb-5">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center text-white" style="background: linear-gradient(135deg,#0d6efd,#6610f2);">
                    <div class="card-body py-3">
                        <div class="small opacity-75">Tổng email</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center text-white" style="background: linear-gradient(135deg,#198754,#20c997);">
                    <div class="card-body py-3">
                        <div class="small opacity-75">Hôm nay</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['today']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center text-white" style="background: linear-gradient(135deg,#fd7e14,#ffc107);">
                    <div class="card-body py-3">
                        <div class="small opacity-75">7 ngày gần đây</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['this_week']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center text-white" style="background: linear-gradient(135deg,#dc3545,#6f42c1);">
                    <div class="card-body py-3">
                        <div class="small opacity-75">Cũ hơn 7 ngày</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['old']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="delete-section">
            <h5 class="mb-3 d-flex align-items-center">
                <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>
                Xoá nhanh
            </h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST" onsubmit="return confirm('Xoá tất cả email cũ hơn 7 ngày?');">
                        <input type="hidden" name="action" value="delete_old">
                        <input type="hidden" name="days" value="7">
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="bi bi-clock-history me-1"></i>Xoá email > 7 ngày
                            <div class="small d-block mt-1">(<?php echo $stats['old']; ?> email)</div>
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" onsubmit="return confirm('Xoá TẤT CẢ email? Thao tác này không thể hoàn tác.');">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i>Xoá toàn bộ email
                            <div class="small d-block mt-1">(<?php echo $stats['total']; ?> email)</div>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="delete-section">
            <h5 class="mb-3 d-flex align-items-center">
                <i class="bi bi-funnel-fill me-2 text-primary"></i>
                Xoá theo mẫu
            </h5>
            <form method="POST" class="row g-2 align-items-center" onsubmit="return confirm('Xoá tất cả email khớp với mẫu này?');">
                <input type="hidden" name="action" value="delete_by_pattern">
                <div class="col-md-8">
                    <input type="text" name="pattern" class="form-control" placeholder="Ví dụ: test, user, .smith" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-trash3 me-1"></i>Xoá theo mẫu
                    </button>
                </div>
            </form>
            <div class="form-text mt-2">
                Ví dụ: "test" sẽ xoá test1@..., test2@..., testing@... và các email chứa chuỗi đó.
            </div>
        </div>

        <div class="delete-section">
            <h5 class="mb-3 d-flex align-items-center">
                <i class="bi bi-list-check me-2 text-secondary"></i>
                Quản lý email (<?php echo $total_count; ?> email)
            </h5>

            <div class="mb-3">
                <form method="GET" class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm email..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete_multiple">

                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                        <i class="bi bi-check2-square me-1"></i>Chọn tất cả
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAll()">
                        <i class="bi bi-square me-1"></i>Bỏ chọn
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Xoá các email đã chọn?');">
                        <i class="bi bi-trash3-fill me-1"></i>Xoá đã chọn
                    </button>
                    <span id="selectedCount" class="ms-2 fw-semibold text-muted"></span>
                </div>

                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="selectAllCheckbox" class="form-check-input" onchange="toggleAll(this)">
                                    </th>
                                    <th>Email</th>
                                    <th>Thời gian tạo</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($generated_emails)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            Không có email nào<?php echo !empty($search) ? " khớp với từ khoá tìm kiếm" : ""; ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($generated_emails as $email): ?>
                                        <tr>
                                            <td class="checkbox-cell">
                                                <input type="checkbox" name="email_ids[]" value="<?php echo $email['id']; ?>" class="form-check-input email-checkbox">
                                            </td>
                                            <td class="fw-semibold">
                                                <?php echo htmlspecialchars($email['email_address']); ?>
                                            </td>
                                            <td class="local-time" data-timestamp="<?php echo htmlspecialchars($email['created_at']); ?>" data-unix="<?php echo $email['timestamp']; ?>">
                                                <?php echo date('d/m/Y H:i', strtotime($email['created_at'])); ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Xoá email này?');">
                                                    <input type="hidden" name="action" value="delete_single">
                                                    <input type="hidden" name="email_id" value="<?php echo $email['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash3 me-1"></i>Xoá
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <?php if ($total_pages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><i class="bi bi-chevron-double-left"></i></a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?>
                                    <span class="page-link"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><i class="bi bi-chevron-double-right"></i></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.email-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.email-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
            updateSelectedCount();
        }

        function deselectAll() {
            const checkboxes = document.querySelectorAll('.email-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.email-checkbox:checked').length;
            const total = document.querySelectorAll('.email-checkbox').length;
            const el = document.getElementById('selectedCount');
            if (!el) return;
            el.textContent = checked > 0 ? `${checked} / ${total} email được chọn` : '';
        }

        function formatDateLocal(dateString, unixTimestamp) {
            let date;
            if (unixTimestamp) {
                date = new Date(unixTimestamp * 1000);
            } else {
                date = new Date(dateString);
            }
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const timeElements = document.querySelectorAll('.local-time[data-timestamp]');
            timeElements.forEach(element => {
                const timestamp = element.getAttribute('data-timestamp');
                const unixTimestamp = element.getAttribute('data-unix');
                if (timestamp) {
                    element.textContent = formatDateLocal(timestamp, unixTimestamp);
                }
            });

            document.querySelectorAll('.email-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>