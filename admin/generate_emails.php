<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../functions.php';
require_once '../faker_names.php';

$success_msg = '';
$error_msg = '';
$generated_emails = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_bulk') {
    $count = intval($_POST['count']);
    $prefix = trim($_POST['prefix'] ?? '');
    $use_random = isset($_POST['use_random']);
    $use_faker = isset($_POST['use_faker']);
    $faker_type = $_POST['faker_type'] ?? 'name';

    if ($count < 1 || $count > 1000) {
        $error_msg = "Số lượng email phải từ 1 đến 1000!";
    } else {
        $conn = getDBConnection();
        $domain = EMAIL_DOMAIN;
        $success_count = 0;
        $duplicate_count = 0;
        $faker = null;

        if ($use_faker) {
            $faker = new SimpleFaker();
        }

        try {
            $conn->beginTransaction();

            for ($i = 0; $i < $count; $i++) {
                if ($use_faker && $faker) {
                    $email_local = $faker->generateUsername($faker_type);
                } elseif ($use_random) {
                    $random_string = bin2hex(random_bytes(8));
                    $email_local = $prefix . $random_string;
                } else {
                    $email_local = $prefix . ($i + 1);
                }

                $full_email = $email_local . $domain;

                try {
                    $stmt = $conn->prepare("INSERT INTO generated_emails (email_address, created_at) VALUES (:email, NOW())");
                    $stmt->execute(['email' => $full_email]);
                    $generated_emails[] = $full_email;
                    $success_count++;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $duplicate_count++;
                        if ($use_faker && $i < $count - 1) {
                            $i--;
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            $conn->commit();

            if ($success_count > 0) {
                $success_msg = "Tạo thành công $success_count email!";
                if ($duplicate_count > 0) {
                    $success_msg .= " ($duplicate_count email đã tồn tại trước đó)";
                }
            } else {
                $error_msg = "Không có email nào được tạo. Tất cả email đã tồn tại.";
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error_msg = "Lỗi: " . $e->getMessage();
        }
    }
}

$conn = getDBConnection();
$total_generated = $conn->query("SELECT COUNT(*) FROM generated_emails")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate email hàng loạt - Bảng quản trị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }

        .admin-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .generated-list {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: .5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
            margin-top: 1.5rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .email-item {
            padding: .5rem .75rem;
            background: #f8f9fa;
            border-radius: .375rem;
            margin-bottom: .5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .email-item span {
            font-family: monospace;
            font-size: .875rem;
        }

        .btn-copy-small {
            border: none;
            border-radius: .25rem;
            padding: .25rem .5rem;
            font-size: .75rem;
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

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted mb-1">Tổng số email đã generate</div>
                    <div class="fs-3 fw-bold text-primary"><?php echo $total_generated; ?></div>
                </div>
                <div class="display-5 text-primary">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0 d-flex align-items-center">
                    <i class="bi bi-gear-fill me-2"></i>Cấu hình generate email
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="generate_bulk">

                    <div class="mb-3">
                        <label for="count" class="form-label">Số lượng email (tối đa 1000)</label>
                        <input type="number" id="count" name="count" min="1" max="1000" value="10" required class="form-control">
                        <div class="form-text">Nhập số lượng email muốn tạo (1–1000)</div>
                    </div>

                    <div class="mb-3">
                        <label for="prefix" class="form-label">Prefix (tuỳ chọn)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                            <input type="text" id="prefix" name="prefix" class="form-control" placeholder="user" pattern="[a-zA-Z0-9_-]*" title="Chỉ dùng chữ, số, dấu gạch ngang (-) và gạch dưới (_)">
                            <span class="input-group-text"><?php echo EMAIL_DOMAIN; ?></span>
                        </div>
                        <div class="form-text">Prefix cho email, ví dụ: "user" sẽ tạo user1, user2, ...</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="use_random" id="use_random">
                        <label class="form-check-label" for="use_random">
                            <i class="bi bi-shuffle me-1"></i> Dùng chuỗi ngẫu nhiên
                        </label>
                        <div class="form-text">Tạo email với chuỗi ngẫu nhiên, ví dụ: user3f2a1b4c5d6e7f8</div>
                    </div>

                    <div class="mb-3 border border-success rounded p-3 bg-success-subtle">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="use_faker" id="use_faker">
                            <label class="form-check-label fw-semibold text-success" for="use_faker">
                                <i class="bi bi-person-badge-fill me-1"></i>Dùng Faker (tên giả thực tế)
                            </label>
                        </div>
                        <div class="form-text text-success mb-2">
                            Tạo email với tên trông giống thật, ví dụ: john.smith, sarah_jones, ...
                        </div>

                        <div id="faker_options" class="mt-2 ps-3" style="display:none;">
                            <label class="form-label mb-1">Kiểu Faker</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="faker_type" id="faker_name" value="name" checked>
                                <label class="form-check-label" for="faker_name">
                                    <strong>Name Based</strong> – john.smith, sarah_jones123
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="faker_type" id="faker_combo" value="combo">
                                <label class="form-check-label" for="faker_combo">
                                    <strong>Combo</strong> – cooluser123, super_gamer456
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="faker_type" id="faker_word" value="word">
                                <label class="form-check-label" for="faker_word">
                                    <strong>Word</strong> – john1234, michael567
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fs-6">
                        <i class="bi bi-lightning-charge-fill me-1"></i>Generate email
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($generated_emails)): ?>
            <div class="generated-list">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        Danh sách email vừa tạo (<?php echo count($generated_emails); ?>)
                    </h6>
                    <button type="button" onclick="copyAllEmails()" class="btn btn-success btn-sm">
                        <i class="bi bi-clipboard-check me-1"></i>Copy tất cả
                    </button>
                </div>
                <div id="email-list">
                    <?php foreach ($generated_emails as $email): ?>
                        <div class="email-item">
                            <span><?php echo htmlspecialchars($email); ?></span>
                            <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($email, ENT_QUOTES); ?>')" class="btn btn-primary btn-copy-small">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Đã sao chép email: ' + text);
            }).catch(function() {
                alert('Không thể sao chép email.');
            });
        }

        function copyAllEmails() {
            const emails = <?php echo json_encode($generated_emails); ?>;
            const emailText = emails.join('\n');
            navigator.clipboard.writeText(emailText).then(function() {
                alert('Đã sao chép tất cả email (' + emails.length + ').');
            }).catch(function() {
                alert('Không thể sao chép danh sách email.');
            });
        }

        document.getElementById('use_random').addEventListener('change', function() {
            const prefixInput = document.getElementById('prefix');
            if (this.checked) {
                prefixInput.placeholder = 'user (tuỳ chọn)';
            } else {
                prefixInput.placeholder = 'user';
            }
        });

        document.getElementById('use_faker').addEventListener('change', function() {
            const fakerOptions = document.getElementById('faker_options');
            fakerOptions.style.display = this.checked ? 'block' : 'none';
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>