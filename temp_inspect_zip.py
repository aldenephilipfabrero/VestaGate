import zipfile, os
path = r'C:\Users\acer\Downloads\capstone dataset.v2-dataset2.0.yolov8.zip'
print('exists', os.path.exists(path))
with zipfile.ZipFile(path, 'r') as z:
    names = z.namelist()
    print('files', len(names))
    for name in names[:60]:
        print(name)
    if len(names) > 60:
        print('...')
