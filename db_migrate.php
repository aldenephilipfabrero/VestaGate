<?php
/**
 * Database Migration - Add Image, SMS, and Alert Support
 * Run this once to add new columns and tables used by the notification flow
 */

include 'config.php';

echo "Starting database migration...\n";

// 0. Add parent phone support to students
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_phone'");
if (!($result && $result->num_rows > 0)) {
    $sql0 = "ALTER TABLE students ADD COLUMN parent_phone VARCHAR(30) NULL DEFAULT NULL AFTER rfid_tag";
    if ($conn->query($sql0) === TRUE) {
        echo "✓ Added parent_phone column to students\n";
    } else {
        echo "✗ Error adding parent_phone column: " . $conn->error . "\n";
    }
}

// 1. Add violation_image column to violation_tickets table
$result = $conn->query("SHOW COLUMNS FROM violation_tickets LIKE 'violation_image'");
if (!($result && $result->num_rows > 0)) {
    $sql1 = "ALTER TABLE violation_tickets ADD COLUMN violation_image VARCHAR(500) NULL DEFAULT NULL AFTER violation_details";
    if ($conn->query($sql1) === TRUE) {
        echo "✓ Added violation_image column to violation_tickets\n";
    } else {
        echo "✗ Error adding violation_image column: " . $conn->error . "\n";
    }
}

// 2. Add violation_image column to violations table (for tracking)
$result = $conn->query("SHOW COLUMNS FROM violations LIKE 'violation_image'");
if (!($result && $result->num_rows > 0)) {
    $sql2 = "ALTER TABLE violations ADD COLUMN violation_image VARCHAR(500) NULL DEFAULT NULL";
    if ($conn->query($sql2) === TRUE) {
        echo "✓ Added violation_image column to violations\n";
    } else {
        echo "✗ Error adding violation_image to violations: " . $conn->error . "\n";
    }
}

// 3. Add capture_timestamp to better track when image was captured
$result = $conn->query("SHOW COLUMNS FROM violation_tickets LIKE 'capture_timestamp'");
if (!($result && $result->num_rows > 0)) {
    $sql3 = "ALTER TABLE violation_tickets ADD COLUMN capture_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP AFTER created_at";
    if ($conn->query($sql3) === TRUE) {
        echo "✓ Added capture_timestamp column to violation_tickets\n";
    } else {
        echo "✗ Error adding capture_timestamp: " . $conn->error . "\n";
    }
}

// 4. Create admin notification table for repeated uniform violations
$sql4 = "CREATE TABLE IF NOT EXISTS admin_notifications (
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
if ($conn->query($sql4) === TRUE) {
    echo "✓ Ensured admin_notifications table exists\n";
} else {
    echo "✗ Error creating admin_notifications table: " . $conn->error . "\n";
}

// 5. Prevent duplicate student credentials at the database level
$studentIdIndex = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'uniq_students_student_id'");
if (!($studentIdIndex && $studentIdIndex->num_rows > 0)) {
    $sql5 = "ALTER TABLE students ADD UNIQUE KEY uniq_students_student_id (student_id)";
    if ($conn->query($sql5) === TRUE) {
        echo "✓ Added unique student ID constraint\n";
    } else {
        echo "✗ Error adding unique student ID constraint: " . $conn->error . "\n";
    }
}

$rfidIndex = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'uniq_students_rfid_tag'");
if (!($rfidIndex && $rfidIndex->num_rows > 0)) {
    $sql6 = "ALTER TABLE students ADD UNIQUE KEY uniq_students_rfid_tag (rfid_tag)";
    if ($conn->query($sql6) === TRUE) {
        echo "✓ Added unique RFID constraint\n";
    } else {
        echo "✗ Error adding unique RFID constraint: " . $conn->error . "\n";
    }
}

echo "\n✓ Migration completed successfully!\n";
?>
