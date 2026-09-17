"""
Direct training script with all Windows multiprocessing protections.
This script is designed to work around VSCode terminal issues on Windows.
"""
import os
import sys

# Set environment variables BEFORE any imports
os.environ['KMP_DUPLICATE_LIB_OK'] = 'TRUE'
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'
os.environ['OPENBLAS_NUM_THREADS'] = '1'
os.environ['VECLIB_MAXIMUM_THREADS'] = '1'
os.environ['NUMEXPR_NUM_THREADS'] = '1'

# Windows multiprocessing protection
if sys.platform == 'win32':
    import multiprocessing
    multiprocessing.freeze_support()
    # Force spawn method
    try:
        multiprocessing.set_start_method('spawn', force=True)
    except RuntimeError:
        pass

def main():
    # Change to training directory
    train_dir = r'C:\wamp64\www\school_gate\cv_training'
    os.chdir(train_dir)
    
    print("=" * 60)
    print("YOLO UNIFORM DETECTION TRAINING")
    print("=" * 60)
    print(f"Working directory: {os.getcwd()}")
    print(f"Python: {sys.executable}")
    print()
    
    # Now import ultralytics
    from ultralytics import YOLO
    
    # Load model
    print("Loading YOLOv8 nano model...")
    model = YOLO('yolov8n.pt')
    
    # Training with minimal settings for Windows CPU
    print("Starting training...")
    print("This may take 10-20 minutes on CPU...")
    print()
    
    results = model.train(
        data='dataset.yaml',
        epochs=100,
        imgsz=320,
        batch=2,
        workers=0,  # CRITICAL: Disable multiprocessing data loading
        device='cpu',
        project='runs/detect',
        name='uniform_detector',
        exist_ok=True,
        pretrained=True,
        verbose=True,
        amp=False,  # Disable automatic mixed precision
        deterministic=True,
        seed=0,
        cache=False,  # Don't cache images in RAM
    )
    
    print()
    print("=" * 60)
    print("TRAINING COMPLETE!")
    print("=" * 60)
    
    # Check for saved model
    best_model = os.path.join(train_dir, 'runs', 'detect', 'uniform_detector', 'weights', 'best.pt')
    last_model = os.path.join(train_dir, 'runs', 'detect', 'uniform_detector', 'weights', 'last.pt')
    
    if os.path.exists(best_model):
        print(f"Best model saved to: {best_model}")
    if os.path.exists(last_model):
        print(f"Last model saved to: {last_model}")
    
    return results

if __name__ == '__main__':
    main()
