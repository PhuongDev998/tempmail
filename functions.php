<?php
require_once 'config.php';

// Helper: decode MIME header (UTF-8, Q/B encoding)
function decode_mime_header_utf8_helper($str)
{
    return preg_replace_callback(
        '/=\?([^?]+)\?([bBqQ])\?([^?]+)\?=/',
        function ($m) {
            $charset  = strtoupper($m[1]);
            $encoding = strtoupper($m[2]);
            $text     = $m[3];

            if ($encoding === 'B') {
                $decoded = base64_decode($text);
            } else { // Q
                $decoded = quoted_printable_decode(str_replace('_', ' ', $text));
            }

            if ($charset !== 'UTF-8' && function_exists('iconv')) {
                $tmp = @iconv($charset, 'UTF-8//TRANSLIT', $decoded);
                if ($tmp !== false) {
                    $decoded = $tmp;
                }
            }

            return $decoded;
        },
        $str
    );
}

// Chuẩn hoá 1 row email (decode subject, from_email)
function normalize_email_row($row)
{
    if (isset($row['subject'])) {
        $row['subject'] = decode_mime_header_utf8_helper($row['subject']);
    }
    if (isset($row['from_email'])) {
        $row['from_email'] = decode_mime_header_utf8_helper($row['from_email']);
    }
    return $row;
}

// Generate random email address
function generateRandomEmail()
{
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $length = rand(8, 12);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $randomString . EMAIL_DOMAIN;
}

// Get all emails for specific address
function getEmails($email_address)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(received_at) as timestamp FROM emails WHERE to_email = :email ORDER BY received_at DESC");
    $stmt->execute(['email' => $email_address]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode subject/from cho tất cả email
    foreach ($rows as &$row) {
        $row = normalize_email_row($row);
    }

    return $rows;
}

// Get single email by ID
function getEmailById($id)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(received_at) as timestamp FROM emails WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $row = normalize_email_row($row);
    }

    return $row;
}

// Save incoming email to database
function saveEmail($to_email, $from_email, $subject, $body, $headers = '')
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO emails (to_email, from_email, subject, body, headers, received_at) 
                           VALUES (:to_email, :from_email, :subject, :body, :headers, NOW())");

    return $stmt->execute([
        'to_email'   => $to_email,
        'from_email' => $from_email,
        'subject'    => $subject,
        'body'       => $body,
        'headers'    => $headers
    ]);
}

// Delete old emails (older than 24 hours)
function cleanOldEmails()
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM emails WHERE received_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    return $stmt->execute();
}

// Get email count for address
function getEmailCount($email_address)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM emails WHERE to_email = :email");
    $stmt->execute(['email' => $email_address]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

// Generate token for email
function generateTokenForEmail($email)
{
    $conn = getDBConnection();

    // Luôn tạo token mới (nếu muốn giữ behavior cũ check trước thì giữ lại, không ảnh hưởng)
    $token = bin2hex(random_bytes(16)); // 32 char hex

    $sql = "INSERT INTO generated_emails (email_address, access_token, created_at)
            VALUES (:email, :token, NOW())
            ON DUPLICATE KEY UPDATE access_token = VALUES(access_token)";

    try {
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([
            ':email' => $email,
            ':token' => $token,
        ]);

        // Nếu execute() trả false mà không ném exception
        if (!$ok) {
            $info = $stmt->errorInfo();
            file_put_contents(__DIR__ . '/db_error.log', date('c') . " EXEC FAIL: " . print_r($info, true) . PHP_EOL, FILE_APPEND);
        }
    } catch (PDOException $e) {
        // Log chi tiết lỗi PDO
        file_put_contents(__DIR__ . '/db_error.log', date('c') . " PDO EX: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        // Vẫn trả về token tạm để hệ thống không chết
        return md5($email . time());
    }

    return $token;
}

// Get email by token
function getEmailByToken($token)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT email_address FROM generated_emails WHERE access_token = :token");
    $stmt->execute(['token' => $token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Update last accessed
        $update = $conn->prepare("UPDATE generated_emails SET last_accessed = NOW(), access_count = access_count + 1 WHERE access_token = :token");
        $update->execute(['token' => $token]);

        return $result['email_address'];
    }

    return null;
}

// Extract OTP code from email content
function extractOTPFromContent($subject, $body)
{
    $content = $subject . ' ' . $body;

    // Pattern untuk mendeteksi OTP
    $patterns = [
        '/\b(\d{4,8})\b/',                          // 4-8 digit numbers
        '/code[:\s]+(\d{4,8})/i',                   // "code: 123456"
        '/otp[:\s]+(\d{4,8})/i',                    // "OTP: 123456"
        '/verification[:\s]+(\d{4,8})/i',           // "verification: 123456"
        '/kode[:\s]+(\d{4,8})/i',                   // "kode: 123456" (Indonesian)
        '/pin[:\s]+(\d{4,8})/i',                    // "PIN: 123456"
        '/token[:\s]+(\d{4,8})/i',                  // "token: 123456"
        '/(\d{4,8})\s+is\s+your/i',                 // "123456 is your code"
        '/your\s+code\s+is[:\s]+(\d{4,8})/i',       // "your code is 123456"
        '/(\d{3}[-\s]\d{3})/i',                     // "123-456" or "123 456"
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            // Clean up the OTP (remove spaces and dashes)
            $otp = preg_replace('/[-\s]/', '', $matches[1]);

            // Validate OTP length (4-8 digits)
            if (strlen($otp) >= 4 && strlen($otp) <= 8 && ctype_digit($otp)) {
                return $otp;
            }
        }
    }

    return null;
}

// Save OTP code to database
function saveOTPCode($email_address, $otp_code, $sender, $subject, $email_id = null)
{
    $conn = getDBConnection();

    try {
        $stmt = $conn->prepare("INSERT INTO otp_codes (email_address, otp_code, sender, subject, email_id, extracted_at) 
                               VALUES (:email, :otp, :sender, :subject, :email_id, NOW())");

        return $stmt->execute([
            'email'    => $email_address,
            'otp'      => $otp_code,
            'sender'   => $sender,
            'subject'  => $subject,
            'email_id' => $email_id
        ]);
    } catch (PDOException $e) {
        error_log("Failed to save OTP: " . $e->getMessage());
        return false;
    }
}

// Get OTP codes for specific email address
function getOTPCodes($email_address, $limit = 10)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(extracted_at) as timestamp 
                           FROM otp_codes 
                           WHERE email_address = :email 
                           ORDER BY extracted_at DESC 
                           LIMIT :limit");
    $stmt->bindValue(':email', $email_address, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get latest OTP code for email address
function getLatestOTP($email_address)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT *, UNIX_TIMESTAMP(extracted_at) as timestamp 
                           FROM otp_codes 
                           WHERE email_address = :email 
                           ORDER BY extracted_at DESC 
                           LIMIT 1");
    $stmt->execute(['email' => $email_address]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mark OTP as used
function markOTPAsUsed($otp_id)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE otp_codes SET is_used = 1, used_at = NOW() WHERE id = :id");
    return $stmt->execute(['id' => $otp_id]);
}
