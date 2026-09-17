"""
Quick training test - 10 epochs only
"""
import multiprocessing
import os

def main():
    os.chdir(r'C:\wamp64\www\school_gate\cv_training')
    
    from ultralytics import YOLO
    
    print("="*100)
    print("QUICK TRAINING TEST - 10 EPOCHS")
    print("="*100)
    
    model = YOLO('yolov8n.pt')
    
    results = model.train(
        data=r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8\data.yaml',
        epochs=10,
        imgsz=640,
        batch=16,
        workers=0,
        name='uniform_detector',
        project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
        exist_ok=True,
        amp=False,
        cache=False,
        val=True,
        patience=5,
        verbose=False,
        device='cpu'
    )
    
    print("="*100)
    print("✅ QUICK TEST TRAINING COMPLETE!")
    print("="*100)

if __name__ == '__main__':
    multiprocessing.freeze_support()
    main()
