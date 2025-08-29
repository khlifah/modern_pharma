<?php
// config.php
const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'modern_pharma';

// لا تبدأ جلسة إذا كانت فعلاً شغّالة
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  }
  return $pdo;
}

function ensure_users_table(): void {
  $sql = "
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) NOT NULL UNIQUE,
      full_name VARCHAR(150) DEFAULT NULL,
      role ENUM('admin','accountant','manager','user') DEFAULT 'user',
      password_hash VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  db()->exec($sql);
}
ensure_users_table();
