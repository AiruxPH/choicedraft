<?php
/**
 * ChoiceDraft API Configuration
 * Database connection settings for Hostinger
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
//define('DB_HOST', 'svr1999.hstgr.io');
define('DB_HOST', 'localhost');
define('DB_NAME', 'ul30348899_choicedraft');
define('DB_USER', 'ul30348899_csit6');
define('DB_PASS', 'CSIT6_pass');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit();
        }
    }
    
    return $pdo;
}

/**
 * Send JSON response
 * @param mixed $data
 * @param int $statusCode
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Get JSON input from request body
 * @return array
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}

/**
 * Generate unique ID
 * @param string $prefix
 * @return string
 */
function generateId($prefix) {
    return $prefix . '_' . bin2hex(random_bytes(4)) . '_' . time();
}
