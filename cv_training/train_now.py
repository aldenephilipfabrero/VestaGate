"""
Windows-compatible YOLOv8 Training Script
"""
import multiprocessing
import os
import sys

def main():
    os.chdir(r'C:\wamp64\www\school_gate\cv_training')
    
    from ultralytics import YOLO
    
    print("="*100)
    print("YOLO TRAINING STARTING")
    print("="*100)
    
    model = YOLO('yolov8n-seg.pt')
    
    results = model.train(
        data='dataset.yaml',
        epochs=100,
        imgsz=640,
        batch=10,
        workers=0,
        name='uniform_detector',
        project='C:/wamp64/www/school_gate/cv_training/runs/detect',
        exist_ok=True,
        amp=False,
        cache=False,
        val=True,
        patience=50,
        verbose=True
    )
    
    print("="*100)
    print("TRAINING COMPLETE!")
    print("Model saved to: runs/detect/uniform_detector/weights/best.pt")
    print("="*100)

if __name__ == '__main__':
    multiprocessing.freeze_support()
    main()
