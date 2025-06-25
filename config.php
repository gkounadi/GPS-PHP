<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kounadis_golf_gps');
define('DB_USER', 'kounadis_golf_user');
define('DB_PASS', 'GolfAppSecure2025!');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // In production, don't output sensitive details
    exit('Database connection failed: ' . $e->getMessage());
}
?>