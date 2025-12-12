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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Vui lòng điền đầy đủ tất cả các trường!';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Mật khẩu mới và xác nhận không trùng khớp!';
    } elseif (strlen($new_password) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự!';
    } else {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE admin_users SET password_hash = :password_hash WHERE id = :id");
                $update->execute([
                    'password_hash' => $new_hash,
                    'id' => $_SESSION['admin_id']
                ]);
                $success = 'Đổi mật khẩu thành công!';
            } else {
                $error = 'Mật khẩu hiện tại không đúng!';
            }
        } catch (PDOException $e) {
            $error = 'Có lỗi hệ thống, vui lòng thử lại sau.';
            error_log("Change password error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }

        .admin-wrapper {
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="admin-wrapper mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0 d-flex align-items-center">
                    <i class="bi bi-key-fill me-2 text-primary"></i>
                    Thay đổi mật khẩu
                </h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="current_password" class="form-control" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-text">Tối thiểu 6 ký tự.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Xác nhận mật khẩu mới</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-repeat me-1"></i>Đổi mật khẩu
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>