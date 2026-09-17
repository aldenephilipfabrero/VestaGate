"""
YOLOv8 Training Script
Train model to detect: id_lace, polo, shoes, skirt
"""

from pathlib import Path

from ultralytics import YOLO


BASE_DIR = Path(__file__).resolve().parent
DATASET_YAML = BASE_DIR / 'dataset.yaml'
MODEL_NAME = 'uniform_detector'
MODEL_OUTPUT = BASE_DIR / 'runs' / 'detect' / MODEL_NAME / 'weights' / 'best.pt'

def train():
    # Check dataset configuration exists
    if not DATASET_YAML.exists():
        print(f"ERROR: Dataset config not found: {DATASET_YAML}")
        return

    print(f"Using dataset config: {DATASET_YAML}")
    
    # Load YOLOv8 nano model (fastest)
    model = YOLO('yolov8n.pt')
    
    # Train
    results = model.train(
        data=str(DATASET_YAML),
        epochs=1000,
        imgsz=640,
        batch=10,
        name=MODEL_NAME,
        exist_ok=True,
        patience=20,
        save=True,
        plots=True,
        workers=0,
        verbose=True
    )
    
    print("\n" + "="*50)
    print("TRAINING COMPLETE!")
    print("="*50)
    print(f"Model saved to: {MODEL_OUTPUT}")
    print("\nTo test: python test_model.py")
    print("To start server: python detection_server.py")

if __name__ == "__main__":
    train()
