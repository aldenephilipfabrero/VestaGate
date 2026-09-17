<?php
// Lightweight include for SMS functions used by CLI tests
include 'config.php';

function detectWindowsSerialPorts() {
    $ports = [];

    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_SerialPort -ErrorAction SilentlyContinue | ForEach-Object { $_.DeviceID + \"|\" + $_.Name }" 2>$null';
    @exec($cmd, $output, $rc);
    if (is_array($output)) {
        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;

            $parts = explode('|', $line, 2);
            $deviceId = trim((string)($parts[0] ?? ''));
            $name = trim((string)($parts[1] ?? ''));

            if ($deviceId === '' || stripos($deviceId, 'COM') === false) {
                continue;
            }

            if (stripos($name, 'Bluetooth') !== false || stripos($deviceId, 'Bluetooth') !== false) {
                continue;
            }

            $ports[] = $deviceId;
        }
    }

    if (count($ports) === 0 && stripos(PHP_OS, 'WIN') === 0) {
        for ($i = 1; $i <= 20; $i++) {
            $candidate = 'COM' . $i;
            if (stripos($candidate, 'Bluetooth') !== false) {
                continue;
            }
            $ports[] = $candidate;
        }
    }

    $seen = [];
    $unique = [];
    foreach ($ports as $port) {
        $port = trim((string)$port);
        if ($port === '' || isset($seen[$port])) continue;
        $seen[$port] = true;
        $unique[] = $port;
    }

    return $unique;
}

function listArduinoPortCandidates($preferredPort) {
    $preferred = trim((string)$preferredPort);
    $candidates = [];

    if ($preferred !== '' && $preferred !== 'auto') {
        $candidates[] = $preferred;
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        foreach (detectWindowsSerialPorts() as $port) {
            $candidates[] = $port;
        }
        for ($i = 1; $i <= 20; $i++) {
            $candidates[] = 'COM' . $i;
        }
    } else {
        for ($i = 0; $i <= 20; $i++) {
            $candidates[] = '/dev/ttyUSB' . $i;
            $candidates[] = '/dev/ttyACM' . $i;
        }
    }

    $seen = [];
    $unique = [];
    foreach ($candidates as $port) {
        $port = trim((string)$port);
        if ($port === '' || isset($seen[$port])) continue;
        $seen[$port] = true;
        $unique[] = $port;
    }

    return $unique;
}

function detectArduinoSerialPort() {
    $preferred = defined('SMS_ARDUINO_PORT') ? trim((string)SMS_ARDUINO_PORT) : '';
    foreach (listArduinoPortCandidates($preferred) as $port) {
        $target = $port;
        if (stripos(PHP_OS, 'WIN') === 0 && strpos($target, ':') === false) {
            $target .= ':';
        }

        $probe = @fopen($target, 'r+b');
        if ($probe === false) {
            continue;
        }

        stream_set_blocking($probe, false);
        stream_set_timeout($probe, 2);
        @fwrite($probe, "AT\r\n");
        usleep(300000);
        $response = '';
        $start = microtime(true);
        while ((microtime(true) - $start) < 2.0) {
            $chunk = fread($probe, 256);
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
            }
            if (stripos($response, 'OK') !== false) {
                fclose($probe);
                return $port;
            }
            usleep(100000);
        }
        fclose($probe);
    }

    return false;
}

function resolveArduinoPort($preferredPort) {
    $preferred = trim((string)$preferredPort);
    foreach (listArduinoPortCandidates($preferred) as $port) {
        if (probeModemGatewayPort($port) === true) {
            return $port;
        }
    }

    if ($preferred !== '' && $preferred !== 'auto') {
        return $preferred;
    }

    return false;
}

function probeModemGatewayPort($port) {
    $port = trim((string)$port);
    if ($port === '') {
        return false;
    }

    $target = $port;
    if (stripos(PHP_OS, 'WIN') === 0) {
        $target .= ':';
    } else {
        $target = '/dev/' . ltrim($port, '/dev/');
    }

    $probe = @fopen($target, 'r+b');
    if ($probe === false) {
        return false;
    }

    stream_set_blocking($probe, false);
    stream_set_timeout($probe, 2);

    $writes = ["AT\r\n", "AT+CPIN?\r\n", "AT+CREG?\r\n"];
    $text = '';

    foreach ($writes as $write) {
        @fwrite($probe, $write);
        usleep(250000);
        $start = microtime(true);
        while ((microtime(true) - $start) < 1.2) {
            $chunk = fread($probe, 256);
            if ($chunk !== false && $chunk !== '') {
                $text .= $chunk;
            }
            if (stripos($text, 'OK') !== false && stripos($text, '+CPIN: READY') !== false) {
                break;
            }
            usleep(100000);
        }
    }

    fclose($probe);

    if (stripos($text, 'OK') !== false && stripos($text, '+CPIN: READY') !== false) {
        return true;
    }

    return false;
}

