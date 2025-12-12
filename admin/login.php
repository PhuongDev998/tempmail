<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$conn = null;

try {
    $conn = getDBConnection();
} catch (Throwable $e) {
    $error = 'Không kết nối được cơ sở dữ liệu: ' . $e->getMessage();
    error_log('DB connection error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        try {
            $stmt = $conn->prepare('SELECT id, username, password_hash, email, is_active FROM admin_users WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'Tài khoản không tồn tại!';
            } elseif ((int)$user['is_active'] !== 1) {
                $error = 'Tài khoản đã bị khoá!';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Mật khẩu không đúng!';
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];

                try {
                    $update = $conn->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = :id');
                    $update->execute(['id' => $user['id']]);
                } catch (PDOException $e) {
                    error_log('Update last_login error: ' . $e->getMessage());
                }

                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Lỗi truy vấn CSDL: ' . $e->getMessage();
            error_log('Login query error: ' . $e->getMessage());
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin</title>
    <link rel="stylesheet" href="admin-style.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>🔐 Đăng nhập Admin</h1>
            <p>Hệ thống Email Tạm Thời</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <input type="text" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary btn-block">Đăng nhập</button>
            </form>

            <div class="login-footer">
                <a href="../index.php">← Quay lại Inbox</a>
            </div>
        </div>
    </div>
</body>

</html>