<?php
include 'config.php';
$r = $conn->query("SELECT rfid_tag,id FROM students LIMIT 1");
if ($r) {
    $row = $r->fetch_assoc();
    if ($row) {
        echo json_encode($row);
        exit(0);
    }
}
echo "NO_STUDENTS";
?>