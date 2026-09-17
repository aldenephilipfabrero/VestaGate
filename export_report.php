<?php
include 'config.php';

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="compliance_report_' . $startDate . '_to_' . $endDate . '.csv"');

$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['Date', 'Time', 'Student ID', 'Name', 'Grade', 'Section', 'Uniform Status', 'ID Visible', 'Overall Status', 'Ticket Number']);

// Get data
$result = $conn->query("
    SELECT e.entry_date, e.entry_time, s.student_id, s.name, s.grade, s.section, 
           e.uniform_status, e.id_visible, e.overall_status, t.ticket_number
    FROM entry_logs e
    JOIN students s ON e.student_id = s.id
    LEFT JOIN violation_tickets t ON t.entry_log_id = e.id
    WHERE e.entry_date BETWEEN '$startDate' AND '$endDate'
    ORDER BY e.entry_date DESC, e.entry_time DESC
");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['entry_date'],
        $row['entry_time'],
        $row['student_id'],
        $row['name'],
        $row['grade'],
        $row['section'],
        $row['uniform_status'],
        $row['id_visible'],
        $row['overall_status'],
        $row['ticket_number'] ?? ''
    ]);
}

fclose($output);
exit;
?>
