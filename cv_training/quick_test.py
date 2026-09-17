from ultralytics import YOLO

model = YOLO('runs/detect/uniform_detector/weights/best.pt')
results = model(r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8\test\images\12_png.rf.27517f8cf2fa0ddd131d867674197a71.jpg', conf=0.15)

print(f"Detections: {len(results[0].boxes)}")
if len(results[0].boxes) > 0:
    print(f"Classes detected: {results[0].boxes.cls.tolist()}")
    print(f"Confidences: {results[0].boxes.conf.tolist()}")
else:
    print("No detections - model may not be trained yet")
