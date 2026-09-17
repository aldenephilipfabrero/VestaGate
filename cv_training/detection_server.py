"""
Detection Server - Flask API
Connects trained YOLO model to PHP system
Returns bounding boxes for drawing on camera feed
Requires person detection before checking compliance
"""

# Windows thread protection - must be set BEFORE imports
import os
os.environ['KMP_DUPLICATE_LIB_OK'] = 'TRUE'
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'

from flask import Flask, request, jsonify
from flask_cors import CORS
from ultralytics import YOLO
import cv2
import numpy as np
import base64
import random

# Set torch threads after import
import torch
torch.set_num_threads(1)

app = Flask(__name__)
CORS(app)

# Model paths
MODEL_PATH = r'C:\wamp64\www\school_gate\cv_training\runs\detect\capstone_seg\weights\best.pt'
LEGACY_MODEL_PATH = r'C:\wamp64\www\school_gate\cv_training\runs\detect\uniform_detector\weights\best.pt'
uniform_model = None

# Person model configuration (can be overridden with environment vars)
PERSON_MODEL_NAME = os.environ.get('PERSON_MODEL', 'yolov8n.pt')
# Confidence used for person detection (can override with PERSON_CONF env var)
PERSON_CONF_THRESHOLD = float(os.environ.get('PERSON_CONF', '0.15'))

# Load YOLOv8 pretrained model for person detection
person_model = None

# Classes from trained model will be loaded dynamically
CLASSES = []

# Confidence thresholds used for inference and label parsing
# Lower default to be more sensitive to small items like ID laces
DEFAULT_CONF_THRESHOLD = 0.08
# Minimum threshold specifically for ID/lace detections (very small objects) - much more sensitive
MIN_ID_CONF = 0.02

# Colors for each class - distinct and bright colors
CLASS_COLORS = {
    'id_lace': '#00FF00',   # Bright Green
    'id lace': '#00FF00',
    'polo': '#00BFFF',      # Deep Sky Blue
    'pologirl': '#00BFFF',
    'poloboy': '#00BFFF',
    'skirt': '#FF1493',     # Deep Pink
    'blackshoes': '#FFD700',
    'shoes': '#FFD700',     # Gold/Yellow
    'blackpants': '#DA70D6',
    'person': '#FF4500'     # Orange Red for person outline
}

def find_uniform_model():
    """Try the configured path first, then search for any available best/last weights."""
    if os.path.exists(MODEL_PATH):
        return MODEL_PATH

    if os.path.exists(LEGACY_MODEL_PATH):
        return LEGACY_MODEL_PATH

    weights_dir = os.path.join(os.path.dirname(MODEL_PATH), '..')
    if os.path.isdir(weights_dir):
        for candidate in ['best.pt', 'last.pt']:
            candidate_path = os.path.join(weights_dir, 'weights', candidate)
            if os.path.exists(candidate_path):
                return candidate_path

    # fallback to any discovered detector weights in the training directory
    base_dir = os.path.dirname(os.path.dirname(os.path.dirname(MODEL_PATH)))
    for root, _, files in os.walk(base_dir):
        for fname in ['best.pt', 'last.pt']:
            if fname in files and 'capstone_seg' in root:
                return os.path.join(root, fname)
    for root, _, files in os.walk(base_dir):
        for fname in ['best.pt', 'last.pt']:
            if fname in files and 'uniform_detector' in root:
                return os.path.join(root, fname)
    return None


