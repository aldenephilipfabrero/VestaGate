<?php
session_start();
include 'config.php';

$returnPage = !empty($_SESSION['admin_logged_in']) ? 'admin_dashboard.php' : 'index.php';

if (isset($_POST['submit'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $grade = intval($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);
    $rfid = $conn->real_escape_string($_POST['rfid_tag']);
    $parent_phone = trim((string)($_POST['parent_phone'] ?? ''));
    $parent_phone_sql = $parent_phone !== '' ? "'" . $conn->real_escape_string($parent_phone) . "'" : "NULL";

    ensureParentPhoneColumn($conn);

    $parentPhoneColumnExists = false;
    $columnResult = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_phone'");
    if ($columnResult && $columnResult->num_rows > 0) {
        $parentPhoneColumnExists = true;
    }
    
    // Check if name already exists
    $checkName = $conn->query("SELECT id FROM students WHERE name = '$name'");
    if ($checkName->num_rows > 0) {
        header("Location: $returnPage?error=duplicate_name");
        exit;
    }
    
    // Check if student_id already exists
    $checkId = $conn->query("SELECT id FROM students WHERE student_id = '$student_id'");
    if ($checkId->num_rows > 0) {
        header("Location: $returnPage?error=duplicate_id");
        exit;
    }

    // Check if the RFID tag is already assigned
    $checkRfid = $conn->query("SELECT id FROM students WHERE rfid_tag = '$rfid'");
    if ($checkRfid->num_rows > 0) {
        header("Location: $returnPage?error=duplicate_rfid");
        exit;
    }
    
    // Insert into database
        if ($parentPhoneColumnExists) {
            $sql = "INSERT INTO students (student_id, name, grade, section, rfid_tag, parent_phone, unifrom_status, uniform_status) 
                    VALUES ('$student_id', '$name', $grade, '$section', '$rfid', $parent_phone_sql, 'non-compliant', 'non-compliant')";
        } else {
            $sql = "INSERT INTO students (student_id, name, grade, section, rfid_tag, unifrom_status, uniform_status) 
                    VALUES ('$student_id', '$name', $grade, '$section', '$rfid', 'non-compliant', 'non-compliant')";
        }
    
    if ($conn->query($sql) === TRUE) {
        header("Location: $returnPage?success=1");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>