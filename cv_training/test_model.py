"""
Test trained model on webcam or images
"""

from ultralytics import YOLO
import cv2
import os

MODEL_PATH = 'runs/detect/uniform_detector/weights/best.pt'
CLASSES = ['id lace', 'pologirl', 'shoes', 'skirt']
COLORS = [(0,255,0), (255,0,0), (0,255,255), (255,0,255)]

def test_webcam():
    if not os.path.exists(MODEL_PATH):
        print(f"Model not found: {MODEL_PATH}")
        return
    
    model = YOLO(MODEL_PATH)
    cap = cv2.VideoCapture(0)
    
    print("Press 'q' to quit")
    
    while True:
        ret, frame = cap.read()
        if not ret:
            break
        
        results = model(frame, conf=0.15, verbose=False)
        
        # Draw detections
        for r in results:
            for box in r.boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                cls_id = int(box.cls[0])
                conf = float(box.conf[0])
                
                color = COLORS[cls_id % len(COLORS)]
                label = f"{CLASSES[cls_id]}: {conf:.2f}"
                
                cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
                cv2.putText(frame, label, (x1, y1-10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        
        cv2.imshow('Uniform Detection', frame)
        
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break
    
    cap.release()
    cv2.destroyAllWindows()

def test_image(image_path):
    if not os.path.exists(MODEL_PATH):
        print(f"Model not found: {MODEL_PATH}")
        return
    
    model = YOLO(MODEL_PATH)
    results = model(image_path, conf=0.15)
    
    # Show results
    for r in results:
        im = r.plot()
        cv2.imshow('Detection', im)
        cv2.waitKey(0)
    
    cv2.destroyAllWindows()

if __name__ == "__main__":
    print("1. Test on webcam")
    print("2. Test on image")
    choice = input("Choice: ").strip()
    
    if choice == '1':
        test_webcam()
    elif choice == '2':
        path = input("Image path: ").strip()
        test_image(path)