def load_models():
    global uniform_model, person_model
    
    # Load uniform detection model
    fallback_path = find_uniform_model()
    if fallback_path:
        uniform_model = YOLO(fallback_path)
        print(f"✓ Uniform model loaded: {fallback_path}")
        if hasattr(uniform_model.model, 'names'):
            # `uniform_model.model.names` is typically a dict {id: name}
            names = uniform_model.model.names
            print(f"  Uniform model classes: {names}")
            global CLASSES
            # Preserve index order when converting to list of names
            try:
                CLASSES = [names[i] for i in range(len(names))]
            except Exception:
                # Fallback to values ordering
                CLASSES = list(names.values())
            print(f"  Loaded CLASSES: {CLASSES}")
    else:
        print(f"✗ Uniform model not found: {MODEL_PATH}")
    
    # Load pretrained YOLOv8 for person detection
    try:
        person_model = YOLO(PERSON_MODEL_NAME)  # Pretrained model detects people
        print(f"✓ Person detection model loaded ({PERSON_MODEL_NAME})")
        print(f"  Person detection confidence threshold: {PERSON_CONF_THRESHOLD}")
    except Exception as e:
        print(f"⚠ Person detection model failed: {e}")
        person_model = None

def detect_person(image):
    """Detect if there's a person in the image"""
    if person_model is None:
        print("Person model not loaded!")
        return False, None
    # Slightly lower person threshold to improve recall in gate footage
    results = person_model(image, conf=PERSON_CONF_THRESHOLD, verbose=False)
    
    # Debug: print all detections
    for r in results:
        for box in r.boxes:
            cls_id = int(box.cls[0])
            conf = float(box.conf[0])
            name = person_model.model.names.get(cls_id, str(cls_id)) if hasattr(person_model, 'model') else str(cls_id)
            print(f"  Person-model detected class {cls_id} ({name}) with conf {conf:.2f}")
    
    for r in results:
        for box in r.boxes:
            cls_id = int(box.cls[0])
            # Class 0 is 'person' in COCO dataset
            if cls_id == 0:
                conf = float(box.conf[0])
                x1, y1, x2, y2 = box.xyxy[0].tolist()
                print(f"  PERSON FOUND: conf={conf:.2f}")
                return True, {
                    'confidence': round(conf * 100),
                    'x1': x1, 'y1': y1, 'x2': x2, 'y2': y2
                }
    
    print("  No person found in frame")
    return False, None

@app.route('/detect', methods=['POST'])
def detect():
    """Detect uniform compliance from image - requires person first"""
    try:
        data = request.get_json()
        
        print(f"=== Detection request received ===")
        
        if not data or 'image' not in data:
            print("ERROR: No image in request")
            return jsonify({'error': 'No image'}), 400
        
        # Decode base64 image
        img_data = data['image']
        if ',' in img_data:
            img_data = img_data.split(',')[1]
        
        print(f"Image data length: {len(img_data)} bytes")
        
        img_bytes = base64.b64decode(img_data)
        nparr = np.frombuffer(img_bytes, np.uint8)
        image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        
        if image is None:
            print("ERROR: Failed to decode image")
            return jsonify({'error': 'Invalid image'}), 400
        
        img_height, img_width = image.shape[:2]
        print(f"Image decoded: {img_width}x{img_height}")
        
        # STEP 1: Check if person is present
        person_detected, person_box = detect_person(image)
        print(f"Person detection: detected={person_detected}, box={person_box}")
        
        if not person_detected:
            # No person in frame - cannot check compliance
            print("No person detected - returning non-compliant")
            return jsonify({
                'uniform_compliant': False,
                'id_visible': False,
                'shoes_compliant': False,
                'overall_compliant': False,
                'person_detected': False,
                'detections': {},
                'boxes': [],
                'image_size': {'width': img_width, 'height': img_height},
                'mode': 'no_person',
                'message': 'No student detected in frame'
            })
        
        # STEP 2: Person found - now check uniform compliance
        boxes = []
        detected = {cls: False for cls in CLASSES}
        
        # Add person bounding box
        if person_box:
            boxes.append({
                'class': 'person',
                'confidence': person_box['confidence'],
                'color': CLASS_COLORS['person'],
                'x': round(person_box['x1'] / img_width * 100, 2),
                'y': round(person_box['y1'] / img_height * 100, 2),
                'width': round((person_box['x2'] - person_box['x1']) / img_width * 100, 2),
                'height': round((person_box['y2'] - person_box['y1']) / img_height * 100, 2)
            })
        
        if uniform_model:
            results = uniform_model(image, conf=DEFAULT_CONF_THRESHOLD, verbose=False)  # Lower threshold
            uniform_boxes, detected = parse_results_with_boxes(results, img_width, img_height, DEFAULT_CONF_THRESHOLD, person_box)
            boxes.extend(uniform_boxes)
            mode = 'trained'
            print(f"Detection: boxes={len(uniform_boxes)}, detected={detected}")  # Debug
        else:
            # No uniform model - use simulation for detected items only
            uniform_boxes, detected = generate_simulation_boxes(img_width, img_height, person_box)
            boxes.extend(uniform_boxes)
            mode = 'simulation'
        
        # Determine compliance - must have detected items
        uniform_ok = any(detected.get(name, False) for name in ['polo', 'pologirl', 'poloboy', 'skirt'])
        id_ok = any(detected.get(name, False) for name in ['id_lace', 'id lace'])
        shoes_ok = any(detected.get(name, False) for name in ['shoes', 'blackshoes'])
        
        return jsonify({
            'uniform_compliant': uniform_ok,
            'id_visible': id_ok,
            'shoes_compliant': shoes_ok,
            'overall_compliant': uniform_ok and id_ok and shoes_ok,
            'person_detected': True,
            'detections': detected,
            'boxes': boxes,
            'image_size': {'width': img_width, 'height': img_height},
            'mode': mode
        })
        
    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

