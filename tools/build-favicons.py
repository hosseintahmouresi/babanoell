#!/usr/bin/env python3
"""Resize the two original rasters without cropping, retouching, or removing their details."""
from pathlib import Path
import argparse
import hashlib
import json
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]


def build(navy: Path, pale: Path) -> None:
    source = ROOT / 'ui/favicon-source'
    output = ROOT / 'ui/favicons'
    source.mkdir(parents=True, exist_ok=True)
    output.mkdir(parents=True, exist_ok=True)
    frames = []
    originals = {}
    for name, path in [('navy', navy), ('pale', pale)]:
        data = path.read_bytes()
        (source / (name + '.png')).write_bytes(data)
        originals[name] = {'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data)}
        with Image.open(path) as image:
            image = image.convert('RGBA')
            originals[name]['size'] = list(image.size)
            frame = image.resize((32, 32), Image.Resampling.LANCZOS)
            frame.save(output / ('favicon-' + name + '.png'), optimize=True, compress_level=9)
            frames.append(frame)
            if name == 'navy':
                image.resize((180, 180), Image.Resampling.LANCZOS).save(
                    output / 'apple-touch-icon.png', optimize=True, compress_level=9)
                image.save(output / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)], bitmap_format='bmp')

    # GIF supports one-bit transparency; full alpha is retained in both PNG frames.
    sample = Image.new('RGB', (32, 64))
    for number, frame in enumerate(frames):
        sample.paste(frame.convert('RGB'), (0, number * 32))
    palette = sample.quantize(colors=255, method=Image.Quantize.MEDIANCUT)
    gif_frames = []
    for frame in frames:
        indexed = frame.convert('RGB').quantize(palette=palette, dither=Image.Dither.NONE)
        mask = frame.getchannel('A').point(lambda value: 255 if value < 128 else 0)
        indexed.paste(255, mask=mask)
        indexed.info['transparency'] = 255
        gif_frames.append(indexed)
    gif_frames[0].save(output / 'favicon.gif', save_all=True, append_images=gif_frames[1:],
                       duration=[500, 500], loop=0, disposal=2, transparency=255,
                       background=255, optimize=False)
    manifest = {'originals': originals, 'frame_ms': 500,
                'processing': 'Whole original canvas resized with Lanczos; no cleanup, crop, recolor, or background replacement.',
                'files': {path.name: {'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
                          for path in sorted(output.iterdir())}}
    (source / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print(json.dumps(manifest, indent=2))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--navy', type=Path, required=True)
    parser.add_argument('--pale', type=Path, required=True)
    args = parser.parse_args()
    build(args.navy, args.pale)
