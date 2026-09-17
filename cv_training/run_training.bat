@echo off
echo =============================================
echo YOLO Training - Uniform Detection
echo =============================================
echo.

cd /d C:\wamp64\www\school_gate\cv_training

echo Starting training...
echo This will take several minutes on CPU.
echo.

python simple_train.py

echo.
echo =============================================
echo Training finished! Check output above.
echo =============================================
pause
