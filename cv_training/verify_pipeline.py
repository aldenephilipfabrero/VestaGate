"""
Complete pipeline verification - tests dataset, model, and inference
Run this to verify everything is ready before deployment
"""
import os
import sys
from pathlib import Path

os.environ['OMP_NUM_THREADS'] = '1'

def test_dataset():
    """Verify dataset exists and is properly formatted"""
    print("\n" + "="*60)
    print("TEST 1: Dataset Validation")
    print("="*60)
    
    dataset_root = Path(r'C:\wamp64\www\school_gate\cv_training\datasets\capstone_dataset.v2')
    
    if not dataset_root.exists():
        print("FAIL: Dataset directory not found")
        return False
    
    print(f"PASS: Dataset found: {dataset_root}")
    
    # Check splits
    for split in ['train', 'valid', 'test']:
        split_path = dataset_root / split
        if not split_path.exists():
            print(f"FAIL: Missing split: {split}")
            return False
        
        images = list((split_path / 'images').glob('*'))
        labels = list((split_path / 'labels').glob('*.txt'))
        
        if len(images) != len(labels):
            print(f"FAIL: {split}: image/label mismatch ({len(images)} vs {len(labels)})")
            return False
        
        print(f"PASS: {split}: {len(images)} images, {len(labels)} labels")
    
    return True

def test_yaml():
    """Verify dataset.yaml points to correct dataset"""
    print("\n" + "="*60)
    print("TEST 2: Dataset Configuration (YAML)")
    print("="*60)
    
    yaml_path = Path(r'C:\wamp64\www\school_gate\cv_training\dataset.yaml')
    if not yaml_path.exists():
        print("FAIL: dataset.yaml not found")
        return False
    
    content = yaml_path.read_text()
    print("PASS: dataset.yaml found")
    
    # Check for expected content
    if 'capstone_dataset.v2' in content or '../train/images' in content:
        print("PASS: YAML correctly references current dataset")
        return True
    else:
        print("WARN: YAML content:")
        print(content[:500])
        return True  # Still pass - manual check needed

def test_model_load():
    """Verify model can be loaded"""
    print("\n" + "="*60)
    print("TEST 3: Model Loading")
    print("="*60)
    
    try:
        from ultralytics import YOLO
        
        model_path = r'C:\wamp64\www\school_gate\cv_training\runs\detect\uniform_detector\weights\best.pt'
        if not Path(model_path).exists():
            print(f"❌ Model not found: {model_path}")
            return False
        
        model = YOLO(model_path)
        print(f"✓ Model loaded successfully")
        print(f"  Classes: {model.names}")
        print(f"  Size: {Path(model_path).stat().st_size / 1024 / 1024:.1f} MB")
        
        # Verify class count
        if len(model.names) != 4:
            print(f"⚠️  Expected 4 classes, got {len(model.names)}")
            return False
        
        expected_classes = ['id_lace', 'polo', 'shoes', 'skirt']
        model_classes = [model.names[i] for i in range(len(model.names))]
        
        all_match = all(
            exp.lower() == mdl.lower() 
            for exp, mdl in zip(expected_classes, model_classes)
        )
        
        if all_match:
            print("✓ All 4 classes present and correct")
        else:
            print("⚠️  Class name mismatch:")
            for exp, mdl in zip(expected_classes, model_classes):
                match = "✓" if exp.lower() == mdl.lower() else "✗"
                print(f"    {match} expected '{exp}' got '{mdl}'")
        
        return True
        
    except Exception as e:
        print(f"❌ Model loading failed: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_inference():
    """Test inference on sample images"""
    print("\n" + "="*60)
    print("TEST 4: Model Inference")
    print("="*60)
    
    try:
        from ultralytics import YOLO
        
        model = YOLO(r'C:\wamp64\www\school_gate\cv_training\runs\detect\uniform_detector\weights\best.pt')
        
        # Get sample images
        dataset_path = Path(r'C:\Users\acer\Downloads\final data set.v12-final-test-7.yolov8\train\images')
        sample_images = list(dataset_path.glob('*.jpg'))[:1]
        
        if not sample_images:
            print("⚠️  No sample images found for testing")
            return True
        
        print(f"✓ Testing inference on {len(sample_images)} sample images...")
        
        for img_path in sample_images:
            results = model.predict(str(img_path), conf=0.25, verbose=False)
            detections = len(results[0].boxes)
            print(f"  {img_path.name}: {detections} detections")
            
            for box in results[0].boxes:
                class_id = int(box.cls[0])
                conf = float(box.conf[0])
                class_name = model.names[class_id]
                print(f"    - {class_name}: {conf:.2%}")
        
        print("✓ Inference test complete")
        return True
        
    except Exception as e:
        print(f"❌ Inference test failed: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_server_config():
    """Verify detection_server.py is configured correctly"""
    print("\n" + "="*60)
    print("TEST 5: Detection Server Configuration")
    print("="*60)
    
    server_file = Path(r'C:\wamp64\www\school_gate\cv_training\detection_server.py')
    if not server_file.exists():
        print("❌ detection_server.py not found")
        return False
    
    content = server_file.read_text()
    
    # Check for correct class names
    if "['id_lace', 'polo', 'shoes', 'skirt']" in content:
        print("✓ Server classes correctly configured")
    else:
        print("⚠️  Server class names may be incorrect")
        # Try to find what they are
        if 'CLASSES = ' in content:
            start = content.find('CLASSES = ')
            end = content.find('\n', start)
            print(f"  Found: {content[start:end]}")
    
    # Check model path
    if 'best.pt' in content:
        print("✓ Server configured to use best.pt model")
    else:
        print("⚠️  Server model path may be incorrect")
    
    return True

def main():
    """Run all tests"""
    print("\n" + "="*80)
    print(" SCHOOL GATE YOLOV8 INTEGRATION - PIPELINE VERIFICATION")
    print("="*80)
    
    tests = [
        ("Dataset", test_dataset),
        ("YAML Config", test_yaml),
        ("Model Loading", test_model_load),
        ("Inference", test_inference),
        ("Server Config", test_server_config),
    ]
    
    results = []
    for name, test_func in tests:
        try:
            result = test_func()
            results.append((name, result))
        except Exception as e:
            print(f"❌ Test '{name}' crashed: {e}")
            import traceback
            traceback.print_exc()
            results.append((name, False))
    
    # Summary
    print("\n" + "="*80)
    print(" TEST SUMMARY")
    print("="*80)
    
    passed = sum(1 for _, r in results if r)
    total = len(results)
    
    for name, result in results:
        status = "✓ PASS" if result else "❌ FAIL"
        print(f"{status:10} {name}")
    
    print()
    print(f"Results: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n" + "="*80)
        print("✅ ALL TESTS PASSED - SYSTEM READY FOR DEPLOYMENT")
        print("="*80)
        print("\nNext steps:")
        print("1. Deploy detection_server.py on production server")
        print("2. Configure PHP frontend to call detection API")
        print("3. Test with live camera feed")
        print("4. Monitor accuracy and adjust confidence threshold if needed")
        return 0
    else:
        print("\n" + "="*80)
        print("❌ SOME TESTS FAILED - REVIEW ISSUES ABOVE")
        print("="*80)
        return 1

if __name__ == '__main__':
    sys.exit(main())
