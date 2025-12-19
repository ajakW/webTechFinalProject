<?php

define('DB_HOST', 'localhost');             
define('DB_USER', 'ajak.panchol');          
define('DB_PASS', 'sudo4541');                       
define('DB_NAME', 'webtech_2025A_ajak_panchol');


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDBConnection()
{
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            logError("DB connection failed", [
                'error' => $conn->connect_error
            ]);
            throw new Exception("Database connection failed.");
        }

        $conn->set_charset("utf8mb4");
        return $conn;

    } catch (Exception $e) {
        
        die("A system error occurred. Please try again later.");
    }
}


// Session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['session_expired'] = true;
}
$_SESSION['last_activity'] = time();

// Session hijacking prevention
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
} elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['session_invalid'] = true;
}

//AUTH HELPERS

function isAdmin()
{
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername()
{
    return $_SESSION['username'] ?? null;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

//CSRF Protection

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Error logs

function logError($message, $context = [])
{
    $logDir = __DIR__ . '/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';

    file_put_contents(
        $logFile,
        "[$timestamp] $message $contextStr" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
