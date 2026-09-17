"""
Debug script to run detection_server logic on sample dataset images.
Loads models and runs person + uniform detection on a few images, printing outputs.
"""
import os
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'

from pathlib import Path
import base64
import cv2
import numpy as np

# Import detection server functions by path
import importlib.util
import sys

spec = importlib.util.spec_from_file_location('detection_server', os.path.join(os.path.dirname(__file__), 'detection_server.py'))
det_srv = importlib.util.module_from_spec(spec)
spec.loader.exec_module(det_srv)

# Load models
print('Loading models...')
det_srv.load_models()
print('Models loaded. CLASSES:', det_srv.CLASSES)

# Find some sample images
dataset_dir = Path(r'C:/wamp64/www/school_gate/cv_training/datasets/capstone_dataset.v2/train/images')
images = list(dataset_dir.glob('*.jpg'))[:6]
if not images:
    images = list(dataset_dir.glob('*.png'))[:6]

for img_path in images:
    print('\n---')
    print('Image:', img_path)
    img = cv2.imread(str(img_path))
    h, w = img.shape[:2]
    print('Size:', w, 'x', h)

    # Person detection
    person_detected, person_box = det_srv.detect_person(img)
    print('Person detected:', person_detected, 'box:', person_box)

    if person_detected:
        # Run uniform model
        results = det_srv.uniform_model(img, conf=det_srv.DEFAULT_CONF_THRESHOLD, verbose=False)
        boxes, detected = det_srv.parse_results_with_boxes(results, w, h, det_srv.DEFAULT_CONF_THRESHOLD, person_box)
        print('Detections dict:', detected)
        print('Boxes returned:', len(boxes))
        for b in boxes:
            print(' -', b)
    else:
        print('No person found; skipping uniform detection')

print('\nDebug run complete.')
