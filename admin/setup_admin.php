<?php
/**
 * Setup Admin User
 * Run this file once to create admin_users table and default admin
 */

require_once '../config.php';

try {
    $conn = getDBConnection();
    
    // Create admin_users table
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    echo "✓ Tabel admin_users berhasil dibuat<br>";
    
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Create default admin user
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, email, created_at) 
                               VALUES ('admin', :password_hash, 'admin@alrelshop.my.id', NOW())");
        $stmt->execute(['password_hash' => $password_hash]);
        echo "✓ User admin berhasil dibuat<br>";
        echo "  Username: admin<br>";
        echo "  Password: admin123<br>";
        echo "<br><strong>⚠️ PENTING: Segera ubah password default!</strong><br>";
    } else {
        echo "✓ User admin sudah ada<br>";
    }
    
    echo "<br><a href='change_password.php'>Ubah Password</a> | <a href='login.php'>Login</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
