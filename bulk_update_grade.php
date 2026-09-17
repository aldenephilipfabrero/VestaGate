<?php
include 'config.php';

if (isset($_POST['submit']) && isset($_POST['student_ids']) && isset($_POST['grade'])) {
    $ids = $_POST['student_ids'];
    $grade = intval($_POST['grade']);
    
    // Validate grade
    if ($grade < 1 || $grade > 12) {
        header("Location: index.php?error=invalid_grade");
        exit;
    }
    
    // Sanitize: only allow comma-separated integers
    $idArray = array_map('intval', explode(',', $ids));
    $idArray = array_filter($idArray, function($id) { return $id > 0; });
    
    if (!empty($idArray)) {
        $idList = implode(',', $idArray);
        $sql = "UPDATE students SET grade = $grade WHERE id IN ($idList)";
        
        if ($conn->query($sql) === TRUE) {
            $count = $conn->affected_rows;
            header("Location: index.php?grade_updated=$count");
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        header("Location: index.php");
    }
} else {
    header("Location: index.php");
}
?>
