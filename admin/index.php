<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_email') {
        $custom_email = trim($_POST['custom_email']);
        $domain = EMAIL_DOMAIN;

        if (!empty($custom_email) && preg_match('/^[a-z0-9_-]+$/i', $custom_email)) {
            $full_email = $custom_email . $domain;

            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("INSERT INTO generated_emails (email_address, created_at) VALUES (:email, NOW())");
                $stmt->execute(['email' => $full_email]);
                $success_msg = "Tạo email thành công: $full_email";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error_msg = "Email đã tồn tại!";
                } else {
                    $error_msg = "Lỗi: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "Định dạng email không hợp lệ! Chỉ dùng chữ, số, dấu gạch ngang (-) và gạch dưới (_).";
        }
    } elseif ($action === 'delete_email') {
        $email_id = isset($_POST['email_id']) ? $_POST['email_id'] : null;
        if ($email_id) {
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("DELETE FROM emails WHERE id = :id");
                $stmt->execute(['id' => $email_id]);
                $success_msg = "Xoá email thành công!";
            } catch (PDOException $e) {
                $error_msg = "Lỗi xoá email: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_generated_email') {
        $generated_email = isset($_POST['generated_email']) ? $_POST['generated_email'] : null;
        if ($generated_email) {
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("DELETE FROM generated_emails WHERE email_address = :email");
                $stmt->execute(['email' => $generated_email]);
                $success_msg = "Xoá email đã tạo thành công!";
            } catch (PDOException $e) {
                $error_msg = "Lỗi xoá email đã tạo: " . $e->getMessage();
            }
        }
    } elseif ($action === 'bulk_delete_emails') {
        $selected_emails = isset($_POST['selected_emails']) && is_array($_POST['selected_emails']) ? $_POST['selected_emails'] : [];
        if (!empty($selected_emails)) {
            $conn = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($selected_emails), '?'));
            try {
                $stmt = $conn->prepare("DELETE FROM emails WHERE id IN ($placeholders)");
                $stmt->execute($selected_emails);
                $success_msg = "Đã xoá " . count($selected_emails) . " email đến.";
            } catch (PDOException $e) {
                $error_msg = "Lỗi xoá email đến: " . $e->getMessage();
            }
        }
    } elseif ($action === 'bulk_delete_generated_emails') {
        $selected_generated = isset($_POST['selected_generated']) && is_array($_POST['selected_generated']) ? $_POST['selected_generated'] : [];
        if (!empty($selected_generated)) {
            $conn = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($selected_generated), '?'));
            try {
                $stmt = $conn->prepare("DELETE FROM generated_emails WHERE id IN ($placeholders)");
                $stmt->execute($selected_generated);
                $success_msg = "Đã xoá " . count($selected_generated) . " email đã tạo.";
            } catch (PDOException $e) {
                $error_msg = "Lỗi xoá email đã tạo: " . $e->getMessage();
            }
        }
    }
}

$conn = getDBConnection();

$stats = [
    'total_emails' => $conn->query("SELECT COUNT(*) FROM emails")->fetchColumn(),
    'total_generated' => $conn->query("SELECT COUNT(*) FROM generated_emails")->fetchColumn(),
    'today_emails' => $conn->query("SELECT COUNT(*) FROM emails WHERE DATE(received_at) = CURDATE()")->fetchColumn(),
];

$search_generated = isset($_GET['search_generated']) ? trim($_GET['search_generated']) : '';
$search_inbox = isset($_GET['search_inbox']) ? trim($_GET['search_inbox']) : '';

$gen_page = isset($_GET['gen_page']) ? (int)$_GET['gen_page'] : 1;
if ($gen_page < 1) $gen_page = 1;
$inbox_page = isset($_GET['inbox_page']) ? (int)$_GET['inbox_page'] : 1;
if ($inbox_page < 1) $inbox_page = 1;

$per_page_generated = 20;
$per_page_inbox = 20;

$generated_count_sql = "SELECT COUNT(*) FROM generated_emails";
$params_generated = [];
if ($search_generated !== '') {
    $generated_count_sql .= " WHERE email_address LIKE :search_generated";
    $params_generated[':search_generated'] = '%' . $search_generated . '%';
}
$stmt = $conn->prepare($generated_count_sql);
$stmt->execute($params_generated);
$total_generated_filtered = (int)$stmt->fetchColumn();
$total_gen_pages = max(1, (int)ceil($total_generated_filtered / $per_page_generated));
if ($gen_page > $total_gen_pages) $gen_page = $total_gen_pages;
$offset_generated = ($gen_page - 1) * $per_page_generated;

