<?php
$host = "localhost"; // Keep as localhost on the Lenovo
$user = "root";
$pass = "";
$dbname = "school_gate";

$adminCredentials = require __DIR__ . '/admin_credentials.php';
if (!defined('ADMIN_USERNAME')) {
    define('ADMIN_USERNAME', (string)($adminCredentials['username'] ?? 'admin'));
}

if (!function_exists('adminPasswordMatches')) {
    function adminPasswordMatches(string $password): bool {
        global $adminCredentials;
        return isset($adminCredentials['password_hash'])
            && password_verify($password, $adminCredentials['password_hash']);
    }
}

if (!defined('SOLENOID_ENABLE')) {
    define('SOLENOID_ENABLE', true);
}

if (!defined('SOLENOID_SERIAL_PORT')) {
    define('SOLENOID_SERIAL_PORT', 'COM4');
}

if (!defined('SOLENOID_OPEN_DURATION_MS')) {
    define('SOLENOID_OPEN_DURATION_MS', 3000);
}

if (!defined('SOLENOID_BRIDGE_URL')) {
    define('SOLENOID_BRIDGE_URL', 'http://localhost:5010/open');
}

if (!defined('SOLENOID_BRIDGE_PULSE_URL')) {
    define('SOLENOID_BRIDGE_PULSE_URL', 'http://localhost:5010/pulse');
}

if (!defined('SMS_ENABLE')) {
    define('SMS_ENABLE', true);
}

if (!defined('SMS_DEMO_MODE')) {
    define('SMS_DEMO_MODE', false); // Keep false unless you are intentionally simulating SMS for a demo.
}

if (!defined('SMS_METHOD')) {
    define('SMS_METHOD', 'semaphore'); // 'semaphore', 'bridge', or 'arduino'
}

if (!defined('SMS_SEMAPHORE_API_URL')) {
    define('SMS_SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages');
}

if (!defined('SMS_SEMAPHORE_API_KEY')) {
    define('SMS_SEMAPHORE_API_KEY', (string)(getenv('SEMAPHORE_API_KEY') ?: ''));
}

if (!defined('SMS_SEMAPHORE_SENDER_NAME')) {
    define('SMS_SEMAPHORE_SENDER_NAME', 'VestaBNHS');
}

if (!defined('SMS_SEMAPHORE_TIMEOUT_SEC')) {
    define('SMS_SEMAPHORE_TIMEOUT_SEC', 15);
}

if (!defined('SMS_BRIDGE_URL')) {
    define('SMS_BRIDGE_URL', 'http://localhost:5020/sms');
}

if (!defined('SMS_ARDUINO_PORT')) {
    define('SMS_ARDUINO_PORT', 'COM9'); // Testing the project on COM9 as requested.
}

if (!defined('SMS_ARDUINO_BAUDRATE')) {
    define('SMS_ARDUINO_BAUDRATE', 9600); // SIM900A baud rate
}

if (!defined('SMS_ARDUINO_TIMEOUT_SEC')) {
    define('SMS_ARDUINO_TIMEOUT_SEC', 10); // Timeout for serial communication
}

if (!defined('SMS_SENDER_ID')) {
    define('SMS_SENDER_ID', 'SchoolGate');
}

if (!defined('UNIFORM_ALERT_THRESHOLD')) {
    define('UNIFORM_ALERT_THRESHOLD', 3);
}

if (!defined('UNIFORM_ALERT_WINDOW_DAYS')) {
    define('UNIFORM_ALERT_WINDOW_DAYS', 7);
}

if (!defined('UNIFORM_ALERT_COOLDOWN_HOURS')) {
    define('UNIFORM_ALERT_COOLDOWN_HOURS', 24);
}

if (!function_exists('ensureParentPhoneColumn')) {
    function ensureParentPhoneColumn($conn) {
        $result = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_phone'");
        if ($result && $result->num_rows > 0) {
            return true;
        }

        $sql = "ALTER TABLE students ADD COLUMN parent_phone VARCHAR(30) NULL DEFAULT NULL AFTER rfid_tag";
        if ($conn->query($sql) === TRUE) {
            return true;
        }

        error_log('Failed to add parent_phone column: ' . $conn->error);
        return false;
    }
}

if (!function_exists('ensureAdminNotificationsTable')) {
    function ensureAdminNotificationsTable($conn) {
        $result = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($result && $result->num_rows > 0) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NULL,
            entry_log_id INT NULL,
            notification_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            dedupe_key VARCHAR(100) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_admin_notification_dedupe (dedupe_key),
            INDEX idx_admin_notifications_read (is_read),
            INDEX idx_admin_notifications_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($conn->query($sql) === TRUE) {
            return true;
        }

        error_log('Failed to create admin_notifications table: ' . $conn->error);
        return false;
    }
}

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
