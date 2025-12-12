<?php
$username = $_SESSION['admin_username'] ?? 'Admin';
$current = basename($_SERVER['PHP_SELF']);
function active($file)
{
    return basename($_SERVER['PHP_SELF']) === $file ? 'active fw-semibold' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center fw-semibold" href="index.php">
            <img src="https://cms77.io.vn/uploads/images/triphuong998/IMG102.png" alt="Logo" height="32" class="me-2">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">

                <li class="nav-item me-3">
                    <a class="nav-link text-light <?= active('generate_emails.php') ?>" href="generate_emails.php">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i>Generate Email
                    </a>
                </li>

                <li class="nav-item me-3">
                    <a class="nav-link text-light <?= active('delete_emails.php') ?>" href="delete_emails.php">
                        <i class="bi bi-trash3-fill me-1"></i>Xoá email
                    </a>
                </li>

                <li class="nav-item me-3">
                    <a class="nav-link text-light <?= active('change_password.php') ?>" href="change_password.php">
                        <i class="bi bi-shield-lock-fill me-1"></i>Đổi mật khẩu
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-light d-flex align-items-center <?= in_array($current, ['change_password.php', 'logout.php']) ? 'active fw-semibold' : '' ?>" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($username) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </div>
    </div>
</nav>