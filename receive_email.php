<?php

/**
 * Email Receiver Script
 * This script receives emails via SMTP or HTTP POST
 * Configure your mail server to pipe emails to this script
 */

require_once 'config.php';
require_once 'functions.php';

// Enable error logging
error_log("Receive email script called - Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

/**
 * Decode MIME-encoded header (UTF-8, Q/B encoding)
 * Ví dụ: =?UTF-8?Q?c=C3=A2dasd?=  =>  câdasd
 */
function decode_mime_header_utf8($str)
{
    // Decode các đoạn =?charset?B/Q?...?=
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

            // Nếu không phải UTF-8 thì convert sang UTF-8 (Gmail của bạn là UTF-8 nên đoạn này gần như không đụng tới)
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

/**
 * Decode email content (handle quoted-printable, base64, etc)
 */
function decodeEmailContent($content, $headers)
{
    // Check encoding
    if (stripos($headers, 'Content-Transfer-Encoding: quoted-printable') !== false) {
        $content = quoted_printable_decode($content);
    } elseif (stripos($headers, 'Content-Transfer-Encoding: base64') !== false) {
        $content = base64_decode($content);
    }

    // Clean up
    $content = trim($content);

    return $content;
}

/**
 * Parse email body (handle multipart, HTML, plain text)
 */
function parseEmailBody($body, $headers)
{
    // Check if multipart
    if (preg_match('/boundary="?([^"\s]+)"?/i', $headers, $boundary_matches)) {
        $boundary = $boundary_matches[1];

        // Split by boundary
        $parts = explode('--' . $boundary, $body);

        $plain_text = '';
        $html_text  = '';

        foreach ($parts as $part) {
            // Skip empty parts
            if (trim($part) === '' || trim($part) === '--') continue;

            // Split headers and content
            $part_split = preg_split("/\r?\n\r?\n/", $part, 2);
            if (count($part_split) < 2) continue;

            $part_headers = $part_split[0];
            $part_content = $part_split[1];

            // Check content type
            if (stripos($part_headers, 'Content-Type: text/plain') !== false) {
                $plain_text = decodeEmailContent($part_content, $part_headers);
            } elseif (stripos($part_headers, 'Content-Type: text/html') !== false) {
                $html_text = decodeEmailContent($part_content, $part_headers);
            }
        }

        // Return HTML if available, otherwise plain text
        if (!empty($html_text)) {
            return $html_text;
        } elseif (!empty($plain_text)) {
            // Không escape ở đây, để frontend xử lý
            return $plain_text;
        }
    }

    // Single part email - check if HTML
    $decoded = decodeEmailContent($body, $headers);

    // If content type is HTML, return as is
    if (stripos($headers, 'Content-Type: text/html') !== false) {
        return $decoded;
    }

    // Otherwise treat as plain text - don't escape, let frontend handle it
    return $decoded;
}

// ----------------------
// Method 1: HTTP POST (webhook/API)
// ----------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Support multiple POST formats
    $to_email   = $_POST['to']        ?? $_POST['to_email'] ?? $_POST['recipient'] ?? '';
    $from_email = $_POST['from']      ?? $_POST['from_email'] ?? $_POST['sender'] ?? '';
    $subject    = $_POST['subject']   ?? 'No Subject';
    $body       = $_POST['body']      ?? $_POST['text'] ?? $_POST['html'] ?? '';
    $headers    = $_POST['headers']   ?? '';

    // Decode MIME cho subject & from (phòng khi bên gửi đẩy dạng =?UTF-8?...?=)
    $subject    = decode_mime_header_utf8($subject);
    $from_email = decode_mime_header_utf8($from_email);

    error_log("Received email (POST) - To: $to_email, From: $from_email, Subject: $subject");

    // Validate email domain
    if (strpos($to_email, EMAIL_DOMAIN) !== false) {
        // Save email and extract OTP
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            INSERT INTO emails (to_email, from_email, subject, body, headers, received_at) 
            VALUES (:to_email, :from_email, :subject, :body, :headers, NOW())
        ");

        $success = $stmt->execute([
            'to_email'    => $to_email,
            'from_email'  => $from_email,
            'subject'     => $subject,
            'body'        => $body,
            'headers'     => $headers
        ]);

        if ($success) {
            $email_id = $conn->lastInsertId();

            // Try to extract OTP from email content
            $otp_code = extractOTPFromContent($subject, $body);

            if ($otp_code) {
                // Save OTP to database
                saveOTPCode($to_email, $otp_code, $from_email, $subject, $email_id);
                error_log("OTP extracted and saved: $otp_code for email: $to_email");
            }

            http_response_code(200);
            error_log("Email saved successfully (POST)");
            echo json_encode([
                'success'      => true,
                'message'      => 'Email received',
                'otp_detected' => !empty($otp_code)
            ]);
        } else {
            http_response_code(500);
            error_log("Failed to save email (POST)");
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save email'
            ]);
        }
    } else {
        http_response_code(400);
        error_log("Invalid domain (POST): $to_email");
        echo json_encode([
            'success' => false,
            'message' => 'Invalid domain'
        ]);
    }
    exit;
}

// ----------------------
// Method 2: STDIN (pipe từ Postfix/Exim) - CLI
// ----------------------
if (php_sapi_name() === 'cli') {
    $raw_email = file_get_contents('php://stdin');

    if (!empty($raw_email)) {
        // Parse email headers and body
        $parts   = preg_split("/\r?\n\r?\n/", $raw_email, 2);
        $headers = $parts[0] ?? '';
        $body    = $parts[1] ?? '';

        // Extract To, From, Subject
        preg_match('/^To: (.*)$/mi', $headers, $to_matches);
        preg_match('/^From: (.*)$/mi', $headers, $from_matches);
        preg_match('/^Subject: (.*)$/mi', $headers, $subject_matches);

        $to_email   = trim($to_matches[1] ?? '');
        $from_email = trim($from_matches[1] ?? '');
        $subject    = trim($subject_matches[1] ?? 'No Subject');

        // Decode MIME header (UTF-8, Q/B)
        $subject    = decode_mime_header_utf8($subject);
        $from_email = decode_mime_header_utf8($from_email);

        // Extract email address from "Name <email@domain.com>" format
        if (preg_match('/<(.+?)>/', $to_email, $matches)) {
            $to_email = $matches[1];
        }
        if (preg_match('/<(.+?)>/', $from_email, $matches)) {
            $from_email = $matches[1];
        }

        // Parse multipart email body
        $parsed_body = parseEmailBody($body, $headers);

        // Validate and save
        if (strpos($to_email, EMAIL_DOMAIN) !== false) {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                INSERT INTO emails (to_email, from_email, subject, body, headers, received_at) 
                VALUES (:to_email, :from_email, :subject, :body, :headers, NOW())
            ");

            $success = $stmt->execute([
                'to_email'    => $to_email,
                'from_email'  => $from_email,
                'subject'     => $subject,
                'body'        => $parsed_body,
                'headers'     => $headers
            ]);

            if ($success) {
                $email_id = $conn->lastInsertId();

                // Try to extract OTP
                $otp_code = extractOTPFromContent($subject, $parsed_body);

                if ($otp_code) {
                    saveOTPCode($to_email, $otp_code, $from_email, $subject, $email_id);
                    echo "Email received and saved with OTP: $otp_code\n";
                } else {
                    echo "Email received and saved\n";
                }
            } else {
                echo "Failed to save email\n";
            }
        } else {
            echo "Invalid domain\n";
        }
    }
    exit;
}

// Default response cho request không hợp lệ
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
