import os
import sys
import traceback
from ultralytics import YOLO

os.chdir(r'C:\wamp64\www\school_gate\cv_training')
print('DEBUG: starting', flush=True)
model = YOLO('yolov8n.pt')
print('DEBUG: model loaded', flush=True)
try:
    results = model.train(
        data=r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8\data.yaml',
        epochs=1,
        imgsz=640,
        batch=16,
        workers=0,
        name='uniform_detector_debug',
        project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
        exist_ok=True,
        amp=False,
        cache=False,
        val=True,
        patience=1,
        verbose=True,
        device='cpu'
    )
    print('DEBUG: training returned', results, flush=True)
except Exception as e:
    print('DEBUG: exception', flush=True)
    traceback.print_exc()
    sys.exit(1)
print('DEBUG: complete', flush=True)