function parseSuccessfulModemSmsResponse($text) {
    $normalized = trim((string)$text);
    $upper = strtoupper($normalized);

    if (stripos($normalized, 'OK:SMS_SENT') !== false) {
        return true;
    }

    if (stripos($normalized, '+CMGS:') !== false) {
        return true;
    }

    if (stripos($normalized, 'OK') !== false && stripos($normalized, 'ERROR') === false && stripos($upper, 'SIM_NOT_READY') === false) {
        // The modem echo can legitimately contain a standalone OK after the command is accepted,
        // so tolerate a single 'OK' only when there is no error collision and the SMS sequence is not a raw failed state.
        return true;
    }

    return false;
}

function sendSmsNotification($phoneNumber, $message) {
    $phoneNumber = trim((string)$phoneNumber);
    $message = trim((string)$message);

    if ($phoneNumber === '' || $message === '') {
        return false;
    }

    if (!defined('SMS_ENABLE') || !SMS_ENABLE) {
        error_log('SMS notification skipped: SMS_ENABLE is false for ' . $phoneNumber);
        return false;
    }

    if (defined('SMS_DEMO_MODE') && SMS_DEMO_MODE) {
        $demoLog = __DIR__ . '/logs/sms_demo.log';
        if (!is_dir(dirname($demoLog))) @mkdir(dirname($demoLog), 0755, true);
        file_put_contents(
            $demoLog,
            date('[Y-m-d H:i:s] ') . 'DEMO SMS to ' . $phoneNumber . ': ' . $message . PHP_EOL,
            FILE_APPEND
        );
        return true;
    }

    $method = defined('SMS_METHOD') ? trim((string)SMS_METHOD) : '';
    if ($method === 'semaphore') {
        return sendSmsViaSemaphore($phoneNumber, $message);
    }

    if ($method === 'bridge') {
        return sendSmsViaBridge($phoneNumber, $message);
    }

    if ($method !== 'arduino') {
        error_log('SMS notification skipped: unsupported SMS_METHOD ' . $method);
        return false;
    }

    if (!defined('SMS_ARDUINO_PORT') || SMS_ARDUINO_PORT === '') {
        error_log('SMS notification skipped: SMS_ARDUINO_PORT not configured for ' . $phoneNumber);
        return false;
    }

    return sendSmsViaArduino($phoneNumber, $message);
}

function sendSmsViaSemaphore($phoneNumber, $message) {
    $apiKey = defined('SMS_SEMAPHORE_API_KEY') ? trim((string)SMS_SEMAPHORE_API_KEY) : '';
    $apiUrl = defined('SMS_SEMAPHORE_API_URL') ? trim((string)SMS_SEMAPHORE_API_URL) : '';
    $senderName = defined('SMS_SEMAPHORE_SENDER_NAME') ? trim((string)SMS_SEMAPHORE_SENDER_NAME) : '';
    $timeout = defined('SMS_SEMAPHORE_TIMEOUT_SEC') ? (int)SMS_SEMAPHORE_TIMEOUT_SEC : 15;

    if ($apiKey === '' || $apiUrl === '') {
        error_log('Semaphore SMS skipped: API key or API URL is not configured');
        return false;
    }

    $payload = [
        'apikey' => $apiKey,
        
        'message' => (string)$message,
    ];
    if ($senderName !== '') {
        $payload['sendername'] = $senderName;
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload, '', '&'),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => max(1, $timeout),
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw !== false && $httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($raw, true);
        $record = is_array($decoded[0] ?? null) ? $decoded[0] : $decoded;
        if (is_array($record) && isset($record['message_id'])) {
            error_log('Semaphore SMS accepted for ' . $phoneNumber
                . ': message_id=' . (string)($record['message_id'] ?? $record['id'] ?? 'unknown')
                . ' status=' . (string)($record['status'] ?? 'unknown')
                . ' response=' . json_encode($decoded));
            return true;
        }
    }

    error_log('Semaphore SMS failed for ' . $phoneNumber . ': HTTP ' . $httpCode
        . ($curlError !== '' ? ' cURL ' . $curlError : '') . ' body ' . (string)$raw);
    return false;
}

