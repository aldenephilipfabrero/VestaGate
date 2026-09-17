"""
Fine-tune existing best.pt model on new dataset
This is simpler than full training and should work around the CPU issue
"""
import os
import sys

os.environ['OMP_NUM_THREADS'] = '1'

print('Loading model for fine-tuning...', flush=True)
sys.stdout.flush()

try:
    from ultralytics import YOLO
    
    # Load existing trained model
    model = YOLO(r'C:\wamp64\www\school_gate\cv_training\runs\detect\uniform_detector\weights\best.pt')
    print('Existing model loaded', flush=True)
    
    # Fine-tune on new dataset
    print('Starting fine-tune...', flush=True)
    sys.stdout.flush()
    
    results = model.train(
        data=r'C:/Users/acer/Downloads/final data set.v12-final-test-7.yolov8/data.yaml',
        epochs=10,
        imgsz=640,
        batch=4,
        workers=0,
        name='finetune_uniform',
        project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
        exist_ok=True,
        amp=False,
        cache=False,
        val=True,
        patience=3,
        verbose=False,
        plots=False,
        save=True,
        device='cpu',
        resume=False,  # Start fresh
        degrees=0.0,
        translate=0.0,
        scale=0.0,
        flipud=0.0,
        fliplr=0.0,
        mosaic=0.0,
        mixup=0.0,
        lr0=0.0005,  # lower learning rate for fine-tuning
    )
    
    print('Fine-tuning complete!', flush=True)
    sys.stdout.flush()
    
except Exception as e:
    import traceback
    print(f'ERROR: {e}', flush=True)
    traceback.print_exc()
    sys.exit(1)
