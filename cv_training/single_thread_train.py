"""
Minimal YOLO training that avoids multiprocessing issues.
Uses torch.utils.data with num_workers=0 explicitly.
"""
import os
import sys
import warnings
warnings.filterwarnings('ignore')

# Disable all parallelism BEFORE any imports
os.environ['KMP_DUPLICATE_LIB_OK'] = 'TRUE'
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'
os.environ['OPENBLAS_NUM_THREADS'] = '1'
os.environ['NUMEXPR_NUM_THREADS'] = '1'
os.environ['VECLIB_MAXIMUM_THREADS'] = '1'
os.environ['TORCH_NUM_THREADS'] = '1'
os.environ['CUBLAS_WORKSPACE_CONFIG'] = ':4096:8'

# Force single-threaded torch
import torch
torch.set_num_threads(1)
torch.set_num_interop_threads(1)

if __name__ == '__main__':
    import multiprocessing
    multiprocessing.freeze_support()
    
    # Change directory
    train_dir = r'C:\wamp64\www\school_gate\cv_training'
    os.chdir(train_dir)
    
    print("=" * 60)
    print("YOLO TRAINING - Single Threaded Mode")
    print("=" * 60)
    print(f"Working dir: {os.getcwd()}")
    print(f"Torch threads: {torch.get_num_threads()}")
    print()
    
    # Import YOLO
    from ultralytics import YOLO
    
    # Load model
    print("Loading model...")
    model = YOLO('yolov8n.pt')
    
    # Train with absolute minimum settings
    print("Starting training (this will take several minutes)...")
    print()
    
    try:
        results = model.train(
            data=os.path.join(train_dir, 'dataset.yaml'),
            epochs=10,
            imgsz=320,
            batch=1,  # Single image batches
            workers=0,  # No worker processes
            device='cpu',
            project=os.path.join(train_dir, 'runs', 'detect'),
            name='uniform_detector',
            exist_ok=True,
            amp=False,
            cache=False,
            deterministic=True,
            single_cls=False,
            verbose=True,
            seed=42,
            val=True,
            save=True,
            plots=False,  # Disable plots to avoid matplotlib issues
        )
        
        print()
        print("=" * 60)
        print("TRAINING COMPLETED SUCCESSFULLY!")
        print("=" * 60)
        
        best_pt = os.path.join(train_dir, 'runs', 'detect', 'uniform_detector', 'weights', 'best.pt')
        if os.path.exists(best_pt):
            print(f"Model saved: {best_pt}")
            
    except Exception as e:
        print(f"Training error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