$generated_query = "SELECT *, UNIX_TIMESTAMP(created_at) as timestamp FROM generated_emails";
if ($search_generated !== '') {
    $generated_query .= " WHERE email_address LIKE :search_generated";
}
$generated_query .= " ORDER BY created_at DESC LIMIT " . $offset_generated . ", " . $per_page_generated;
$stmt = $conn->prepare($generated_query);
if ($search_generated !== '') {
    $stmt->bindValue(':search_generated', '%' . $search_generated . '%', PDO::PARAM_STR);
}
$stmt->execute();
$generated_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inbox_count_sql = "SELECT COUNT(*) FROM emails";
$params_inbox = [];
if ($search_inbox !== '') {
    $inbox_count_sql .= " WHERE to_email LIKE :search_inbox OR from_email LIKE :search_inbox OR subject LIKE :search_inbox";
    $params_inbox[':search_inbox'] = '%' . $search_inbox . '%';
}
$stmt = $conn->prepare($inbox_count_sql);
$stmt->execute($params_inbox);
$total_inbox_filtered = (int)$stmt->fetchColumn();
$total_inbox_pages = max(1, (int)ceil($total_inbox_filtered / $per_page_inbox));
if ($inbox_page > $total_inbox_pages) $inbox_page = $total_inbox_pages;
$offset_inbox = ($inbox_page - 1) * $per_page_inbox;

$inbox_query = "SELECT *, UNIX_TIMESTAMP(received_at) as timestamp FROM emails";
if ($search_inbox !== '') {
    $inbox_query .= " WHERE to_email LIKE :search_inbox OR from_email LIKE :search_inbox OR subject LIKE :search_inbox";
}
$inbox_query .= " ORDER BY received_at DESC LIMIT " . $offset_inbox . ", " . $per_page_inbox;
$stmt = $conn->prepare($inbox_query);
if ($search_inbox !== '') {
    $stmt->bindValue(':search_inbox', '%' . $search_inbox . '%', PDO::PARAM_STR);
}
$stmt->execute();
$recent_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

