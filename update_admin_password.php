<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

include 'config.php';

$credentialsFile = __DIR__ . '/admin_credentials.php';
$currentPassword = trim((string)($_POST['current_password'] ?? ''));
$newPassword = trim((string)($_POST['new_password'] ?? ''));
$confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

if (!adminPasswordMatches($currentPassword)) {
    header('Location: admin_dashboard.php?password_error=current');
    exit;
}

if (strlen($newPassword) < 6) {
    header('Location: admin_dashboard.php?password_error=short');
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: admin_dashboard.php?password_error=match');
    exit;
}

$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$fileContent = "<?php\nreturn [\n    'username' => '" . addslashes(ADMIN_USERNAME) . "',\n    'password_hash' => '" . addslashes($newPasswordHash) . "',\n];\n";

if (@file_put_contents($credentialsFile, $fileContent) === false) {
    header('Location: admin_dashboard.php?password_error=write');
    exit;
}

$_SESSION['admin_logged_in'] = true;
header('Location: admin_dashboard.php?password_updated=1');
exit;
