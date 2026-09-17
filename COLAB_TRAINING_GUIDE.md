# YOLOv8 Training in Google Colab - Quick Start

## 5-Minute Setup

### Step 1: Upload Dataset to Google Drive (2 min)
1. Open [Google Drive](https://drive.google.com)
2. Drag and drop this file into Drive root:
   ```
   capstone dataset.v2-dataset2.0.yolov8.zip
   ```
   (Location: `C:\Users\acer\Downloads\capstone dataset.v2-dataset2.0.yolov8.zip`)
3. Wait for upload to complete ✓

### Step 2: Open Notebook in Colab (1 min)
1. Go to [colab.research.google.com](https://colab.research.google.com)
2. Click **"File"** → **"Upload notebook"**
3. Select this file: `colab_train_current_dataset.ipynb`
4. Colab opens the notebook

### Step 3: Enable GPU (1 min)
1. Click **"Runtime"** → **"Change runtime type"**
2. Select **GPU** from the "Hardware accelerator" dropdown
3. Click **"Save"**
4. Colab restarts (wait 10 seconds)

### Step 4: Run Training (1 min setup + 15-30 min training)
1. **Cell 1** (Install): Click the ▶️ button or press `Ctrl+Enter`
   - You'll see: "Enter verification code" prompt
   - Open the link, authorize, copy code, paste it back
   - Wait for installation (~2 min)

2. **Cell 2** (Verify): Click ▶️ 
   - Output should show: `train: FOUND`, `valid: FOUND`

3. **Cell 3** (Convert labels): Click ▶️
   - Output: `Converted 18 bbox lines in 18 label files.`

4. **Cell 4** (Training): Click ▶️
   - **This takes 15-30 minutes** with GPU
   - Watch the progress bar
   - You'll see loss metrics in real-time

5. **Cell 5** (Download): Click ▶️ 
   - Automatically downloads `best.pt` to your computer
   - Check your Downloads folder

---

## What Each Cell Does

| Cell | Action | Status |
|------|--------|--------|
| 1 | Install Ultralytics, mount Google Drive | ✅ Auto-detects Colab |
| 2 | Verify dataset structure | ✅ Should pass |
| 3 | Convert bbox labels to polygons | ✅ Handles mixed formats |
| 4 | **Train YOLOv8 for 150 epochs** | ⏱️ 15-30 min |
| 5 | Download trained model | ✅ Auto-downloads best.pt |

---

## Expected Output

### Cell 1 (Install)
```
Using existing extracted dataset at /content/capstone_dataset.v2
```

### Cell 4 (Training Progress)
```
Epoch 1/150: 100%|████████| 316/316 [02:45<00:00, 1.91it/s]
  bbox_loss: 1.234 | seg_loss: 0.456 | val_loss: 1.567
Epoch 2/150: 100%|████████| 316/316 [02:30<00:00, 2.10it/s]
  bbox_loss: 0.987 | seg_loss: 0.345 | val_loss: 1.200
... (150 epochs total)
Training finished
Results saved to: /content/runs/detect
```

### Cell 5 (Download)
```
best.pt (48 MB) downloaded to your computer
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Dataset not found" | Check upload to Drive completed; file name must match exactly |
| "Out of memory" | Use GPU (Runtime → Change runtime type → GPU) |
| Training very slow (>2 min/epoch) | Switch to GPU; CPU-only is unsupported |
| Model download fails | Check if training actually completed (look for "Training finished") |

---

## After Training

The trained model is saved locally as:
```
Downloads/best.pt (48 MB)
```

**Next steps:**
1. Copy to your project: `cv_training/runs/detect/capstone_seg/weights/best.pt`
2. Use in detection server: update `detection_server.py` to load this model
3. Test on live camera feed

---

## Performance

- **GPU (Colab)**: 15–30 min for 150 epochs ✅
- **CPU (Local)**: 20+ hours ❌

### Expected Metrics After Training

- mAP50 (Segmentation): ~0.65–0.75
- Loss (Final): ~0.8–1.2
- F1-Score: ~0.70–0.80

---

**Ready?** Start with **Step 1** above. You're 5 minutes away from a trained model! 🚀
