param()

# Creates a shortcut in the current user's Startup folder to launch the runner at login.
$shell = New-Object -ComObject WScript.Shell
$startup = $shell.SpecialFolders("Startup")
$shortcutPath = Join-Path $startup "SolenoidBridgeRunner.lnk"
$runner = Join-Path $PSScriptRoot "solenoid_bridge_runner.ps1"

if (-not (Test-Path $runner)) {
    Write-Error "Runner not found: $runner"
    exit 1
}

$target = "powershell.exe"
$args = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$runner`""

$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $target
$shortcut.Arguments = $args
$shortcut.WorkingDirectory = Split-Path $runner -Parent
$shortcut.IconLocation = "$env:SystemRoot\System32\SHELL32.dll, 1"
$shortcut.Save()

Write-Output "Created startup shortcut: $shortcutPath"
