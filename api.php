<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'generate':
        try {
            $domain = 'healdailylife.com';
            $name = $_POST['name'] ?? $_GET['name'] ?? '';
            if ($name !== '') {
                $local = strtolower(trim($name));
                $local = preg_replace('/[^a-z0-9._-]/', '', $local);
                if ($local === '') {
                    throw new Exception('Tên email không hợp lệ');
                }
                $new_email = $local . '@' . $domain;
            } else {
                $generated = generateRandomEmail();
                $parts = explode('@', $generated, 2);
                $local = $parts[0] ?? '';
                if ($local === '') {
                    throw new Exception('Không thể tạo email ngẫu nhiên');
                }
                $new_email = $local . '@' . $domain;
            }

            $token = generateTokenForEmail($new_email);

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/?token=' . $token;

            echo json_encode([
                'success' => true,
                'email'   => $new_email,
                'token'   => $token,
                'url'     => $url
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        break;

    case 'restore':
        $token = $_POST['token'] ?? '';
        if ($token) {
            $email = getEmailByToken($token);
            if ($email) {
                echo json_encode([
                    'success' => true,
                    'email'   => $email,
                    'token'   => $token
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Token không hợp lệ hoặc đã hết hạn'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Token không được cung cấp'
            ]);
        }
        break;

    case 'get_emails':
        $email = $_GET['email'] ?? '';
        $token = $_GET['token'] ?? '';

        if (!$email && $token) {
            $email = getEmailByToken($token);
        }

        if ($email) {
            $emails = getEmails($email);
            echo json_encode([
                'success' => true,
                'emails'  => $emails,
                'count'   => count($emails),
                'email'   => $email
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không có địa chỉ email. Hãy dùng ?email=your@email.com hoặc ?token=...'
            ]);
        }
        break;

    case 'get_email':
        $id = $_GET['id'] ?? 0;
        $email = getEmailById($id);
        if ($email) {
            echo json_encode([
                'success' => true,
                'email'   => $email
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy email'
            ]);
        }
        break;

    case 'get_otp_codes':
        $email = $_GET['email'] ?? '';
        $token = $_GET['token'] ?? '';
        $limit = $_GET['limit'] ?? 10;

        if (!$email && $token) {
            $email = getEmailByToken($token);
        }

        if ($email) {
            $otp_codes = getOTPCodes($email, $limit);

            foreach ($otp_codes as &$otp) {
                if (isset($otp['subject'])) {
                    $otp['subject'] = decode_mime_header_utf8_helper($otp['subject']);
                }
                if (isset($otp['sender'])) {
                    $otp['sender'] = decode_mime_header_utf8_helper($otp['sender']);
                }
            }

            echo json_encode([
                'success'   => true,
                'otp_codes' => $otp_codes,
                'count'     => count($otp_codes),
                'email'     => $email
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không có địa chỉ email. Hãy dùng ?email=your@email.com hoặc ?token=...'
            ]);
        }
        break;

    case 'get_latest_otp':
        $email = $_GET['email'] ?? '';
        $token = $_GET['token'] ?? '';

        if (!$email && $token) {
            $email = getEmailByToken($token);
        }

        if ($email) {
            $otp = getLatestOTP($email);
            if ($otp) {
                $subject = isset($otp['subject']) ? decode_mime_header_utf8_helper($otp['subject']) : null;
                $sender  = isset($otp['sender']) ? decode_mime_header_utf8_helper($otp['sender']) : null;

                echo json_encode([
                    'success'      => true,
                    'otp'          => $otp['otp_code'],
                    'sender'       => $sender,
                    'subject'      => $subject,
                    'extracted_at' => $otp['extracted_at'],
                    'is_used'      => $otp['is_used'],
                    'email'        => $email
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Không tìm thấy mã OTP cho email này'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không có địa chỉ email. Hãy dùng ?email=your@email.com hoặc ?token=...'
            ]);
        }
        break;

    case 'mark_otp_used':
        $otp_id = $_POST['otp_id'] ?? 0;

        if ($otp_id) {
            $success = markOTPAsUsed($otp_id);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Cần truyền OTP ID'
            ]);
        }
        break;

    case 'get_all_latest_otps':
        $limit = $_GET['limit'] ?? 10;

        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(extracted_at) as timestamp 
                               FROM otp_codes 
                               ORDER BY extracted_at DESC 
                               LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $otps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($otps as &$otp) {
            if (isset($otp['subject'])) {
                $otp['subject'] = decode_mime_header_utf8_helper($otp['subject']);
            }
            if (isset($otp['sender'])) {
                $otp['sender'] = decode_mime_header_utf8_helper($otp['sender']);
            }
        }

        echo json_encode([
            'success' => true,
            'otps'    => $otps,
            'count'   => count($otps)
        ]);
        break;

    case 'get_latest_otp_global':
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(extracted_at) as timestamp 
                               FROM otp_codes 
                               ORDER BY extracted_at DESC 
                               LIMIT 1");
        $stmt->execute();
        $otp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otp) {
            $subject = isset($otp['subject']) ? decode_mime_header_utf8_helper($otp['subject']) : null;
            $sender  = isset($otp['sender']) ? decode_mime_header_utf8_helper($otp['sender']) : null;

            echo json_encode([
                'success'        => true,
                'otp_code'       => $otp['otp_code'],
                'email_address'  => $otp['email_address'],
                'sender'         => $sender,
                'subject'        => $subject,
                'extracted_at'   => $otp['extracted_at'],
                'is_used'        => $otp['is_used'],
                'id'             => $otp['id']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy mã OTP nào'
            ]);
        }
        break;

    case 'search_otp':
        $search = $_GET['search'] ?? '';

        if (empty($search)) {
            echo json_encode([
                'success' => false,
                'message' => 'Thiếu tham số tìm kiếm'
            ]);
            break;
        }

        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(extracted_at) as timestamp 
                               FROM otp_codes 
                               WHERE otp_code      LIKE :search 
                                  OR sender        LIKE :search 
                                  OR email_address LIKE :search 
                                  OR subject       LIKE :search
                               ORDER BY extracted_at DESC 
                               LIMIT 20");
        $searchParam = "%{$search}%";
        $stmt->execute(['search' => $searchParam]);
        $otps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($otps as &$otp) {
            if (isset($otp['subject'])) {
                $otp['subject'] = decode_mime_header_utf8_helper($otp['subject']);
            }
            if (isset($otp['sender'])) {
                $otp['sender'] = decode_mime_header_utf8_helper($otp['sender']);
            }
        }

        echo json_encode([
            'success' => true,
            'otps'    => $otps,
            'count'   => count($otps),
            'search'  => $search
        ]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Yêu cầu không hợp lệ'
        ]);
}
