import argparse
import random
from pathlib import Path
import shutil


def create_split(src_images_dirs, src_labels_dirs, dest_base, backup_dir, split=0.8, seed=42):
    random.seed(seed)
    src_images = []
    for d in src_images_dirs:
        p = Path(d)
        if p.exists():
            src_images.extend([x for x in p.iterdir() if x.suffix.lower() in ('.jpg', '.jpeg', '.png')])

    labels_map = {}
    for d in src_labels_dirs:
        p = Path(d)
        if p.exists():
            for f in p.iterdir():
                if f.suffix.lower() == '.txt':
                    labels_map[f.stem] = f

    pairs = []
    orphan_labels = set(labels_map.keys())
    for img in src_images:
        stem = img.stem
        if stem in labels_map:
            pairs.append((img, labels_map[stem]))
            orphan_labels.discard(stem)

    pairs.sort()  # deterministic ordering prior to shuffle
    random.shuffle(pairs)

    total = len(pairs)
    train_count = int(total * split)

    dest_base = Path(dest_base)
    img_train = dest_base / 'images' / 'train'
    img_val = dest_base / 'images' / 'val'
    lbl_train = dest_base / 'labels' / 'train'
    lbl_val = dest_base / 'labels' / 'val'

    for d in (img_train, img_val, lbl_train, lbl_val):
        d.mkdir(parents=True, exist_ok=True)

    for i, (img, lbl) in enumerate(pairs):
        if i < train_count:
            tgt_img = img_train / img.name
            tgt_lbl = lbl_train / lbl.name
        else:
            tgt_img = img_val / img.name
            tgt_lbl = lbl_val / lbl.name
        shutil.copy2(img, tgt_img)
        shutil.copy2(lbl, tgt_lbl)

    # backup orphan labels
    backup_dir = Path(backup_dir)
    backup_dir.mkdir(parents=True, exist_ok=True)
    for stem in orphan_labels:
        src = labels_map[stem]
        shutil.copy2(src, backup_dir / src.name)

    return {
        'total_pairs': total,
        'train': train_count,
        'val': total - train_count,
        'backup_orphans': len(orphan_labels),
        'dest': str(dest_base)
    }


def write_dataset_yaml(dest_base, names):
    dest_base = Path(dest_base)
    yaml_path = dest_base / 'dataset.yaml'
    content = []
    content.append(f"train: {str((dest_base / 'images' / 'train').as_posix())}")
    content.append(f"val: {str((dest_base / 'images' / 'val').as_posix())}")
    content.append('')
    content.append('nc: %d' % len(names))
    content.append('names:')
    for i, n in enumerate(names):
        content.append(f"  {i}: '{n}'")

    yaml_path.write_text('\n'.join(content))
    return str(yaml_path)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--src-images', nargs='+', default=['cv_training/dataset/images/train', 'cv_training/dataset/images/val'])
    parser.add_argument('--src-labels', nargs='+', default=['cv_training/dataset/labels/train', 'cv_training/dataset/labels/val'])
    parser.add_argument('--dest', default='cv_training/dataset_new')
    parser.add_argument('--backup', default='cv_training/dataset_backup_labels')
    parser.add_argument('--split', type=float, default=0.8)
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--classes-file', default='cv_training/classes.txt')
    args = parser.parse_args()

    names = []
    cf = Path(args.classes_file)
    if cf.exists():
        names = [l.strip() for l in cf.read_text().splitlines() if l.strip()]

    res = create_split(args.src_images, args.src_labels, args.dest, args.backup, split=args.split, seed=args.seed)
    yaml = write_dataset_yaml(args.dest, names if names else ['class0'])
    print('Done:', res)
    print('Wrote dataset yaml:', yaml)


if __name__ == '__main__':
    main()
