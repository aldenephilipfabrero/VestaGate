<?php
include 'config.php';
$port = SOLENOID_SERIAL_PORT;
$path = $port; // use plain COM6 which previous test showed opens
$fp = @fopen($path, 'r+');
if (!$fp) {
    echo "FAILED_OPEN\n";
    exit(1);
}
stream_set_blocking($fp, false);
// Drain any initial data
// Read initial messages for 2 seconds (Arduino may send SOLENOID_READY after reset)
$start = time();
while (time() - $start < 2) {
    $line = fgets($fp);
    if ($line !== false) echo "INIT: " . trim($line) . "\n";
    usleep(100000);
}
// Send OPEN command (use double quotes so \n is interpreted)
$cmd = "OPEN|2000\n";
fwrite($fp, $cmd);
fflush($fp);
// Read responses for up to 5 seconds
$end = time() + 5;
while (time() < $end) {
    $line = fgets($fp);
    if ($line !== false) {
        echo "RESP: " . trim($line) . "\n";
    }
    usleep(100000);
}
fclose($fp);
?>