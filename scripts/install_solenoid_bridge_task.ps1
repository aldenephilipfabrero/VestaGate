param()

# Creates a Scheduled Task to run the solenoid bridge runner at system startup.
# Requires administrative privileges to register for all users / highest privileges.

$taskName = "SolenoidBridgeRunner"
$runner = Join-Path $PSScriptRoot "solenoid_bridge_runner.ps1"

Write-Output "Creating scheduled task '$taskName' to run: $runner"

try {
    $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$runner`""
    $trigger = New-ScheduledTaskTrigger -AtStartup
    $principal = New-ScheduledTaskPrincipal -UserId "BUILTIN\Administrators" -RunLevel Highest
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Force
    Write-Output "Scheduled task '$taskName' registered."
} catch {
    Write-Error "Failed to register scheduled task: $_"
    Write-Output "If this fails, run this script from an elevated Admin PowerShell."
}
