#!/usr/bin/env python3
"""Build cPanel update archives from the active services, excluding all runtime/private files."""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SERVICES = ('identity', 'documents', 'integrations', 'partners', 'loans', 'finance', 'panel')
DIRECTORIES = ('src', 'public', 'config', 'database', 'migrations', 'bin', 'resources')
ROOT_FILES = ('.htaccess', 'bootstrap.php', 'router.php', 'composer.json', 'composer.lock', 'pdf-runtime-manifest.json')
PROTECTED = {'config/local.env.php', 'config/service-auth.local.php', 'public/install.php', 'public/install.php.locked'}
BOOTSTRAP_SERVICES = ('identity', 'documents', 'integrations')


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def select_files(service: str) -> dict[str, bytes]:
    source = ROOT / f'{service}.noeel.ir/public_html'
    paths = [source / name for name in ROOT_FILES if (source / name).is_file()]
    for directory in DIRECTORIES:
        if (source / directory).exists():
            paths.extend(path for path in (source / directory).rglob('*') if path.is_file())
    result = {}
    for path in sorted(paths):
        relative = path.relative_to(source).as_posix()
        if relative in PROTECTED or path.is_symlink():
            continue
        if any(part in ('storage', 'vendor', 'backups', 'uploads', 'logs', '__pycache__', 'tests', '.git') for part in path.relative_to(source).parts):
            continue
        if path.name.endswith(('.local.php', '.locked', '.log', '.zip', '.dump', '.pyc', '~')):
            continue
        if path.suffix == '.sql' and not relative.startswith(('database/migrations/', 'migrations/')):
            continue
        if path.name.startswith('.env') and path.name != '.env.example':
            continue
        if any(part.startswith('.') and part not in ('.htaccess', '.env.example') for part in path.relative_to(source).parts):
            continue
        result[relative] = path.read_bytes()
    return result


def metadata(files: dict[str, bytes]) -> dict[str, str]:
    code = files['config/version.php'].decode()
    values = {}
    for key in ('version', 'ui', 'phase'):
        match = re.search(r"['\"]" + key + r"['\"]\s*=>\s*['\"]([^'\"]+)['\"]", code)
        if not match:
            raise ValueError(f'Missing {key} in version metadata')
        values[key] = match.group(1)
    return values


def archive(path: Path, entries: dict[str, bytes], stamp: dt.datetime) -> None:
    with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as target:
        for name, data in sorted(entries.items()):
            info = zipfile.ZipInfo(name, stamp.timetuple()[:6])
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            target.writestr(info, data, compresslevel=9)


def build(output: Path) -> None:
    output.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.now(dt.timezone.utc).replace(microsecond=0)
    release_id = stamp.strftime('%Y%m%d-%H%M%S') + '-ui-2.16.0'
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    index = {'release_id': release_id, 'created_at_utc': stamp.isoformat(), 'source_commit': commit,
             'includes_uncommitted_work': True, 'deployment_order': list(SERVICES), 'packages': []}
    bootstrap = {}
    for service in SERVICES:
        files = select_files(service)
        meta = metadata(files)
        migrations = sorted(name for name in files if name.startswith(('database/migrations/', 'migrations/')) and name.endswith(('.php', '.sql')))
        new_migrations = sorted(name for name in migrations if Path(name).name.startswith('20261105'))
        manifest = {'service': service, **meta, 'api_version': 'v1', 'release_id': release_id,
                    'created_at_utc': stamp.isoformat(), 'database_migration_required': bool(migrations),
                    'excluded_runtime': ['config/local.env.php', 'config/service-auth.local.php', 'storage/', 'vendor/',
                                         'public/install.php', 'public/install.php.locked'],
                    'migration_policy': 'Run pending migrations; preserve existing tracking and all customer data.',
                    'files': {name: digest(data) for name, data in sorted(files.items())}}
        source_fingerprint = digest('\n'.join(f'{name}\0{digest(data)}' for name, data in sorted(files.items())).encode())
        manifest['source_fingerprint'] = source_fingerprint
        encoded = (json.dumps(manifest, ensure_ascii=False, indent=2) + '\n').encode()
        (ROOT / f'{service}.noeel.ir/public_html/release-manifest.json').write_bytes(encoded)
        files['release-manifest.json'] = encoded
        name = f'babanoell-{service}-{meta["version"]}-ui-{meta["ui"]}.zip'
        path = output / name
        archive(path, {f'{service}/{key}': data for key, data in files.items()}, stamp)
        index['packages'].append({'service': service, **meta, 'file': name, 'bytes': path.stat().st_size,
                                  'sha256': digest(path.read_bytes()), 'uncompressed_bytes': sum(map(len, files.values())),
                                  'files': len(files), 'migrations': len(migrations), 'new_migrations': new_migrations,
                                  'source_fingerprint': source_fingerprint})
        if service in BOOTSTRAP_SERVICES:
            for relative in ('src/Update/UpdateTool.php', 'src/Update/PackageIntegrity.php'):
                bootstrap[f'{service}/{relative}'] = files[relative]
    bootstrap_path = output / 'bootstrap-updaters.zip'
    archive(bootstrap_path, bootstrap, stamp)
    index['updater_bootstrap'] = {'file': bootstrap_path.name, 'bytes': bootstrap_path.stat().st_size,
                                 'sha256': digest(bootstrap_path.read_bytes()),
                                 'files': {key: digest(value) for key, value in bootstrap.items()}}
    (output / 'release-index.json').write_text(json.dumps(index, ensure_ascii=False, indent=2) + '\n')
    sums = [f'{package["sha256"]}  {package["file"]}' for package in index['packages']]
    sums += [f'{index["updater_bootstrap"]["sha256"]}  {bootstrap_path.name}']
    (output / 'SHA256SUMS.txt').write_text('\n'.join(sums) + '\n')
    for package in index['packages']:
        print(f'{package["service"]}: {package["files"]} files, {package["bytes"]:,} bytes, UI {package["ui"]}')
    print(f'Release index: {output / "release-index.json"}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    build(parser.parse_args().output)
