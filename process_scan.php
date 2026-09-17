<?php
include 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$rfid = trim((string)($input['rfid'] ?? ''));
$imageData = $input['image'] ?? null; // Base64 encoded image from camera

if (empty($rfid)) {
    echo json_encode(['success' => false, 'error' => 'No RFID provided']);
    exit;
}

// Find student by RFID
$student = null;
$stmt = $conn->prepare('SELECT * FROM students WHERE rfid_tag = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('s', $rfid);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Unknown RFID']);
    exit;
}

$gateOpened = false;

/**
 * Function to save violation image from base64
 * @param string $imageData Base64 encoded image data
 * @param int $studentId Student ID
 * @param string $violationType Type of violation
 * @return string|null Path to saved image or null if failed
 */
function saveViolationImage($imageData, $studentId, $violationType) {
    if (empty($imageData)) {
        return null;
    }
    
    try {
        // Create violations_images directory if not exists
        $imageDir = __DIR__ . '/violations_images';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        
        // Remove base64 header if present
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
        }
        
        // Decode base64 to binary
        $imageBinary = base64_decode($imageData, true);
        if ($imageBinary === false) {
            return null;
        }
        
        // Generate unique filename with timestamp and student ID
        $timestamp = date('YmdHis');
        $microtime = substr(microtime(), 2, 8);
        $filename = "violation_{$studentId}_{$timestamp}_{$microtime}.jpg";
        $filepath = $imageDir . '/' . $filename;
        
        // Save image file
        $bytesWritten = file_put_contents($filepath, $imageBinary);
        if ($bytesWritten === false || $bytesWritten === 0) {
            return null;
        }
        
        // Return relative path for database storage
        return 'violations_images/' . $filename;
    } catch (Exception $e) {
        error_log("Error saving violation image: " . $e->getMessage());
        return null;
    }
}

/**
 * Send an OPEN command to the Arduino solenoid controller.
 */
