# YOLOv8 Model Training Guide

## Current Status

- **Existing Model**: `runs/detect/uniform_detector/weights/best.pt` is fully functional for inference
- **Dataset**: Roboflow dataset cleaned and validated at `C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8`
- **Issue**: CPU training causes Ultralytics 8.4.45 + PyTorch 2.10.0 to crash silently on this Windows machine

## Solution: Train on GPU or Cloud

### Option 1: Google Colab (FREE - RECOMMENDED)

```python
# Run in Google Colab:
!git clone https://github.com/ultralytics/ultralytics.git
!cd ultralytics && pip install -e .

from ultralytics import YOLO

# Upload your dataset.yaml and training set
# Or use Roboflow API

model = YOLO('yolov8n.pt')
results = model.train(
    data='path/to/dataset.yaml',
    epochs=100,
    imgsz=640,
    batch=16,  # GPU can handle larger batch
    device=0,  # Use GPU
    patience=20,
    save=True,
    plots=True,
)

# Download best.pt when done
```

### Option 2: Local GPU Training (Windows)

#### Prerequisites:
```bash
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118
pip install ultralytics
```

#### Training Script:
```python
from ultralytics import YOLO

model = YOLO('yolov8n.pt')
results = model.train(
    data=r'C:/Users/acer/Downloads/final data set.v12-final-test-7.yolov8/data.yaml',
    epochs=100,
    imgsz=640,
    batch=16,
    device=0,  # GPU index
    workers=4,
    patience=20,
    name='uniform_detector_gpu',
    project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
)
```

### Option 3: Azure ML or AWS SageMaker

Use cloud training services for automatic GPU provisioning.

## Dataset Information

### If You Only Have Raw Photos

Raw photos cannot be trained directly for YOLOv8. They must be labeled first with bounding boxes and exported in YOLO format.

Use this workflow:

1. Copy raw photos into a labeling folder:
```bash
python cv_training/setup_dataset.py
```
Choose option `4` to copy source images for labeling.

2. Label the photos in a tool such as LabelImg, CVAT, or Label Studio.

3. Export the annotations in YOLO format so each image has a matching `.txt` file.

4. Put the labeled images and labels into the dataset structure:
```text
cv_training/dataset/
    images/train
    images/val
    labels/train
    labels/val
```

5. Train using the prepared dataset.

### Class Names

Use these exact class names in order:
- `id_lace`
- `polo`
- `shoes`
- `skirt`

### Location
```
C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8
```

### Structure
```
train/  - 526 images after data cleaning
valid/  - 93 images
test/   - 17 images
```

### Classes (4 total)
- 0: id_lace
- 1: polo
- 2: shoes  
- 3: skirt

### Validation Status
✅ All labels cleaned - no out-of-bounds coordinates
✅ All annotation files valid

## Local CPU Workaround (Not Recommended)

If you must train locally on CPU:

1. Downgrade PyTorch:
```bash
pip install torch==2.0.0 torchvision==0.15.1 torchaudio==2.0.0
```

2. Run training:
```bash
python cv_training/train_now.py
```

⚠️ This will be very slow (hours per epoch) but may avoid the crash.

## Current Model Usage

The existing `best.pt` works perfectly for inference:

```python
from ultralytics import YOLO

model = YOLO(r'C:\wamp64\www\school_gate\cv_training\runs\detect\uniform_detector\weights\best.pt')
results = model.predict('image.jpg', conf=0.25)
```

## Performance Notes

- **Model Size**: 5.9 MB (YOLOv8 nano)
- **CPU Inference**: ~100-110ms per image at 640x640
- **Classes**: 4 (matches Roboflow export)
- **Accuracy**: Will improve significantly after training on the new dataset

## Next Steps

1. Train model on GPU using Option 1-3 above
2. Replace `best.pt` with newly trained weights
3. Test detection on real camera footage
4. Optionally fine-tune confidence threshold in `detection_server.py`

## Files to Reference

- `cv_training/train_now.py` - Ready-to-run training script
- `cv_training/finetune_model.py` - Fine-tuning script
- `cv_training/detection_server.py` - Detection API server
- `cv_training/test_inference.py` - Inference test script
