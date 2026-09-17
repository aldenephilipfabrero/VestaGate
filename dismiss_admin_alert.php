<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

include 'config.php';

$alertId = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : 0;
if ($alertId > 0) {
    $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $alertId);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: admin_dashboard.php');
exit;
