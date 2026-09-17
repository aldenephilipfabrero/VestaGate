"""
Diagnose training crash - run in debugger mode
"""
import sys
import traceback
import warnings

warnings.filterwarnings('ignore')

def diagnose():
    print('step 1: import torch')
    try:
        import torch
        print(f'  torch version: {torch.__version__}')
        print(f'  cuda available: {torch.cuda.is_available()}')
        print(f'  cuda version: {torch.version.cuda}')
    except Exception as e:
        print(f'  ERROR: {e}')
        traceback.print_exc()
        return 1
    
    print('step 2: import ultralytics')
    try:
        from ultralytics import YOLO
        print(f'  ultralytics imported')
    except Exception as e:
        print(f'  ERROR: {e}')
        traceback.print_exc()
        return 1
    
    print('step 3: load yolov8n.pt')
    try:
        model = YOLO('yolov8n.pt')
        print(f'  model loaded')
        print(f'  model device: {model.device}')
    except Exception as e:
        print(f'  ERROR: {e}')
        traceback.print_exc()
        return 1
    
    print('step 4: validate dataset')
    try:
        from pathlib import Path
        yaml_path = r'C:/Users/acer/Downloads/final data set.v12-final-test-7.yolov8/data.yaml'
        yaml_exists = Path(yaml_path).exists()
        print(f'  yaml exists: {yaml_exists}')
        if yaml_exists:
            with open(yaml_path) as f:
                content = f.read()
                print(f'  yaml content preview: {content[:200]}')
    except Exception as e:
        print(f'  ERROR: {e}')
        traceback.print_exc()
        return 1
    
    print('step 5: start training with minimal config')
    try:
        print('  calling model.train()...')
        sys.stdout.flush()
        sys.stderr.flush()
        
        results = model.train(
            data=yaml_path,
            epochs=1,
            imgsz=640,
            batch=8,
            workers=0,
            name='diagnose_test',
            project=r'C:\wamp64\www\school_gate\cv_training\runs\detect',
            exist_ok=True,
            amp=False,
            cache=False,
            val=True,
            patience=1,
            verbose=False,
            plots=False,
            save=True,
            device='cpu',
            single_cls=False,
        )
        print(f'  training returned: {results}')
        return 0
        
    except Exception as e:
        print(f'  EXCEPTION during training: {e}')
        traceback.print_exc()
        sys.stdout.flush()
        sys.stderr.flush()
        return 1

if __name__ == '__main__':
    print('DIAGNOSIS START', flush=True)
    sys.stdout.flush()
    sys.stderr.flush()
    
    exit_code = diagnose()
    
    print(f'DIAGNOSIS END - exit code {exit_code}', flush=True)
    sys.stdout.flush()
    sys.stderr.flush()
    sys.exit(exit_code)