function build_query_link($params_override = [])
{
    $params = $_GET;
    foreach ($params_override as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $query = http_build_query($params);
    return $query ? ('?' . $query) : '?';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng quản trị - Email tạm thời</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }

        .stats-card-icon {
            font-size: 2.2rem;
            opacity: 0.3;
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .table-container {
            background: #ffffff;
            border-radius: .5rem;
            padding: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
        }

        .btn-copy-small {
            border: none;
            background: transparent;
            padding: 0 .25rem;
        }

        .btn-copy-small i {
            font-size: 1rem;
        }

        .pagination {
            margin-bottom: 0;
        }

        .table thead th {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mb-5">
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Tổng email đến</h6>
                            <div class="stats-number text-primary"><?php echo $stats['total_emails']; ?></div>
                        </div>
                        <div class="stats-card-icon text-primary">
                            <i class="bi bi-inbox-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Email hôm nay</h6>
                            <div class="stats-number text-success"><?php echo $stats['today_emails']; ?></div>
                        </div>
                        <div class="stats-card-icon text-success">
                            <i class="bi bi-calendar-day-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Email đã tạo</h6>
                            <div class="stats-number text-warning"><?php echo $stats['total_generated']; ?></div>
                        </div>
                        <div class="stats-card-icon text-warning">
                            <i class="bi bi-envelope-plus-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex align-items-center">
                <i class="bi bi-envelope-fill me-2"></i>
                <h5 class="mb-0">Quản lý email</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card h-100 border">
                            <div class="card-body text-center">
                                <h6 class="mb-1">Tạo email đơn</h6>
                                <p class="text-muted small mb-3">Tạo một email tuỳ chỉnh</p>
                                <form method="POST" class="text-start">
                                    <input type="hidden" name="action" value="create_email">
                                    <div class="mb-3">
                                        <label class="form-label">Địa chỉ email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                                            <input type="text" name="custom_email" class="form-control" placeholder="ten-email" required pattern="[a-zA-Z0-9_-]+" title="Chỉ dùng chữ, số, dấu gạch ngang (-) và gạch dưới (_)">
                                            <span class="input-group-text"><?php echo EMAIL_DOMAIN; ?></span>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle-fill me-1"></i>Tạo email
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100 text-white" style="background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h6 class="mb-1">Generate hàng loạt</h6>
                                <p class="small mb-3" style="opacity: .9;">Tạo tối đa 1000 email trong một lần</p>
                                <a href="generate_emails.php" class="btn btn-light fw-semibold">
                                    <i class="bi bi-rocket-takeoff-fill me-1"></i>Bắt đầu generate
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100 text-white" style="background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h6 class="mb-1">Xoá email</h6>
                                <p class="small mb-3" style="opacity: .9;">Xoá email theo lô hoặc từng email</p>
                                <a href="delete_emails.php" class="btn btn-light fw-semibold">
                                    <i class="bi bi-trash3-fill me-1"></i>Quản lý xoá email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex align-items-center">
                <i class="bi bi-list-check me-2"></i>
                <h5 class="mb-0">Danh sách email đã tạo</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search_generated" class="form-control" placeholder="Tìm theo địa chỉ email..." value="<?php echo htmlspecialchars($search_generated); ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <?php if ($search_inbox !== ''): ?>
                                <input type="hidden" name="search_inbox" value="<?php echo htmlspecialchars($search_inbox); ?>">
                            <?php endif; ?>
                            <?php if ($inbox_page > 1): ?>
                                <input type="hidden" name="inbox_page" value="<?php echo (int)$inbox_page; ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>Tìm kiếm
                            </button>
                            <?php if ($search_generated !== ''): ?>
                                <a href="<?php echo htmlspecialchars(build_query_link(['search_generated' => null, 'gen_page' => 1])); ?>" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($search_generated !== ''): ?>
                        <p class="mt-2 text-muted small">
                            Tìm thấy <?php echo $total_generated_filtered; ?> kết quả cho "<strong><?php echo htmlspecialchars($search_generated); ?></strong>"
                        </p>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                    <form method="POST" id="form_bulk_generated" onsubmit="return confirm('Xoá các email đã chọn?');">
                        <input type="hidden" name="action" value="bulk_delete_generated_emails">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px;" class="text-center">
                                            <input type="checkbox" id="check_all_generated">
                                        </th>
                                        <th>Email</th>
                                        <th>Thời gian tạo</th>
                                        <th class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generated_emails as $email): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="check-generated" name="selected_generated[]" value="<?php echo (int)$email['id']; ?>">
                                            </td>
                                            <td class="fw-semibold">
                                                <?php echo htmlspecialchars($email['email_address']); ?>
                                                <button type="button" class="btn-copy-small" onclick="copyToClipboard('<?php echo htmlspecialchars($email['email_address'], ENT_QUOTES); ?>')">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </td>
                                            <td class="local-time" data-timestamp="<?php echo htmlspecialchars($email['created_at']); ?>" data-unix="<?php echo $email['timestamp']; ?>">
                                                <?php echo date('d/m/Y H:i', strtotime($email['created_at'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="../index.php?email=<?php echo urlencode($email['email_address']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem inbox
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-generated-single" data-email="<?php echo htmlspecialchars($email['email_address'], ENT_QUOTES); ?>">
                                                        <i class="bi bi-trash3-fill me-1"></i>Xoá
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($generated_emails)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Chưa có email nào được tạo.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="submit" class="btn btn-danger btn-sm" <?php echo empty($generated_emails) ? 'disabled' : ''; ?>>
                                <i class="bi bi-trash3-fill me-1"></i>Xoá các email đã chọn
                            </button>
                            <nav aria-label="Phân trang email đã tạo">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $gen_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $gen_page > 1 ? htmlspecialchars(build_query_link(['gen_page' => $gen_page - 1])) : '#'; ?>">«</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_gen_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $gen_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo htmlspecialchars(build_query_link(['gen_page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $gen_page >= $total_gen_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $gen_page < $total_gen_pages ? htmlspecialchars(build_query_link(['gen_page' => $gen_page + 1])) : '#'; ?>">»</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex align-items-center">
                <i class="bi bi-inbox me-2"></i>
                <h5 class="mb-0">Email đến mới nhất</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search_inbox" class="form-control" placeholder="Tìm theo email đến, email gửi hoặc tiêu đề..." value="<?php echo htmlspecialchars($search_inbox); ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <?php if ($search_generated !== ''): ?>
                                <input type="hidden" name="search_generated" value="<?php echo htmlspecialchars($search_generated); ?>">
                            <?php endif; ?>
                            <?php if ($gen_page > 1): ?>
                                <input type="hidden" name="gen_page" value="<?php echo (int)$gen_page; ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>Tìm kiếm
                            </button>
                            <?php if ($search_inbox !== ''): ?>
                                <a href="<?php echo htmlspecialchars(build_query_link(['search_inbox' => null, 'inbox_page' => 1])); ?>" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($search_inbox !== ''): ?>
                        <p class="mt-2 text-muted small">
                            Tìm thấy <?php echo $total_inbox_filtered; ?> kết quả cho "<strong><?php echo htmlspecialchars($search_inbox); ?></strong>"
                        </p>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                    <form method="POST" id="form_bulk_inbox" onsubmit="return confirm('Xoá các email đến đã chọn?');">
                        <input type="hidden" name="action" value="bulk_delete_emails">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px;" class="text-center">
                                            <input type="checkbox" id="check_all_inbox">
                                        </th>
                                        <th>Đến</th>
                                        <th>Từ</th>
                                        <th>Tiêu đề</th>
                                        <th>Thời gian nhận</th>
                                        <th class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_emails as $email): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="check-inbox" name="selected_emails[]" value="<?php echo (int)$email['id']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($email['to_email']); ?></td>
                                            <td><?php echo htmlspecialchars($email['from_email']); ?></td>
                                            <td><?php echo htmlspecialchars($email['subject']); ?></td>
                                            <td class="local-time" data-timestamp="<?php echo htmlspecialchars($email['received_at']); ?>" data-unix="<?php echo $email['timestamp']; ?>">
                                                <?php echo date('d/m/Y H:i', strtotime($email['received_at'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-inbox-single" data-id="<?php echo (int)$email['id']; ?>">
                                                    <i class="bi bi-trash3-fill me-1"></i>Xoá
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_emails)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Chưa có email đến nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="submit" class="btn btn-danger btn-sm" <?php echo empty($recent_emails) ? 'disabled' : ''; ?>>
                                <i class="bi bi-trash3-fill me-1"></i>Xoá các email đã chọn
                            </button>
                            <nav aria-label="Phân trang email đến">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $inbox_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $inbox_page > 1 ? htmlspecialchars(build_query_link(['inbox_page' => $inbox_page - 1])) : '#'; ?>">«</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_inbox_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $inbox_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo htmlspecialchars(build_query_link(['inbox_page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $inbox_page >= $total_inbox_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $inbox_page < $total_inbox_pages ? htmlspecialchars(build_query_link(['inbox_page' => $inbox_page + 1])) : '#'; ?>">»</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Đã sao chép email: ' + text);
            });
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
            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
        }

        function submitSingle(action, data) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const inputAction = document.createElement('input');
            inputAction.type = 'hidden';
            inputAction.name = 'action';
            inputAction.value = action;
            form.appendChild(inputAction);
            for (const key in data) {
                if (Object.prototype.hasOwnProperty.call(data, key)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = data[key];
                    form.appendChild(input);
                }
            }
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const timeElements = document.querySelectorAll('.local-time[data-timestamp]');
            timeElements.forEach(function(element) {
                const timestamp = element.getAttribute('data-timestamp');
                const unixTimestamp = element.getAttribute('data-unix');
                if (timestamp) {
                    element.textContent = formatDateLocal(timestamp, unixTimestamp);
                }
            });

            const checkAllGenerated = document.getElementById('check_all_generated');
            const generatedChecks = document.querySelectorAll('.check-generated');
            if (checkAllGenerated) {
                checkAllGenerated.addEventListener('change', function() {
                    generatedChecks.forEach(function(chk) {
                        chk.checked = checkAllGenerated.checked;
                    });
                });
            }
            generatedChecks.forEach(function(chk) {
                chk.addEventListener('change', function() {
                    if (!chk.checked) {
                        if (checkAllGenerated) checkAllGenerated.checked = false;
                    } else {
                        const allChecked = Array.from(generatedChecks).every(function(c) {
                            return c.checked;
                        });
                        if (checkAllGenerated) checkAllGenerated.checked = allChecked;
                    }
                });
            });

            const checkAllInbox = document.getElementById('check_all_inbox');
            const inboxChecks = document.querySelectorAll('.check-inbox');
            if (checkAllInbox) {
                checkAllInbox.addEventListener('change', function() {
                    inboxChecks.forEach(function(chk) {
                        chk.checked = checkAllInbox.checked;
                    });
                });
            }
            inboxChecks.forEach(function(chk) {
                chk.addEventListener('change', function() {
                    if (!chk.checked) {
                        if (checkAllInbox) checkAllInbox.checked = false;
                    } else {
                        const allCheckedInbox = Array.from(inboxChecks).every(function(c) {
                            return c.checked;
                        });
                        if (checkAllInbox) checkAllInbox.checked = allCheckedInbox;
                    }
                });
            });

            const btnDeleteGeneratedSingle = document.querySelectorAll('.btn-delete-generated-single');
            btnDeleteGeneratedSingle.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const email = btn.getAttribute('data-email');
                    if (confirm('Xoá email đã tạo này?')) {
                        submitSingle('delete_generated_email', {
                            generated_email: email
                        });
                    }
                });
            });

            const btnDeleteInboxSingle = document.querySelectorAll('.btn-delete-inbox-single');
            btnDeleteInboxSingle.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = btn.getAttribute('data-id');
                    if (confirm('Xoá email này?')) {
                        submitSingle('delete_email', {
                            email_id: id
                        });
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>