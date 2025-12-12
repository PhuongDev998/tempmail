<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    $error = 'Không kết nối được database.';
    $conn = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Tên đăng nhập và mật khẩu không được để trống!';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu tối thiểu 6 ký tự!';
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO admin_users (username, password_hash, email, created_at) VALUES (:username, :password_hash, :email, NOW())');
                $stmt->execute([
                    'username' => $username,
                    'password_hash' => $password_hash,
                    'email' => $email !== '' ? $email : null
                ]);
                $success = "Đã thêm admin \"$username\" thành công!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Tên đăng nhập đã tồn tại!';
                } else {
                    $error = 'Có lỗi hệ thống, vui lòng thử lại sau.';
                    error_log('Add admin error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit_admin') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($admin_id <= 0) {
            $error = 'Admin không hợp lệ!';
        } elseif ($username === '') {
            $error = 'Tên đăng nhập không được để trống!';
        } elseif ($password !== '' && strlen($password) < 6) {
            $error = 'Mật khẩu tối thiểu 6 ký tự!';
        } else {
            try {
                $fields = ['username = :username', 'email = :email'];
                $params = [
                    'id' => $admin_id,
                    'username' => $username,
                    'email' => $email !== '' ? $email : null
                ];
                if ($password !== '') {
                    $fields[] = 'password_hash = :password_hash';
                    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql = 'UPDATE admin_users SET ' . implode(', ', $fields) . ' WHERE id = :id';
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $success = 'Đã cập nhật thông tin admin!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Tên đăng nhập đã tồn tại!';
                } else {
                    $error = 'Có lỗi hệ thống, vui lòng thử lại sau.';
                    error_log('Edit admin error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'toggle_status') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            $error = 'Admin không hợp lệ!';
        } elseif ($admin_id == (int)$_SESSION['admin_id']) {
            $error = 'Không thể tự khoá tài khoản của chính mình!';
        } else {
            try {
                $stmt = $conn->prepare('UPDATE admin_users SET is_active = NOT is_active WHERE id = :id');
                $stmt->execute(['id' => $admin_id]);
                $success = 'Đã thay đổi trạng thái admin!';
            } catch (PDOException $e) {
                $error = 'Có lỗi hệ thống, vui lòng thử lại sau.';
                error_log('Toggle admin status error: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete_admin') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            $error = 'Admin không hợp lệ!';
        } elseif ($admin_id == (int)$_SESSION['admin_id']) {
            $error = 'Không thể xoá tài khoản của chính mình!';
        } else {
            try {
                $stmt = $conn->prepare('DELETE FROM admin_users WHERE id = :id');
                $stmt->execute(['id' => $admin_id]);
                $success = 'Đã xoá admin thành công!';
            } catch (PDOException $e) {
                $error = 'Có lỗi hệ thống, vui lòng thử lại sau.';
                error_log('Delete admin error: ' . $e->getMessage());
            }
        }
    }
}

$admins = [];
if ($conn) {
    try {
        $stmt = $conn->query('SELECT * FROM admin_users ORDER BY created_at DESC');
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Không lấy được danh sách admin.';
        error_log('Fetch admins error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý admin - Bảng quản trị</title>
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

        .badge-status-active {
            background-color: rgba(25, 135, 84, .15);
            color: #198754;
        }

        .badge-status-inactive {
            background-color: rgba(220, 53, 69, .15);
            color: #dc3545;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="admin-wrapper mb-5">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4 mt-3">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0 d-flex align-items-center">
                    <i class="bi bi-person-plus-fill me-2 text-primary"></i>Thêm admin mới
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Tên đăng nhập" required pattern="[a-zA-Z0-9_-]+">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Mật khẩu (≥ 6 ký tự)" required minlength="6">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Email (tuỳ chọn)">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle-fill me-1"></i>Thêm admin
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0 d-flex align-items-center">
                    <i class="bi bi-card-list me-2 text-secondary"></i>Danh sách admin
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tên đăng nhập</th>
                                <th>Email</th>
                                <th>Ngày tạo</th>
                                <th>Đăng nhập gần nhất</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Chưa có admin nào.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($admin['username']); ?></span>
                                            <?php if ((int)$admin['id'] === (int)$_SESSION['admin_id']): ?>
                                                <span class="badge bg-success ms-1">
                                                    <i class="bi bi-person-badge-fill me-1"></i>Bạn
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['email'] ?: '-'); ?></td>
                                        <td><?php echo $admin['created_at'] ? date('d/m/Y H:i', strtotime($admin['created_at'])) : '-'; ?></td>
                                        <td><?php echo $admin['last_login'] ? date('d/m/Y H:i', strtotime($admin['last_login'])) : '-'; ?></td>
                                        <td>
                                            <?php if (!empty($admin['is_active'])): ?>
                                                <span class="badge badge-status-active">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-status-inactive">
                                                    <i class="bi bi-x-circle-fill me-1"></i>Đã khoá
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editAdminModal-<?php echo (int)$admin['id']; ?>">
                                                <i class="bi bi-pencil-square me-1"></i>Sửa
                                            </button>
                                            <?php if ((int)$admin['id'] !== (int)$_SESSION['admin_id']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Thay đổi trạng thái admin này?');">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="admin_id" value="<?php echo (int)$admin['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <i class="bi bi-arrow-repeat me-1"></i>
                                                        <?php echo !empty($admin['is_active']) ? 'Khoá' : 'Kích hoạt'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Xoá admin này?');">
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="admin_id" value="<?php echo (int)$admin['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash3-fill me-1"></i>Xoá
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small ms-2">Không thể khoá/xoá tài khoản hiện tại</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($admins)): ?>
            <?php foreach ($admins as $admin): ?>
                <div class="modal fade" id="editAdminModal-<?php echo (int)$admin['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-pencil-square me-2"></i>Sửa admin: <?php echo htmlspecialchars($admin['username']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_admin">
                                <input type="hidden" name="admin_id" value="<?php echo (int)$admin['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Tên đăng nhập</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text"
                                            name="username"
                                            class="form-control"
                                            required
                                            pattern="[a-zA-Z0-9_-]+"
                                            value="<?php echo htmlspecialchars($admin['username']); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                        <input type="email"
                                            name="email"
                                            class="form-control"
                                            value="<?php echo htmlspecialchars($admin['email']); ?>">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Mật khẩu mới</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                        <input type="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Để trống nếu không đổi"
                                            minlength="6">
                                    </div>
                                </div>
                                <div class="form-text">Nếu không nhập mật khẩu mới thì mật khẩu cũ sẽ được giữ nguyên.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save2-fill me-1"></i>Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>