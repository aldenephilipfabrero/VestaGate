"""
Test inference with the existing best.pt model
"""
import os
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'

from ultralytics import YOLO
from pathlib import Path

# Load model
model_path = r'C:\wamp64\www\school_gate\cv_training\runs\detect\capstone_seg\weights\best.pt'
print(f'Loading model from {model_path}...')

model = YOLO(model_path)
print(f'Model loaded successfully')
print(f'Model class names: {model.names}')

# Test on a sample image from the current dataset
dataset_path = Path(r'C:\wamp64\www\school_gate\cv_training\datasets\capstone_dataset.v2\train\images')
test_images = list(dataset_path.glob('*.jpg'))[:3]

if test_images:
    print(f'\nTesting inference on {len(test_images)} sample images...')
    for img_path in test_images:
        results = model.predict(str(img_path), conf=0.25)
        print(f'  {img_path.name}: detected {len(results[0].boxes)} objects')
        for box in results[0].boxes:
            class_id = int(box.cls[0])
            conf = float(box.conf[0])
            print(f'    - class {class_id} ({model.names[class_id]}): conf {conf:.2f}')
else:
    print('No test images found')

print('\nInference test complete!')