def parse_results_with_boxes(results, img_width, img_height, conf_threshold=DEFAULT_CONF_THRESHOLD, person_box=None):
    """Parse YOLO results and return bounding boxes"""
    detected = {cls: False for cls in CLASSES}
    boxes = []
    
    for r in results:
        for box in r.boxes:
            cls_id = int(box.cls[0])
            conf = float(box.conf[0])
            
            if cls_id < len(CLASSES):
                class_name = CLASSES[cls_id]
                # Normalize class name variants
                canonical = class_name.replace(' ', '_')
                # Lower threshold for ID detection (harder to detect)
                threshold = MIN_ID_CONF if canonical in ['id_lace', 'idlace'] or class_name in ['id lace', 'id_lace'] else conf_threshold

                # Always print detection attempts for debugging
                print(f"  RAW_DETECTION: id={cls_id} name={class_name} conf={conf:.4f} threshold={threshold}")

                if conf >= threshold:
                    # Get box coordinates (x1, y1, x2, y2)
                    x1, y1, x2, y2 = box.xyxy[0].tolist()

                    # Basic sanity filters to avoid full-image / spurious detections
                    box_w = x2 - x1
                    box_h = y2 - y1
                    box_area = box_w * box_h

                    person_ok = True
                    if person_box is not None:
                        px1 = person_box['x1']
                        py1 = person_box['y1']
                        pw = person_box['x2'] - person_box['x1']
                        ph = person_box['y2'] - person_box['y1']

                        # Require detection center to lie within the person bbox
                        cx = (x1 + x2) / 2.0
                        cy = (y1 + y2) / 2.0
                        if not (px1 <= cx <= px1 + pw and py1 <= cy <= py1 + ph):
                            person_ok = False
                            print(f"  FILTERED_OUT: {class_name} center not inside person bbox (cx,cy)=({cx:.1f},{cy:.1f})")

                        # Reject boxes that are nearly as large as the person (likely spurious)
                        if box_w > 0.9 * pw or box_h > 0.9 * ph:
                            person_ok = False
                            print(f"  FILTERED_OUT: {class_name} box too large relative to person (box_w={box_w:.1f}, box_h={box_h:.1f}, pw={pw:.1f}, ph={ph:.1f})")

                    # Reject boxes that cover the whole image
                    if box_w > 0.95 * img_width and box_h > 0.95 * img_height:
                        person_ok = False
                        print(f"  FILTERED_OUT: {class_name} box covers entire image")

                    # Minimum box size (avoid tiny false positives)
                    # But allow smaller boxes for ID/face detection
                    min_width_threshold = 0.008 if canonical in ['id_lace', 'idlace'] or class_name in ['id lace', 'id_lace'] else 0.02
                    min_height_threshold = 0.008 if canonical in ['id_lace', 'idlace'] or class_name in ['id lace', 'id_lace'] else 0.02
                    
                    if box_w < min_width_threshold * img_width or box_h < min_height_threshold * img_height:
                        person_ok = False
                        print(f"  FILTERED_OUT: {class_name} box too small (w={box_w:.1f}, h={box_h:.1f})")

                    if not person_ok:
                        continue

                    detected[class_name] = True
                    print(f"  DETECTED: {class_name} with conf {conf:.2f} (threshold {threshold})")  # Debug

                    # Convert to percentages for responsive drawing
                    boxes.append({
                        'class': class_name,
                        'confidence': round(conf * 100),
                        'color': CLASS_COLORS.get(class_name, '#ffffff'),
                        'x': round(x1 / img_width * 100, 2),
                        'y': round(y1 / img_height * 100, 2),
                        'width': round((x2 - x1) / img_width * 100, 2),
                        'height': round((y2 - y1) / img_height * 100, 2)
                    })
                else:
                    print(f"  BELOW THRESHOLD: {class_name} with conf {conf:.2f} (threshold {threshold})")  # Debug
    
    return boxes, detected

