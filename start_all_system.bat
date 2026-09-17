@echo off
setlocal
cd /d C:\wamp64\www\school_gate

taskkill /F /IM "Arduino IDE.exe" /T >NUL 2>NUL
taskkill /F /IM arduino-cli.exe /T >NUL 2>NUL
taskkill /F /IM serial-discovery.exe /T >NUL 2>NUL
taskkill /F /IM mdns-discovery.exe /T >NUL 2>NUL

taskkill /F /IM python.exe /T >NUL 2>NUL

echo Starting system services...
start "PHP Server" powershell -NoExit -Command "php -S localhost:8000 -t ."
start "Detection Server" powershell -NoExit -Command "C:/Users/acer/AppData/Local/Programs/Python/Python312/python.exe cv_training/detection_server.py"
start "Camera + Print Server" powershell -NoExit -Command "C:/Users/acer/AppData/Local/Programs/Python/Python312/python.exe cv_training/camera_server.py"

set SMS_SERIAL_PORT=COM9
start "SMS Gateway Bridge" powershell -NoExit -Command "$env:SMS_SERIAL_PORT='COM9'; C:/Users/acer/AppData/Local/Programs/Python/Python312/python.exe sms_bridge.py"

powershell -NoProfile -Command "for ($i = 0; $i -lt 30; $i++) { try { $r = Invoke-WebRequest -Uri 'http://localhost:5001/status' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -eq 200) { Write-Host 'Tapo camera server is online'; break } } catch {} Start-Sleep -Seconds 1 }"

echo All services launched:
echo - Web:        http://localhost:8000
echo - Detection:  http://localhost:5000/health
echo - Camera:     http://localhost:5001/status
echo - Print:      http://localhost:5001/printer_status
echo - Solenoid:   handled via solenoid_bridge.py over COM4
echo - SMS:        handled via http://localhost:5020/sms over COM3
endlocal
