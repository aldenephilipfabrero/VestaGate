"""
Clean corrupt YOLO annotations from dataset
Removes images with out-of-bounds bounding box coordinates
"""
import os
from pathlib import Path

def clean_dataset(dataset_path):
    """Remove images and labels with corrupt coordinates"""
    dataset_path = Path(dataset_path)
    
    splits = ['train', 'valid', 'test']
    total_removed = 0
    
    for split in splits:
        labels_dir = dataset_path / split / 'labels'
        images_dir = dataset_path / split / 'images'
        
        if not labels_dir.exists():
            continue
        
        print(f"\n{'='*60}")
        print(f"Cleaning {split} set...")
        print(f"{'='*60}")
        
        removed = 0
        checked = 0
        
        for label_file in sorted(labels_dir.glob('*.txt')):
            checked += 1
            is_corrupt = False
            
            try:
                with open(label_file, 'r') as f:
                    lines = f.readlines()
                
                for line in lines:
                    parts = line.strip().split()
                    if len(parts) < 5:
                        is_corrupt = True
                        break
                    
                    # Check coordinates (format: class_id x_center y_center width height)
                    try:
                        cls = int(parts[0])
                        coords = [float(p) for p in parts[1:5]]
                    except ValueError:
                        is_corrupt = True
                        break
                    
                    if cls < 0 or cls > 3:
                        is_corrupt = True
                        break
                    if any(c < 0 or c > 1 for c in coords):
                        is_corrupt = True
                        break
                    if coords[2] <= 0 or coords[3] <= 0:
                        is_corrupt = True
                        break
                
                if is_corrupt:
                    # Find and remove corresponding image
                    base_name = label_file.stem
                    for img_ext in ['.jpg', '.jpeg', '.png']:
                        img_file = images_dir / f"{base_name}{img_ext}"
                        if img_file.exists():
                            img_file.unlink()
                            print(f"  Removed: {img_file.name}")
                    
                    # Remove label file
                    label_file.unlink()
                    removed += 1
                    total_removed += 1
            
            except Exception as e:
                print(f"  Error processing {label_file.name}: {e}")
                is_corrupt = True
                base_name = label_file.stem
                for img_ext in ['.jpg', '.jpeg', '.png']:
                    img_file = images_dir / f"{base_name}{img_ext}"
                    if img_file.exists():
                        img_file.unlink()
                label_file.unlink()
                removed += 1
                total_removed += 1
        
        print(f"\n{split.upper()}: Checked {checked}, Removed {removed}")
    
    print(f"\n{'='*60}")
    print(f"CLEANING COMPLETE - Removed {total_removed} corrupt entries")
    print(f"{'='*60}")

if __name__ == "__main__":
    dataset_path = r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8'
    clean_dataset(dataset_path)
