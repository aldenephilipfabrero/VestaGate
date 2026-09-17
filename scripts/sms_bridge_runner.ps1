param()

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $scriptDir

$logsDir = Join-Path $scriptDir "..\logs"
if (-not (Test-Path $logsDir)) { New-Item -ItemType Directory -Path $logsDir | Out-Null }
$logFile = Join-Path $logsDir "sms_bridge.log"

function Log($msg) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "$ts $msg" | Out-File -FilePath $logFile -Append -Encoding utf8
}

Log "Runner starting; scriptDir=$scriptDir"

while ($true) {
    Log "Launching sms_bridge.py"
    try {
        & python "${scriptDir}\..\sms_bridge.py" 2>&1 | ForEach-Object { Log $_ }
    } catch {
        Log "Exception launching python: $_"
    }
    Log "sms_bridge.py exited - will restart in 5 seconds"
    Start-Sleep -Seconds 5
}
