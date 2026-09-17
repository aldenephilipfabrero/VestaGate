<?php
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $today = date('Y-m-d');
    
    // Get student info
    $result = $conn->query("SELECT * FROM students WHERE id = $id");
    $student = $result->fetch_assoc();
    
    if ($student) {
        // Get all violations with images
        $violations = [];
        $vResult = $conn->query("SELECT * FROM violations WHERE student_id = $id ORDER BY violation_date DESC");
        while ($v = $vResult->fetch_assoc()) {
            // Add image URL if image exists
            if (!empty($v['violation_image'])) {
                $v['image_url'] = $v['violation_image'];
                $v['has_image'] = true;
            } else {
                $v['has_image'] = false;
            }
            $violations[] = $v;
        }
        
        // Get today's violations with images
        $todayViolations = [];
        $tvResult = $conn->query("SELECT * FROM violations WHERE student_id = $id AND violation_date = '$today'");
        while ($tv = $tvResult->fetch_assoc()) {
            // Add image URL if image exists
            if (!empty($tv['violation_image'])) {
                $tv['image_url'] = $tv['violation_image'];
                $tv['has_image'] = true;
            } else {
                $tv['has_image'] = false;
            }
            $todayViolations[] = $tv;
        }
        
        echo json_encode([
            'success' => true,
            'student' => $student,
            'violations' => $violations,
            'todayViolations' => $todayViolations
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
}
?>
