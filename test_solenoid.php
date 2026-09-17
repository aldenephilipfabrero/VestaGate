<?php
include 'config.php';

function tryOpenPort($port) {
    $result = ['port' => $port, 'opened' => false, 'errno' => null, 'errstr' => null];
    $fp = @fopen($port, 'w');
    if ($fp !== false) {
        fwrite($fp, "PING\n");
        fflush($fp);
        fclose($fp);
        $result['opened'] = true;
    } else {
        $result['opened'] = false;
    }
    return $result;
}

$ports = [];
if (stripos(PHP_OS, 'WIN') === 0) {
    $base = SOLENOID_SERIAL_PORT;
    $ports = [
        $base,
        $base . ':',
        "\\\\.\\" . $base
    ];
} else {
    $ports = [
        '/dev/' . SOLENOID_SERIAL_PORT,
        SOLENOID_SERIAL_PORT
    ];
}

$results = [];
foreach ($ports as $p) {
    $results[] = tryOpenPort($p);
}

header('Content-Type: application/json');
echo json_encode(['tested_ports' => $results, 'configured' => SOLENOID_SERIAL_PORT, 'enabled' => SOLENOID_ENABLE]);
?>