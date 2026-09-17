import ultralytics
from pathlib import Path
root = Path(ultralytics.__file__).resolve().parent
print('root', root)
count = 0
for p in root.rglob('*.py'):
    text = p.read_text(errors='ignore')
    if 'sys.exit(' in text:
        print('file', p)
        for i, line in enumerate(text.splitlines(), 1):
            if 'sys.exit(' in line:
                print(i, line.strip())
                count += 1
print('count', count)
