<?php
session_start();
include 'config.php';

$returnPage = !empty($_SESSION['admin_logged_in']) ? 'admin_dashboard.php' : 'index.php';

// Handle bulk delete (multiple IDs)
if (isset($_GET['ids'])) {
    $ids = $_GET['ids'];
    // Sanitize: only allow comma-separated integers
    $idArray = array_map('intval', explode(',', $ids));
    $idArray = array_filter($idArray, function($id) { return $id > 0; });
    
    if (!empty($idArray)) {
        $idList = implode(',', $idArray);
        $sql = "DELETE FROM students WHERE id IN ($idList)";
        
        if ($conn->query($sql) === TRUE) {
            $count = $conn->affected_rows;
            header("Location: $returnPage?deleted=$count");
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        header("Location: $returnPage");
    }
}
// Handle single delete
elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "DELETE FROM students WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: $returnPage?deleted=1");
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    header("Location: $returnPage");
}
?>