function triggerSolenoidOpen() {
    if (!defined('SOLENOID_ENABLE') || !SOLENOID_ENABLE) {
        return false;
    }

    if (!defined('SOLENOID_SERIAL_PORT') || empty(SOLENOID_SERIAL_PORT)) {
        error_log('Solenoid trigger skipped: missing serial port configuration');
        return false;
    }

    $openMs = defined('SOLENOID_OPEN_DURATION_MS') ? SOLENOID_OPEN_DURATION_MS : 3000;

    // First, try bridge service that keeps serial port open persistently.
    $GLOBALS['solenoid_debug'] = ['attempts' => []];

    if (defined('SOLENOID_BRIDGE_PULSE_URL') && !empty(SOLENOID_BRIDGE_PULSE_URL)) {
        // Use pulse endpoint to directly drive pins 2 and 3 high for openMs milliseconds
        $payload = ['pins' => [2,3], 'value' => 1, 'duration' => $openMs];
        $ch = curl_init(SOLENOID_BRIDGE_PULSE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $pulseTimeout = max(6, (int)ceil($openMs / 1000) + 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, $pulseTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

        $bridgeResponse = curl_exec($ch);
        $bridgeCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $bridgeErr = curl_error($ch);
        curl_close($ch);

        $GLOBALS['solenoid_debug']['bridge'] = [
            'url' => SOLENOID_BRIDGE_PULSE_URL,
            'http_code' => $bridgeCode,
            'curl_error' => $bridgeErr,
            'response' => $bridgeResponse,
            'payload' => $payload
        ];

        if ($bridgeCode === 200 && $bridgeResponse) {
            $parsed = json_decode($bridgeResponse, true);
            if (is_array($parsed) && !empty($parsed['success'])) {
                return true;
            }
        }

        // Bridge endpoint exists but did not succeed; continue to direct serial fallback.
        $GLOBALS['solenoid_debug']['bridge']['fallback_to_direct'] = true;
    }

    $command = 'OPEN|' . $openMs . "\r\n";
    $portCandidates = [];

    if (stripos(PHP_OS, 'WIN') === 0) {
        $portCandidates[] = SOLENOID_SERIAL_PORT . ':';
        $portCandidates[] = SOLENOID_SERIAL_PORT;
    } else {
        $portCandidates[] = '/dev/' . SOLENOID_SERIAL_PORT;
        $portCandidates[] = SOLENOID_SERIAL_PORT;
    }

    // Keep existing bridge debug and append direct serial attempts.
    if (!isset($GLOBALS['solenoid_debug']['attempts']) || !is_array($GLOBALS['solenoid_debug']['attempts'])) {
        $GLOBALS['solenoid_debug']['attempts'] = [];
    }

    foreach ($portCandidates as $port) {
        $attempt = ['port' => $port, 'opened' => false, 'written' => false, 'responses' => []];

        // Open for read/write so we can capture Arduino responses
        $serial = @fopen($port, 'r+');
        if ($serial === false) {
            $GLOBALS['solenoid_debug']['attempts'][] = $attempt;
            continue;
        }

        $attempt['opened'] = true;

        // Make stream non-blocking and allow Arduino to finish auto-reset on serial open
        stream_set_blocking($serial, false);
        $start = microtime(true);
        while (microtime(true) - $start < 2.2) {
            $line = fgets($serial);
            if ($line !== false) $attempt['responses'][] = trim($line);
            usleep(50000);
        }

        // Send command
        $bytes = fwrite($serial, $command);
        fflush($serial);
        $attempt['written'] = $bytes !== false && $bytes > 0;

        // Read responses for up to 2 seconds
        $start = microtime(true);
        while (microtime(true) - $start < 2.0) {
            $line = fgets($serial);
            if ($line !== false) {
                $attempt['responses'][] = trim($line);
                // If Arduino replied OK, we consider success
                if (stripos($line, 'OK') !== false) {
                    fclose($serial);
                    $GLOBALS['solenoid_debug']['attempts'][] = $attempt;
                    return true;
                }
            }
            usleep(100000);
        }

        // Close and record attempt
        fclose($serial);
        $GLOBALS['solenoid_debug']['attempts'][] = $attempt;

        // If the command was written successfully, treat the gate trigger as sent.
        // Some boards/relay modules do not reliably return an OK line, but the relay
        // may still have toggled correctly.
        if ($attempt['written']) {
            return true;
        }
    }

    error_log('Solenoid trigger failed: unable to open serial port ' . SOLENOID_SERIAL_PORT);
    return false;
}

include_once __DIR__ . '/sms_functions.php';

function limitParentEntrySms($message, $maxLength = 140) {
    if (function_exists('mb_substr')) {
        return mb_substr($message, 0, $maxLength, 'UTF-8');
    }

    return substr($message, 0, $maxLength);
}

// Ensure optional tables exist before scan-time notification logic runs.
ensureAdminNotificationsTable($conn);

function queueAdminNotification($conn, $student, $entryLogId, $violationType, $violationCount) {
    $studentId = intval($student['id']);
    $normalizedType = strtolower(trim((string)$violationType));
    $safeType = preg_replace('/[^a-z0-9]+/i', '_', $normalizedType);
    $safeType = trim((string)$safeType, '_');
    $dedupeKey = 'repeat_violation_' . $studentId . '_' . date('o-W') . '_' . ($safeType !== '' ? $safeType : 'unknown');
    $message = sprintf(
        '%s (%s) has repeated the same violation, "%s", %d times in the current week.',
        $student['name'],
        $student['student_id'],
        $violationType,
        $violationCount
    );

    $checkStmt = $conn->prepare("SELECT id FROM admin_notifications WHERE dedupe_key = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('s', $dedupeKey);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        if ($existing && $existing->num_rows > 0) {
            $checkStmt->close();
            return false;
        }
        $checkStmt->close();
    }

    $insertStmt = $conn->prepare("INSERT INTO admin_notifications (student_id, entry_log_id, notification_type, message, dedupe_key) VALUES (?, ?, 'repeat_violation', ?, ?)");
    if (!$insertStmt) {
        error_log('Admin notification insert failed to prepare: ' . $conn->error);
        return false;
    }

    $insertStmt->bind_param('iiss', $studentId, $entryLogId, $message, $dedupeKey);
    $success = $insertStmt->execute();
    if (!$success) {
        error_log('Admin notification insert failed: ' . $insertStmt->error);
    }
    $insertStmt->close();
    return $success;
}

function maybeNotifyFrequentViolation($conn, $student, $entryLogId, $currentViolationType) {
    $threshold = defined('UNIFORM_ALERT_THRESHOLD') ? intval(UNIFORM_ALERT_THRESHOLD) : 3;
    $studentId = intval($student['id']);
    $currentTypes = preg_split('/\s*,\s*/', trim((string)$currentViolationType), -1, PREG_SPLIT_NO_EMPTY);

    if (empty($currentTypes)) {
        return false;
    }

    $countSql = "SELECT violation_type FROM violations WHERE student_id = ? AND YEARWEEK(violation_date, 1) = YEARWEEK(CURDATE(), 1)";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        error_log('Failed to prepare same-week violation count query: ' . $conn->error);
        return false;
    }

    $countStmt->bind_param('i', $studentId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();

    $typeCounts = [];
    while ($row = $countResult->fetch_assoc()) {
        $rowTypes = preg_split('/\s*,\s*/', trim((string)($row['violation_type'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($rowTypes as $rowType) {
            $normalized = trim((string)$rowType);
            if ($normalized === '') {
                continue;
            }
            $key = strtolower($normalized);
            $typeCounts[$key] = ($typeCounts[$key] ?? 0) + 1;
        }
    }
    $countStmt->close();

    foreach ($currentTypes as $targetType) {
        $normalizedTarget = strtolower(trim((string)$targetType));
        if ($normalizedTarget === '') {
            continue;
        }

        $matchingCount = $typeCounts[$normalizedTarget] ?? 0;
        if ($matchingCount >= $threshold) {
            return queueAdminNotification($conn, $student, $entryLogId, $targetType, $matchingCount);
        }
    }

    return false;
}

// Computer Vision Detection
// Call Python detection server if available, otherwise use simulation
$uniformCompliant = false;  // Default to non-compliant
$idVisible = false;
$shoesCompliant = false;
$detectionMode = 'simulation';

$detectionBoxes = [];  // Store detection boxes for frontend
$personDetected = false;  // Default to no person detected
$debugInfo = [];  // Debug info

if ($imageData) {
    // Try to call Python detection server
    $cvServerUrl = 'http://localhost:5000/detect';
    
    $ch = curl_init($cvServerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['image' => $imageData]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $debugInfo['http_code'] = $httpCode;
    $debugInfo['curl_error'] = $curlError;
    $debugInfo['response_length'] = strlen($response ?? '');
    
    if ($httpCode == 200 && $response) {
        $cvResult = json_decode($response, true);
        $debugInfo['cv_result'] = $cvResult ? 'parsed' : 'parse_failed';
        
        if ($cvResult && !isset($cvResult['error'])) {
            $personDetected = $cvResult['person_detected'] ?? false;
            $uniformCompliant = $cvResult['uniform_compliant'] ?? false;
            $idVisible = $cvResult['id_visible'] ?? false;
            $shoesCompliant = $cvResult['shoes_compliant'] ?? false;
            $detectionMode = $cvResult['mode'] ?? 'trained_model';
            $detectionBoxes = $cvResult['boxes'] ?? [];
            
            // If no person detected, mark everything as non-compliant
            if (!$personDetected) {
                $uniformCompliant = false;
                $idVisible = false;
                $shoesCompliant = false;
                $detectionMode = 'no_person';
            }
        } else {
            $debugInfo['error'] = $cvResult['error'] ?? 'unknown';
            $detectionMode = 'server_error';
        }
    } else {
        $debugInfo['failed'] = true;
        $detectionMode = 'connection_failed';
    }
} else {
    $debugInfo['no_image'] = true;
    $detectionMode = 'no_image';
}

// DO NOT use random simulation - if detection failed, mark as non-compliant
// This ensures the scanner doesn't randomly pass people

$overallCompliant = $uniformCompliant && $idVisible && $shoesCompliant;

$uniformStatus = $uniformCompliant ? 'compliant' : 'non-compliant';
$idVisibleStatus = $idVisible ? 'yes' : 'no';
$shoesStatus = $shoesCompliant ? 'compliant' : 'non-compliant';
$overallStatus = $overallCompliant ? 'compliant' : 'non-compliant';

// Log entry
$entryDate = date('Y-m-d');
$entryTime = date('H:i:s');

$logSql = "INSERT INTO entry_logs (student_id, rfid_tag, entry_date, entry_time, uniform_status, id_visible, overall_status) 
           VALUES ({$student['id']}, '$rfid', '$entryDate', '$entryTime', '$uniformStatus', '$idVisibleStatus', '$overallStatus')";
$conn->query($logSql);
$entryLogId = $conn->insert_id;

$parentPhone = trim((string)($student['parent_phone'] ?? ''));

// Always notify the parent when the student enters, and include the current compliance summary.
if ($parentPhone !== '') {
    $studentName = trim((string)$student['name']);
    $studentIdentifier = trim((string)$student['student_id']);
    $entryMessage = sprintf(
        'Entry: %s (%s) at %s %s. Status: %s. U:%s. ID:%s. Shoes:%s.',
        $studentName,
        $studentIdentifier,
        $entryDate,
        $entryTime,
        $overallStatus,
        $uniformStatus,
        $idVisibleStatus,
        $shoesStatus
    );
    $entryMessage = limitParentEntrySms($entryMessage);
    sendSmsNotification($parentPhone, $entryMessage);
}

$ticket = null;

// If non-compliant, create violation ticket
if (!$overallCompliant) {
    // Generate ticket number
    $ticketNumber = 'VT-' . date('Ymd') . '-' . str_pad($entryLogId, 4, '0', STR_PAD_LEFT);
    
    // Determine violation type
    $violationTypes = [];
    if (!$uniformCompliant) $violationTypes[] = 'Improper Uniform';
    if (!$idVisible) $violationTypes[] = 'ID Not Visible';
    if (!$shoesCompliant) $violationTypes[] = 'Non-compliant Shoes';
    $violationType = implode(', ', $violationTypes);
    
    // Save violation image
    $violationImagePath = saveViolationImage($imageData, $student['id'], $violationType);
    
    // Escape image path for SQL
    $imagePath = $violationImagePath ? $conn->real_escape_string($violationImagePath) : NULL;
    $imagePathSql = $imagePath ? "'$imagePath'" : "NULL";
    
    // Create ticket with image path
    $ticketSql = "INSERT INTO violation_tickets (ticket_number, student_id, entry_log_id, violation_type, violation_details, violation_image, ticket_date) 
                  VALUES ('$ticketNumber', {$student['id']}, $entryLogId, '$violationType', 'Auto-generated by compliance system', $imagePathSql, '$entryDate')";
    $conn->query($ticketSql);
    $ticketId = $conn->insert_id;
    
    // Also log to violations table for dashboard with image path
    $violationSql = "INSERT INTO violations (student_id, violation_type, violation_image, violation_date) 
                     VALUES ({$student['id']}, '$violationType', $imagePathSql, '$entryDate')";
    $conn->query($violationSql);

    // Check if student has reached the violation threshold and notify parent if so
    $windowDays = defined('UNIFORM_ALERT_WINDOW_DAYS') ? intval(UNIFORM_ALERT_WINDOW_DAYS) : 7;
    $threshold = defined('UNIFORM_ALERT_THRESHOLD') ? intval(UNIFORM_ALERT_THRESHOLD) : 3;
    $countSql = "SELECT COUNT(*) as cnt FROM violations WHERE student_id = {$student['id']} AND violation_date >= DATE_SUB(CURDATE(), INTERVAL $windowDays DAY)";
    $countResult = $conn->query($countSql);
    $violationCount = $countResult ? intval(($countResult->fetch_assoc()['cnt'] ?? 0)) : 0;
    if ($violationCount >= $threshold && $parentPhone !== '') {
        // Use admin_notifications table to dedupe parent summon messages (one per student per day)
        $parentDedupeKey = 'parent_summon_' . intval($student['id']) . '_' . date('Y-m-d');

        $checkStmt = $conn->prepare("SELECT id FROM admin_notifications WHERE dedupe_key = ? LIMIT 1");
        $shouldSendParentSummon = true;
        if ($checkStmt) {
            $checkStmt->bind_param('s', $parentDedupeKey);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();
            if ($existing && $existing->num_rows > 0) {
                $shouldSendParentSummon = false;
            }
            $checkStmt->close();
        }

        if ($shouldSendParentSummon) {
            $summonMessage = sprintf(
                'Notice: Your child %s (%s) has accumulated %d violation(s) in the last %d days. Please contact the school office to schedule a meeting. Further violations may result in disciplinary action.',
                $student['name'],
                $student['student_id'],
                $violationCount,
                $windowDays
            );

            // Keep parent summon records out of the admin dashboard alert list; this message is for SMS only.
            $insertStmt = $conn->prepare("INSERT INTO admin_notifications (student_id, entry_log_id, notification_type, message, dedupe_key) VALUES (?, ?, 'parent_summon', ?, ?)");
            if ($insertStmt) {
                $sid = intval($student['id']);
                $eid = intval($entryLogId);
                $insertStmt->bind_param('iiss', $sid, $eid, $summonMessage, $parentDedupeKey);
                $insOk = $insertStmt->execute();
                $insertStmt->close();

                if ($insOk) {
                    sendSmsNotification($parentPhone, $summonMessage);
                } else {
                    error_log('Parent summon insert failed: ' . $insertStmt->error);
                    sendSmsNotification($parentPhone, $summonMessage);
                }
            } else {
                error_log('Parent summon insert failed to prepare: ' . $conn->error);
                sendSmsNotification($parentPhone, $summonMessage);
            }
        }
    }

    if (!empty($violationType)) {
        maybeNotifyFrequentViolation($conn, $student, $entryLogId, $violationType);
    }
    
    $ticket = [
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber,
        'violation_type' => $violationType,
        'image' => $violationImagePath
    ];
}

echo json_encode([
    'success' => true,
    'gate_opened' => $gateOpened,
    'student' => [
        'id' => $student['id'],
        'student_id' => $student['student_id'],
        'name' => $student['name'],
        'grade' => $student['grade'],
        'section' => $student['section']
    ],
    'compliance' => [
        'uniform_status' => $uniformStatus,
        'id_visible' => $idVisibleStatus,
        'shoes_status' => $shoesStatus,
        'overall_status' => $overallStatus,
        'detection_mode' => $detectionMode,
        'person_detected' => $personDetected,
        'boxes' => $detectionBoxes,
        'debug' => $debugInfo  // Debug info for troubleshooting
    ],
    'entry_time' => date('H:i'),
    'ticket' => $ticket
    ,'solenoid_debug' => $GLOBALS['solenoid_debug'] ?? null
]);
?>