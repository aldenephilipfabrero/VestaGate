<?php
include 'config.php';
include_once __DIR__ . '/sms_functions.php';

header('Content-Type: application/json');

function openGateViaArduino($openMs) {
    if (!defined('SOLENOID_ENABLE') || !SOLENOID_ENABLE) {
        return false;
    }

    $port = defined('SOLENOID_SERIAL_PORT') ? trim((string)SOLENOID_SERIAL_PORT) : '';
    if ($port === '' || $port === 'auto') {
        $detected = detectArduinoSerialPort();
        if ($detected === false) {
            error_log('Gate open skipped: no Arduino serial port detected');
            return false;
        }
        $port = $detected;
    }

    return sendArduinoCommand('OPEN|' . intval($openMs), $port);
}

$openMs = defined('SOLENOID_OPEN_DURATION_MS') ? SOLENOID_OPEN_DURATION_MS : 3000;
$ok = openGateViaArduino($openMs);

echo json_encode([
    'success' => $ok,
    'open_ms' => $openMs
]);
?>