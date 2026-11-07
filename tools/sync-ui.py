#!/usr/bin/env python3
"""Build the shared local UI into each independently deployable service."""
from pathlib import Path
import argparse
import base64
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'ui'
SERVICES = ('identity', 'loans', 'documents', 'partners', 'finance', 'integrations', 'panel')
NAMESPACES = dict(zip(SERVICES, ('Auth', 'Loans', 'Documents', 'Partners', 'Finance', 'Integrations', 'Panel')))

def build(check=False):
    css = '\n'.join((SOURCE / 'src' / name).read_text() for name in ('compatibility.css', 'redesign.css', 'icons.css'))
    js = '\n'.join((SOURCE / 'src' / name).read_text() for name in ('compatibility.js', 'interactions.js'))
    files = {}
    for service in SERVICES:
        target = ROOT / f'{service}.noeel.ir/public_html/public/assets'
        for name in ('platform', 'panel', 'babanoell-ui'):
            files[target / f'{name}.css'] = css.encode()
            files[target / f'{name}.js'] = js.encode()
        for font in (SOURCE / 'fonts').iterdir():
            files[target / font.name] = font.read_bytes()
        for icon in (SOURCE / 'favicons').iterdir():
            if icon.suffix == '.png':
                files[target / icon.name] = icon.read_bytes()
            elif icon.suffix in ('.gif', '.ico'):
                # Existing native updaters accept JSON but not GIF/ICO. The asset gateway
                # decodes these small local containers to the original binary MIME type.
                encoded = base64.b64encode(icon.read_bytes()).decode('ascii')
                files[target / (icon.name + '.json')] = (json.dumps({'base64': encoded}) + '\n').encode()
        favicon_js = (SOURCE / 'src/favicon.js').read_text()
        for token, name in [('NAVY_PNG', 'favicon-navy.png'), ('PALE_PNG', 'favicon-pale.png')]:
            favicon_js = favicon_js.replace('{{'+token+'}}', base64.b64encode((SOURCE/'favicons'/name).read_bytes()).decode('ascii'))
        files[target / 'favicon.js'] = favicon_js.encode()
        if service == 'panel':
            files[target / 'credit.js'] = (SOURCE / 'src/credit.js').read_bytes()
        helper = (SOURCE / 'php/Favicon.php.in').read_text().replace('{{NAMESPACE}}', NAMESPACES[service])
        files[target.parents[1] / 'src/Platform/Favicon.php'] = helper.encode()
        files[target / 'Font-Awesome-LICENSE.txt'] = (SOURCE / 'licenses/Font-Awesome.txt').read_bytes()
    pdf_target = ROOT / 'documents.noeel.ir/public_html/resources/fonts'
    for font in (SOURCE / 'pdf-fonts').glob('*.ttf'):
        files[pdf_target / font.name] = font.read_bytes()
    mismatched = []
    for target, data in files.items():
        if check:
            if not target.is_file() or target.read_bytes() != data:
                mismatched.append(str(target.relative_to(ROOT)))
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
    if mismatched:
        raise SystemExit('UI files differ from their source: ' + ', '.join(mismatched))
    manifest = {str(path.relative_to(SOURCE)): {'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
                for folder in ('fonts', 'pdf-fonts', 'favicons') for path in sorted((SOURCE / folder).iterdir()) if path.is_file()}
    if not check:
        (SOURCE / 'asset-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    if check:
        print(f'All {len(files)} distributed UI files match their source')
        return
    print(f'Shared UI built for {len(SERVICES)} services; CSS={len(css.encode())} B, JS={len(js.encode())} B')

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='verify distributed assets without changing files')
    build(parser.parse_args().check)
