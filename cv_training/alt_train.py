"""
Alternative training approach - disable multiprocessing and use minimal settings
"""
import os
import sys

# Disable multiprocessing issues
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['OPENBLAS_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'
os.environ['VECLIB_MAXIMUM_THREADS'] = '1'
os.environ['NUMEXPR_NUM_THREADS'] = '1'

import traceback
from pathlib import Path

print('Loading ultralytics...', flush=True)
sys.stdout.flush()

try:
    from ultralytics import YOLO
    import torch
    
    print(f'PyTorch: {torch.__version__}', flush=True)
    sys.stdout.flush()
    
    # Load model
    print('Loading model...', flush=True)
    sys.stdout.flush()
    model = YOLO('yolov8n.pt')
    
    # Start training
    print('Starting training...', flush=True)
    sys.stdout.flush()
    
    results = model.train(
        data=r'C:/Users/acer/Downloads/final data set.v12-final-test-7.yolov8/data.yaml',
        epochs=1,
        imgsz=640,
        batch=4,  # very small batch
        workers=0,
        name='alt_train_test',
        project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
        exist_ok=True,
        amp=False,
        cache=False,
        val=True,
        patience=1,
        verbose=False,
        plots=False,
        save=True,
        device='cpu',
        hsv_h=0.0,
        hsv_s=0.0,
        hsv_v=0.0,
        degrees=0.0,
        translate=0.0,
        scale=0.0,
        flipud=0.0,
        fliplr=0.0,
        mosaic=0.0,  # disable mosaic augmentation
        mixup=0.0,
        copy_paste=0.0,
    )
    
    print('Training complete!', flush=True)
    sys.stdout.flush()
    sys.exit(0)

except Exception as e:
    print(f'ERROR: {e}', flush=True)
    print('TRACEBACK:', flush=True)
    traceback.print_exc()
    sys.stdout.flush()
    sys.stderr.flush()
    sys.exit(1)
