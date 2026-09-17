<?php
session_start();
include 'config.php';

$returnPage = !empty($_SESSION['admin_logged_in']) ? 'admin_dashboard.php' : 'index.php';

if (isset($_POST['submit'])) {
    $id = intval($_POST['id']);
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $grade = intval($_POST['grade']);
    $section = $conn->real_escape_string($_POST['section']);
    $rfid_tag = $conn->real_escape_string($_POST['rfid_tag']);
    $parent_phone = trim((string)($_POST['parent_phone'] ?? ''));
    $parent_phone_sql = $parent_phone !== '' ? "'" . $conn->real_escape_string($parent_phone) . "'" : "NULL";

    ensureParentPhoneColumn($conn);

    $parentPhoneColumnExists = false;
    $columnResult = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_phone'");
    if ($columnResult && $columnResult->num_rows > 0) {
        $parentPhoneColumnExists = true;
    }

    $checkDuplicate = $conn->query("SELECT student_id, rfid_tag FROM students WHERE id <> $id AND (student_id = '$student_id' OR rfid_tag = '$rfid_tag') LIMIT 1");
    if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
        $duplicate = $checkDuplicate->fetch_assoc();
        if ($duplicate['student_id'] === $student_id) {
            header("Location: $returnPage?student_id=$id&error=duplicate_id");
        } else {
            header("Location: $returnPage?student_id=$id&error=duplicate_rfid");
        }
        exit;
    }
    
    if ($parentPhoneColumnExists) {
        $sql = "UPDATE students SET 
                student_id = '$student_id',
                name = '$name', 
                grade = $grade,
                section = '$section',
                rfid_tag = '$rfid_tag',
                parent_phone = $parent_phone_sql 
                WHERE id = $id";
    } else {
        $sql = "UPDATE students SET 
                student_id = '$student_id',
                name = '$name', 
                grade = $grade,
                section = '$section',
                rfid_tag = '$rfid_tag' 
                WHERE id = $id";
    }
    
    if ($conn->query($sql) === TRUE) {
        header("Location: $returnPage?student_id=$id&updated=1");
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    header("Location: $returnPage");
}
?>
