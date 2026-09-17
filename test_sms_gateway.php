<?php
/**
 * SMS Gateway Test Script
 * Test script to verify SMS sending
 * 
 * Usage:
 *   php test_sms_gateway.php [phone_number] [message]
 *   
 * Examples:
 *   php test_sms_gateway.php +639171234567 "Test message"
 *   php test_sms_gateway.php +639171234567
 */

include 'config.php';
include 'sms_functions.php';

// Get phone number and message from command line
$phone = isset($argv[1]) ? $argv[1] : '';
$message = isset($argv[2]) ? $argv[2] : 'Test SMS from school gate system - ' . date('Y-m-d H:i:s');

if (empty($phone)) {
    echo "Usage: php test_sms_gateway.php [phone_number] [optional_message]\n";
    echo "Example: php test_sms_gateway.php +639171234567 \"Proof of entry\"\n";
    exit(1);
}

echo "SMS Gateway Test\n";
echo "================\n\n";

// Verify configuration
echo "Configuration Check:\n";
echo "  SMS_ENABLE: " . (SMS_ENABLE ? 'true' : 'false') . "\n";
echo "  SMS_METHOD: " . (defined('SMS_METHOD') ? SMS_METHOD : 'undefined') . "\n";
if (defined('SMS_METHOD') && SMS_METHOD === 'arduino') {
    echo "  SMS_ARDUINO_PORT: " . (defined('SMS_ARDUINO_PORT') ? SMS_ARDUINO_PORT : 'undefined') . "\n";
    echo "  SMS_ARDUINO_BAUDRATE: " . (defined('SMS_ARDUINO_BAUDRATE') ? SMS_ARDUINO_BAUDRATE : 'undefined') . "\n";
}
echo "\n";

if (!SMS_ENABLE) {
    echo "ERROR: SMS_ENABLE is false in config.php\n";
    exit(1);
}

if (defined('SMS_METHOD') && SMS_METHOD === 'arduino' && (!defined('SMS_ARDUINO_PORT') || SMS_ARDUINO_PORT === '')) {
    echo "ERROR: SMS_ARDUINO_PORT not configured in config.php\n";
    exit(1);
}

echo "Sending SMS Test:\n";
echo "  Phone: " . $phone . "\n";
echo "  Message: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\n";
if (defined('SMS_METHOD') && SMS_METHOD === 'arduino') {
    echo "  Port: " . SMS_ARDUINO_PORT . "\n";
    echo "  Baudrate: " . (defined('SMS_ARDUINO_BAUDRATE') ? SMS_ARDUINO_BAUDRATE : 9600) . "\n";
}
echo "\n";

echo "Attempting to send SMS...\n";

// Send SMS
$result = sendSmsNotification($phone, $message);

if ($result) {
    echo "✓ SMS sent successfully!\n";
    echo "\nCheck your phone for the message within 30 seconds.\n";
    exit(0);
} else {
    echo "✗ SMS failed. Check the following:\n";
    if (defined('SMS_METHOD') && SMS_METHOD === 'semaphore') {
        echo "  1. SEMAPHORE_API_KEY is available to the PHP/Apache process\n";
        echo "  2. The Semaphore account has SMS credits\n";
        echo "  3. The sender name is approved by Semaphore\n";
        echo "  4. Check PHP error logs for the API response\n";
    } else {
        echo "  1. Check the configured SMS gateway and its connection\n";
        echo "  2. Check PHP error logs for details\n";
    }
    echo "\nError logs: C:\\wamp\\logs\\php_error.log\n";
    exit(1);
}
?>
