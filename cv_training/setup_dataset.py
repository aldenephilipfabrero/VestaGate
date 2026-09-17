"""
Setup Dataset from Label Studio Export or Manual Annotation
"""

import os
import shutil
import random
import zipfile
import glob

def create_folders():
    """Create YOLO dataset folder structure"""
    folders = [
        'dataset/images/train',
        'dataset/images/val', 
        'dataset/labels/train',
        'dataset/labels/val',
        'images_to_label'
    ]
    for folder in folders:
        os.makedirs(folder, exist_ok=True)
    print("✓ Created dataset folders")

def import_from_label_studio(zip_path):
    """Import YOLO export from Label Studio"""
    print(f"Extracting {zip_path}...")
    
    # Extract to temp folder
    with zipfile.ZipFile(zip_path, 'r') as zip_ref:
        zip_ref.extractall('temp_export')
    
    # Find images and labels
    images = glob.glob('temp_export/**/*.jpg', recursive=True) + \
             glob.glob('temp_export/**/*.jpeg', recursive=True) + \
             glob.glob('temp_export/**/*.png', recursive=True)
    
    labels = glob.glob('temp_export/**/*.txt', recursive=True)
    labels = [l for l in labels if 'classes.txt' not in l]
    
    print(f"Found {len(images)} images, {len(labels)} labels")
    
    # Copy to dataset
    for img in images:
        name = os.path.basename(img)
        shutil.copy(img, f'dataset/images/train/{name}')
    
    for lbl in labels:
        name = os.path.basename(lbl)
        shutil.copy(lbl, f'dataset/labels/train/{name}')
    
    # Cleanup
    shutil.rmtree('temp_export', ignore_errors=True)
    
    print(f"✓ Imported {len(images)} images to dataset")

def import_from_folder(image_folder, label_folder=None):
    """Import images and labels from folders"""
    if label_folder is None:
        label_folder = image_folder
    
    images = glob.glob(f'{image_folder}/*.jpg') + \
             glob.glob(f'{image_folder}/*.jpeg') + \
             glob.glob(f'{image_folder}/*.png')
    
    count = 0
    for img_path in images:
        img_name = os.path.basename(img_path)
        base_name = os.path.splitext(img_name)[0]
        label_path = os.path.join(label_folder, f'{base_name}.txt')
        
        if os.path.exists(label_path):
            shutil.copy(img_path, f'dataset/images/train/{img_name}')
            shutil.copy(label_path, f'dataset/labels/train/{base_name}.txt')
            count += 1
    
    print(f"✓ Imported {count} labeled images")

def split_train_val(val_ratio=0.2):
    """Split training data into train/val sets"""
    train_images = glob.glob('dataset/images/train/*')
    
    if not train_images:
        print("No images in training folder!")
        return
    
    random.shuffle(train_images)
    val_count = int(len(train_images) * val_ratio)
    val_images = train_images[:val_count]
    
    for img_path in val_images:
        img_name = os.path.basename(img_path)
        base_name = os.path.splitext(img_name)[0]
        
        # Move image
        shutil.move(img_path, f'dataset/images/val/{img_name}')
        
        # Move label if exists
        label_path = f'dataset/labels/train/{base_name}.txt'
        if os.path.exists(label_path):
            shutil.move(label_path, f'dataset/labels/val/{base_name}.txt')
    
    train_count = len(glob.glob('dataset/images/train/*'))
    val_count = len(glob.glob('dataset/images/val/*'))
    
    print(f"✓ Split complete: {train_count} train, {val_count} val")

def copy_source_images(source_path):
    """Copy images from source folder for labeling"""
    if not os.path.exists(source_path):
        print(f"Source path not found: {source_path}")
        return
    
    count = 0
    for root, dirs, files in os.walk(source_path):
        for f in files:
            if f.lower().endswith(('.jpg', '.jpeg', '.png')):
                src = os.path.join(root, f)
                dst = f'images_to_label/img_{count:04d}.jpg'
                shutil.copy(src, dst)
                count += 1
    
    print(f"✓ Copied {count} images to 'images_to_label' folder")
    print("  Now label them with LabelImg or Label Studio")

def main():
    print("="*50)
    print("DATASET SETUP")
    print("="*50)
    print("\nOptions:")
    print("1. Create folder structure only")
    print("2. Import from Label Studio ZIP export")
    print("3. Import from folder (images + labels)")
    print("4. Copy source images for labeling")
    print("5. Split into train/val sets")
    
    choice = input("\nChoice (1-5): ").strip()
    
    if choice == '1':
        create_folders()
    
    elif choice == '2':
        create_folders()
        zip_path = input("Path to Label Studio ZIP: ").strip()
        if zip_path:
            import_from_label_studio(zip_path)
            split_train_val()
    
    elif choice == '3':
        create_folders()
        img_folder = input("Image folder path: ").strip()
        lbl_folder = input("Label folder path (Enter for same): ").strip() or None
        import_from_folder(img_folder, lbl_folder)
        split_train_val()
    
    elif choice == '4':
        create_folders()
        source = input("Source images path: ").strip()
        copy_source_images(source)
    
    elif choice == '5':
        split_train_val()
    
    print("\nDone!")

if __name__ == "__main__":
    main()
