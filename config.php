<?php
$host = 'localhost';
$dbname = 'tempmail@1@';
$username = 'triphuong998';
$password = 'Phuong123@';

define('EMAIL_DOMAIN', '@healdailylife.com');

function getDBConnection()
{
    static $conn = null;
    global $host, $dbname, $username, $password;

    if ($conn === null) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $conn = new PDO($dsn, $username, $password, $options);
        // Ép connection về utf8mb4 luôn
        $conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    return $conn;
}