function sendArduinoCommand($command, $preferredPort = null) {
    $preferredPort = $preferredPort === null ? (defined('SMS_ARDUINO_PORT') ? trim((string)SMS_ARDUINO_PORT) : '') : trim((string)$preferredPort);
    $port = resolveArduinoPort($preferredPort);
    if ($port === '' || $port === 'auto' || $port === false) {
        error_log('No Arduino serial port detected for command: ' . $command);
        return false;
    }

    $baudrate = defined('SMS_ARDUINO_BAUDRATE') ? SMS_ARDUINO_BAUDRATE : 9600;
    $timeout = defined('SMS_ARDUINO_TIMEOUT_SEC') ? intval(SMS_ARDUINO_TIMEOUT_SEC) : 10;

    $command = trim((string)$command) . "\r\n";

    $logFile = __DIR__ . '/logs/sms_gateway.log';
    if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
    $log = function($line) use ($logFile) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $line . PHP_EOL, FILE_APPEND);
    };

    $log('Attempting raw Arduino command on ' . $port . ': ' . trim($command));

    try {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $openTarget = $port . ':';
            $handle = @fopen($openTarget, 'r+b');
            if ($handle === false) {
                $log('fopen failed for ' . $openTarget . ' (raw command)');
                $psSafe = str_replace('"', '\"', $command);
                $psSafe = str_replace("'", "''", $psSafe);
                $psLines = array(
                    '$sp = New-Object System.IO.Ports.SerialPort -ArgumentList "' . $port . '",' . $baudrate . ',"None",8,"One"',
                    '$sp.Open()',
                    'Start-Sleep -Milliseconds 2000',
                    '$sp.WriteLine("' . $psSafe . '")',
                    'Start-Sleep -Milliseconds 1000',
                    '$out = $sp.ReadExisting()',
                    '$sp.Close()',
                    'Write-Output $out'
                );
                $psScript = implode("\r\n", $psLines);
                $tmp = tempnam(sys_get_temp_dir(), 'arduino');
                file_put_contents($tmp . '.ps1', $psScript);
                $psFile = $tmp . '.ps1';
                exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($psFile) . ' 2>&1', $psOut, $psRc);
                $log('Raw PowerShell rc=' . $psRc . ' output: ' . implode(' | ', $psOut));
                @unlink($psFile);
                return $psRc === 0;
            }
        } else {
            $openTarget = '/dev/' . $port;
            $handle = @fopen($openTarget, 'r+b');
            if ($handle === false) {
                $log('fopen failed for ' . $openTarget . ' (raw command)');
                return false;
            }

            stream_set_blocking($handle, true);
            stream_set_timeout($handle, $timeout);
            $bytesWritten = fwrite($handle, $command);
            fclose($handle);
            return $bytesWritten !== false && $bytesWritten > 0;
        }
    } catch (Exception $e) {
        $log('Raw Arduino command exception: ' . $e->getMessage());
        return false;
    }

    return false;
}

function sendSmsViaBridge($phoneNumber, $message) {
    $bridgeUrl = defined('SMS_BRIDGE_URL') ? trim((string)SMS_BRIDGE_URL) : '';
    if ($bridgeUrl === '') {
        return false;
    }

    $payload = json_encode([
        'phone' => (string)$phoneNumber,
        'message' => (string)$message,
    ]);

    $ch = curl_init($bridgeUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 10,
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $success = $decoded['success'] ?? false;
            $message = $decoded['message'] ?? '';
            if ($success === true || $message === 'sms_sent') {
                return true;
            }
        }
    }

    error_log('SMS bridge failed for ' . (string)$phoneNumber . ': HTTP ' . (string)$httpCode . ' body ' . (string)$raw);
    return false;
}

