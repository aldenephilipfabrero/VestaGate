"""
Use Ultralytics via command line interface instead of Python API.
This sometimes works better on Windows.
"""
import os
import subprocess
import sys

# Set environment variables
env = os.environ.copy()
env['KMP_DUPLICATE_LIB_OK'] = 'TRUE'
env['OMP_NUM_THREADS'] = '1'

# Change to training directory
os.chdir(r'C:\wamp64\www\school_gate\cv_training')

print("=" * 60)
print("YOLO Training via CLI")
print("=" * 60)

# Build the command
cmd = [
    sys.executable, '-m', 'ultralytics',
    'detect', 'train',
    'data=dataset.yaml',
    'model=yolov8n.pt',
    'epochs=10',
    'imgsz=320',
    'batch=2',
    'workers=0',
    'device=cpu',
    'project=runs/detect',
    'name=uniform_detector',
    'exist_ok=True',
    'amp=False',
    'verbose=True',
]

print(f"Command: {' '.join(cmd)}")
print()

# Run the command
process = subprocess.run(cmd, env=env)
print()
print(f"Exit code: {process.returncode}")