def generate_simulation_boxes(img_width, img_height, person_box):
    """Generate simulated detection boxes based on person location"""
    detected = {cls: False for cls in CLASSES}
    boxes = []
    
    if not person_box:
        return boxes, detected
    
    # Calculate relative positions based on person box
    px1, py1 = person_box['x1'], person_box['y1']
    pw = person_box['x2'] - person_box['x1']
    ph = person_box['y2'] - person_box['y1']
    
    # Simulate detections with realistic positions relative to person
    simulations = [
        # Polo - upper body (30-60% of person height)
        {'class': 'polo', 'rx': 0.1, 'ry': 0.15, 'rw': 0.8, 'rh': 0.35, 'prob': 0.7},
        # ID Lace - chest area (25-45% of person height)
        {'class': 'id_lace', 'rx': 0.3, 'ry': 0.2, 'rw': 0.25, 'rh': 0.2, 'prob': 0.6},
        # Shoes - bottom (85-100% of person height)
        {'class': 'shoes', 'rx': 0.2, 'ry': 0.85, 'rw': 0.6, 'rh': 0.15, 'prob': 0.5},
    ]
    
    for sim in simulations:
        if random.random() < sim['prob']:
            detected[sim['class']] = True
            conf = random.randint(55, 85)
            
            x = px1 + pw * sim['rx']
            y = py1 + ph * sim['ry']
            w = pw * sim['rw']
            h = ph * sim['rh']
            
            boxes.append({
                'class': sim['class'],
                'confidence': conf,
                'color': CLASS_COLORS.get(sim['class'], '#ffffff'),
                'x': round(x / img_width * 100, 2),
                'y': round(y / img_height * 100, 2),
                'width': round(w / img_width * 100, 2),
                'height': round(h / img_height * 100, 2)
            })
    
    return boxes, detected

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'running',
        'uniform_model_loaded': uniform_model is not None,
        'person_model_loaded': person_model is not None,
        'person_model_name': PERSON_MODEL_NAME,
        'person_conf_threshold': PERSON_CONF_THRESHOLD,
        'uniform_model_path': MODEL_PATH,
        'default_conf_threshold': DEFAULT_CONF_THRESHOLD,
        'min_id_conf': MIN_ID_CONF
    })

if __name__ == '__main__':
    print("="*50)
    print("UNIFORM DETECTION SERVER")
    print("="*50)
    load_models()
    print("\nStarting on http://localhost:5000")
    app.run(host='0.0.0.0', port=5000, debug=False)
    app.run(host='0.0.0.0', port=5000, debug=False)
