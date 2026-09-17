@echo off
title Tapo Camera Server
echo ============================================================
echo           TAPO C560WS CAMERA SERVER
echo ============================================================
echo.
echo Before running, make sure you have:
echo   1. Updated camera_server.py with your Tapo camera details
echo   2. Enabled RTSP in Tapo app (Camera Settings ^> Advanced)
echo.
echo Starting camera server on http://localhost:5001
echo Press Ctrl+C to stop
echo.

cd /d "%~dp0"
python camera_server.py

pause
