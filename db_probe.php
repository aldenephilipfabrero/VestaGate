<?php
require 'config.php';
echo "DB_OK\n";
$tables = $conn->query('SHOW TABLES');
while ($row = $tables->fetch_row()) {
    echo $row[0] . "\n";
}
echo "--- students ---\n";
$students = $conn->query('SELECT id, student_id, name, rfid_tag, parent_phone, grade, section FROM students LIMIT 5');
while ($r = $students->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
?>
