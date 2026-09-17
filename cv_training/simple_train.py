"""
Simple training script - debug version
"""
import sys
import traceback

print("Starting simple training...")
print(f"Python: {sys.version}")

try:
    from ultralytics import YOLO
    print("Ultralytics imported successfully")
    
    # Load model
    print("Loading YOLOv8n model...")
    model = YOLO('yolov8n.pt')
    print("Model loaded!")
    
    # Train with minimal settings
    print("Starting training...")
    results = model.train(
        data='C:/wamp64/www/school_gate/cv_training/dataset.yaml',
        epochs=10,
        imgsz=320,
        batch=2,
        workers=0,
        name='uniform_model',
        exist_ok=True,
        amp=False,  # Disable automatic mixed precision for CPU
        verbose=True
    )
    
    print("\n" + "="*50)
    print("TRAINING COMPLETE!")
    print("="*50)
    
except Exception as e:
    print(f"\nERROR: {e}")
    traceback.print_exc()

print("\nScript finished.")
