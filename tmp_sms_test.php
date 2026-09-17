<?php
require 'sms_functions.php';
$result = sendSmsNotification('+639171234567', 'PHP bridge test');
echo $result ? 'true' : 'false';
?>
