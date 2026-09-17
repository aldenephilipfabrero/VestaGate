"""
Debug pure detection training with minimal flags
"""
from pathlib import Path
from ultralytics import YOLO

BASE_DIR = Path(__file__).resolve().parent
DATA_YAML = Path(r'C:/Users/acer/Downloads/final data set.v12-final-test-7.yolov8/data.yaml')
model = YOLO('yolov8n.pt')
print('DEBUG TRAIN PURE: model loaded')
results = model.train(
    data=str(DATA_YAML),
    epochs=1,
    imgsz=640,
    batch=16,
    workers=0,
    name='uniform_detector_debug2',
    project=str(BASE_DIR / 'runs' / 'detect'),
    exist_ok=True,
    amp=False,
    cache=False,
    val=True,
    patience=1,
    verbose=True,
    plots=False,
    save=True,
    save_txt=False,
    save_conf=False,
    device='cpu'
)
print('DEBUG TRAIN PURE: done', results)
