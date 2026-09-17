<?php
$payload = json_encode([
    'pins' => [2, 3],
    'value' => 1,
    'duration' => 3000,
]);

$ch = curl_init('http://localhost:5010/pulse');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
if ($response === false) {
    echo 'CURL_ERR: ' . curl_error($ch) . PHP_EOL;
} else {
    echo $response . PHP_EOL;
}
curl_close($ch);
