<?php
include 'config.php';

header('Content-Type: application/json');

$today = date('Y-m-d');

$result = $conn->query("
    SELECT e.entry_time, s.name, e.overall_status 
    FROM entry_logs e 
    JOIN students s ON e.student_id = s.id 
    WHERE e.entry_date = '$today' 
    ORDER BY e.id DESC 
    LIMIT 10
");

$scans = [];
while ($row = $result->fetch_assoc()) {
    $scans[] = [
        'name' => $row['name'],
        'time' => date('H:i', strtotime($row['entry_time'])),
        'compliant' => $row['overall_status'] === 'compliant'
    ];
}

echo json_encode($scans);
?>
