import os
import sys
import traceback
from ultralytics import YOLO

os.chdir(r'C:\wamp64\www\school_gate\cv_training')
print('DEBUG: starting validate', flush=True)
model = YOLO('yolov8n.pt')
print('DEBUG: model loaded', flush=True)
try:
    results = model.val(
        data=r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8\data.yaml',
        imgsz=640,
        batch=16,
        device='cpu'
    )
    print('DEBUG: validate returned', results, flush=True)
except Exception:
    print('DEBUG: exception', flush=True)
    traceback.print_exc()
    sys.exit(1)
print('DEBUG: validate complete', flush=True)
