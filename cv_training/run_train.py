import subprocess
import sys

cmd = [
    sys.executable,
    '-c',
    '''
import os
os.chdir(r"C:\\wamp64\\www\\school_gate\\cv_training")
from ultralytics import YOLO
model = YOLO("yolov8n.pt")
model.train(data="dataset.yaml", epochs=100, imgsz=320, batch=2, workers=0, name="uniform_detector", exist_ok=True, amp=False)
print("TRAINING COMPLETE!")
'''
]

process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, bufsize=1)

for line in process.stdout:
    try:
        print(line.decode('utf-8', errors='ignore'), end='')
    except:
        pass

process.wait()
print(f"Exit code: {process.returncode}")
