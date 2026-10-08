<?php
/**
 * Database configuration - XAMPP defaults
 * Change these if your MySQL credentials are different
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'micro_lending');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is empty
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection (singleton-style)
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}