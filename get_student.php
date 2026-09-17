<?php
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM students WHERE id = $id");
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No ID provided']);
}
?>
