<?php
/**
 * Get ticket data for direct printing
 * Returns ticket information as JSON for the Python print server
 */
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No ticket ID specified']);
    exit;
}

$id = intval($_GET['id']);

$result = $conn->query("
    SELECT t.*, s.name, s.student_id, s.grade, s.section 
    FROM violation_tickets t 
    JOIN students s ON t.student_id = s.id 
    WHERE t.id = $id
");

$ticket = $result->fetch_assoc();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'ticket' => [
        'ticket_number' => $ticket['ticket_number'],
        'date' => date('m/d/Y', strtotime($ticket['ticket_date'])),
        'time' => date('h:i A', strtotime($ticket['created_at'])),
        'student_id' => $ticket['student_id'],
        'name' => $ticket['name'],
        'grade' => $ticket['grade'],
        'section' => $ticket['section'],
        'violation_type' => $ticket['violation_type'],
        'violation_details' => $ticket['violation_details'] ?? ''
    ]
]);
?>
