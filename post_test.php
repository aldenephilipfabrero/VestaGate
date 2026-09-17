<?php
$payload = json_encode(['rfid' => '0610034631']);
$ch = curl_init('http://localhost:8000/process_scan.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
if ($res === false) {
    echo 'CURL_ERR: ' . curl_error($ch);
} else {
    echo $res;
}
curl_close($ch);
?>