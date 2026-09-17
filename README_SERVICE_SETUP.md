Service setup for Solenoid Bridge

Actions included in `scripts`:

- `solenoid_bridge_runner.ps1` — runs `solenoid_bridge.py` in a restart loop and logs output to `logs/solenoid_bridge.log`.
- `install_solenoid_bridge_task.ps1` — attempts to register a Scheduled Task `SolenoidBridgeRunner` to run the runner at system startup (requires Administrator).

Usage:

1. To start the runner now (current session):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\solenoid_bridge_runner.ps1
```

2. To install the scheduled task (run as Administrator):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install_solenoid_bridge_task.ps1
```

Notes:
- The scheduled task must be created from an elevated PowerShell to register with highest privileges.
- The runner writes to `logs/solenoid_bridge.log` in the project root.
- After installing, verify service by checking the log file and hitting `http://localhost:5010/health`.