function sendSmsViaArduino($phoneNumber, $message) {
    $preferredPort = defined('SMS_ARDUINO_PORT') ? trim((string)SMS_ARDUINO_PORT) : '';
    $port = resolveArduinoPort($preferredPort);
    if ($port === '' || $port === 'auto' || $port === false) {
        error_log('No valid Arduino/SIM900A serial port detected. Demo mode fallback is active.');
        if (defined('SMS_DEMO_MODE') && SMS_DEMO_MODE) {
            $demoLog = __DIR__ . '/logs/sms_demo.log';
            if (!is_dir(dirname($demoLog))) @mkdir(dirname($demoLog), 0755, true);
            file_put_contents($demoLog, date('[Y-m-d H:i:s] ') . 'DEMO SMS fallback via port detection to ' . $phoneNumber . ': ' . $message . PHP_EOL, FILE_APPEND);
            return true;
        }
        return sendSmsViaBridge($phoneNumber, $message);
    }

    $baudrate = defined('SMS_ARDUINO_BAUDRATE') ? SMS_ARDUINO_BAUDRATE : 9600;
    $timeout = defined('SMS_ARDUINO_TIMEOUT_SEC') ? intval(SMS_ARDUINO_TIMEOUT_SEC) : 10;

    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    if (strpos($phoneNumber, '+') !== 0) {
        if (preg_match('/^0(9\d{9})$/', $phoneNumber, $m)) {
            $phoneNumber = '+63' . $m[1];
        } elseif (preg_match('/^(9\d{9})$/', $phoneNumber, $m)) {
            $phoneNumber = '+63' . $m[1];
        }
    }
    $message = substr(trim((string)$message), 0, 160);
    $command = 'AT|' . $phoneNumber . '|' . $message . "\r\n";

    $logFile = __DIR__ . '/logs/sms_gateway.log';
    if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
    $log = function($line) use ($logFile) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $line . PHP_EOL, FILE_APPEND);
    };

    $log('Attempting to open port ' . $port . ' (baud ' . $baudrate . ')');

    try {
        $handle = false;
        if (stripos(PHP_OS, 'WIN') === 0) {
            $openTarget = $port . ':';
            $handle = @fopen($openTarget, 'r+b');
            if ($handle === false) {
                $log('fopen failed for ' . $openTarget);
                $psSafe = str_replace("'", "''", $command);
                $psLines = [
                    '$sp = New-Object System.IO.Ports.SerialPort -ArgumentList "' . $port . '",' . $baudrate . ',"None",8,"One"',
                    '$sp.Open()',
                    'Start-Sleep -Milliseconds 2000',
                    '$sp.WriteLine(\'' . $psSafe . '\')',
                    'Start-Sleep -Milliseconds 1000',
                    '$out = $sp.ReadExisting()',
                    '$sp.Close()',
                    'Write-Output $out'
                ];
                $tmp = tempnam(sys_get_temp_dir(), 'smsps');
                file_put_contents($tmp . '.ps1', implode("\r\n", $psLines));
                $psFile = $tmp . '.ps1';
                exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($psFile) . ' 2>&1', $psOut, $psRc);
                @unlink($psFile);
                $combined = implode("\n", $psOut);
                $log('PowerShell fallback rc=' . $psRc . ' output: ' . $combined);
                return parseSuccessfulModemSmsResponse($combined);
            }
        } else {
            $openTarget = '/dev/' . $port;
            $handle = @fopen($openTarget, 'r+b');
            if ($handle === false) {
                $log('fopen failed for ' . $openTarget);
                return false;
            }
        }

        stream_set_blocking($handle, false);
        stream_set_timeout($handle, $timeout);

        $writeOk = fwrite($handle, $command);
        if ($writeOk === false || $writeOk === 0) {
            fclose($handle);
            $log('SMS serial failed: could not write to port ' . $port);
            return false;
        }

        $response = '';
        $startTime = microtime(true);
        do {
            $chunk = fread($handle, 1024);
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
            }
            if (stripos($response, 'OK') !== false || stripos($response, 'ERROR') !== false) {
                break;
            }
            usleep(100000);
        } while ((microtime(true) - $startTime) < $timeout);

        fclose($handle);

        $log('Response from ' . $port . ': ' . trim($response));
        if (stripos($response, 'OK') !== false) {
            $log('SMS sent successfully to ' . $phoneNumber . ' via Arduino');
            return true;
        }

        $log('SMS failed for ' . $phoneNumber . ': no OK response. Raw response: ' . trim($response));
        $bridgeResult = sendSmsViaBridge($phoneNumber, $message);
        if ($bridgeResult) {
            $log('SMS relay bridge succeeded for ' . $phoneNumber . ' after serial timeout');
            return true;
        }

        return false;
    } catch (Exception $e) {
        $log('SMS exception for ' . $phoneNumber . ': ' . $e->getMessage());
        $bridgeResult = sendSmsViaBridge($phoneNumber, $message);
        if ($bridgeResult) {
            $log('SMS relay bridge succeeded for ' . $phoneNumber . ' after exception');
            return true;
        }
        return false;
    }
}

?>
