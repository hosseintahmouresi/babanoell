#!/usr/bin/env python3
"""Build cPanel update archives from the active services, excluding all runtime/private files."""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import io
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


def permitted(relative: str) -> bool:
    path = Path(relative)
    if relative in PROTECTED:
        return False
    if len(path.parts) == 1:
        if relative not in ROOT_FILES:
            return False
    elif path.parts[0] not in DIRECTORIES:
        return False
    if any(part in ('storage', 'vendor', 'backups', 'uploads', 'logs', '__pycache__', 'tests', '.git') for part in path.parts):
        return False
    if path.name.endswith(('.local.php', '.locked', '.log', '.zip', '.dump', '.pyc', '~')):
        return False
    if path.suffix == '.sql' and not relative.startswith(('database/migrations/', 'migrations/')):
        return False
    if path.name.startswith('.env') and path.name != '.env.example':
        return False
    return not any(part.startswith('.') and part not in ('.htaccess', '.env.example') for part in path.parts)


def git_files(service: str, commit: str) -> dict[str, bytes]:
    prefix = f'{service}.noeel.ir/public_html/'
    listing = subprocess.check_output(['git', 'ls-tree', '-r', '-z', commit, '--', prefix], cwd=ROOT)
    selected = []
    for entry in listing.split(b'\0'):
        if not entry:
            continue
        header, encoded_path = entry.split(b'\t', 1)
        mode, kind, object_id = header.decode().split()
        relative = encoded_path.decode().removeprefix(prefix)
        if mode in ('100644', '100755') and kind == 'blob' and permitted(relative):
            selected.append((relative, object_id))
    # Filter paths before reading blobs; private configuration and runtime are never read.
    output = subprocess.check_output(['git', 'cat-file', '--batch'], cwd=ROOT,
                                     input=''.join(oid + '\n' for _, oid in selected).encode())
    stream = io.BytesIO(output)
    files = {}
    for relative, expected in selected:
        actual, kind, size = stream.readline().decode().split()
        if actual != expected or kind != 'blob':
            raise ValueError(f'Unexpected Git object for {relative}')
        files[relative] = stream.read(int(size))
        if len(files[relative]) != int(size) or stream.read(1) != b'\n':
            raise ValueError(f'Truncated Git object for {relative}')
    return files


def select_files(service: str, commit: str | None = None) -> dict[str, bytes]:
    if commit:
        return git_files(service, commit)
    source = ROOT / f'{service}.noeel.ir/public_html'
    paths = [source / name for name in ROOT_FILES if (source / name).is_file()]
    for directory in DIRECTORIES:
        if (source / directory).exists():
            paths.extend(path for path in (source / directory).rglob('*') if path.is_file())
    result = {}
    for path in sorted(paths):
        relative = path.relative_to(source).as_posix()
        if path.is_symlink() or not permitted(relative):
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


def build(output: Path, ref: str | None = None, updater_bootstrap: bool = True, base_ref: str | None = None, services: tuple[str, ...] | None = None) -> None:
    selected = tuple(service for service in SERVICES if service in (services or SERVICES))
    if services is not None and (not services or len(set(services)) != len(services) or set(services) - set(SERVICES)):
        raise ValueError('Choose unique, known service names')
    output.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.now(dt.timezone.utc).replace(microsecond=0)
    commit = subprocess.check_output(['git', 'rev-parse', '--verify', (ref or 'HEAD') + '^{commit}'], cwd=ROOT, text=True).strip()
    dirty = False if ref else bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT))
    snapshots = {service: select_files(service, commit if ref else None) for service in selected}
    base_commit = subprocess.check_output(['git', 'rev-parse', '--verify', base_ref + '^{commit}'], cwd=ROOT, text=True).strip() if base_ref else None
    baseline = {service: set(git_files(service, base_commit)) for service in selected} if base_commit else {}
    ui_versions = {metadata(files)['ui'] for files in snapshots.values()}
    if len(ui_versions) != 1:
        raise ValueError('All service packages must use the same UI version')
    release_id = stamp.strftime('%Y%m%d-%H%M%S') + '-ui-' + next(iter(ui_versions))
    index = {'release_id': release_id, 'created_at_utc': stamp.isoformat(), 'source_commit': commit,
             'includes_uncommitted_work': dirty, 'deployment_order': list(selected), 'packages': []}
    if base_commit:
        index['upgrade_base_commit'] = base_commit
    bootstrap = {}
    for service in selected:
        files = snapshots[service]
        meta = metadata(files)
        migrations = sorted(name for name in files if name.startswith(('database/migrations/', 'migrations/')) and name.endswith(('.php', '.sql')))
        new_migrations = sorted(name for name in migrations if name not in baseline.get(service, set())) if base_commit else []
        manifest = {'service': service, **meta, 'api_version': 'v1', 'release_id': release_id,
                    'source_commit': commit, 'includes_uncommitted_work': dirty,
                    'created_at_utc': stamp.isoformat(), 'database_migration_required': bool(migrations),
                    'excluded_runtime': ['config/local.env.php', 'config/service-auth.local.php', 'storage/', 'vendor/',
                                         'public/install.php', 'public/install.php.locked'],
                    'migration_policy': 'Run pending migrations; preserve existing tracking and all customer data.',
                    'files': {name: digest(data) for name, data in sorted(files.items())}}
        source_fingerprint = digest('\n'.join(f'{name}\0{digest(data)}' for name, data in sorted(files.items())).encode())
        manifest['source_fingerprint'] = source_fingerprint
        encoded = (json.dumps(manifest, ensure_ascii=False, indent=2) + '\n').encode()
        files['release-manifest.json'] = encoded
        name = f'babanoell-{service}-{meta["version"]}-ui-{meta["ui"]}.zip'
        path = output / name
        archive(path, {f'{service}/{key}': data for key, data in files.items()}, stamp)
        index['packages'].append({'service': service, **meta, 'file': name, 'bytes': path.stat().st_size,
                                  'sha256': digest(path.read_bytes()), 'uncompressed_bytes': sum(map(len, files.values())),
                                  'files': len(files), 'migrations': len(migrations), 'new_migrations': new_migrations,
                                  'source_fingerprint': source_fingerprint})
        if updater_bootstrap and service in BOOTSTRAP_SERVICES:
            for relative in ('src/Update/UpdateTool.php', 'src/Update/PackageIntegrity.php'):
                bootstrap[f'{service}/{relative}'] = files[relative]
    if updater_bootstrap:
        bootstrap_path = output / 'bootstrap-updaters.zip'
        archive(bootstrap_path, bootstrap, stamp)
        index['updater_bootstrap'] = {'file': bootstrap_path.name, 'bytes': bootstrap_path.stat().st_size,
                                     'sha256': digest(bootstrap_path.read_bytes()),
                                     'files': {key: digest(value) for key, value in bootstrap.items()}}
    (output / 'release-index.json').write_text(json.dumps(index, ensure_ascii=False, indent=2) + '\n')
    sums = [f'{package["sha256"]}  {package["file"]}' for package in index['packages']]
    if updater_bootstrap:
        sums += [f'{index["updater_bootstrap"]["sha256"]}  {bootstrap_path.name}']
    (output / 'SHA256SUMS.txt').write_text('\n'.join(sums) + '\n')
    for package in index['packages']:
        print(f'{package["service"]}: {package["files"]} files, {package["bytes"]:,} bytes, UI {package["ui"]}')
    print(f'Release index: {output / "release-index.json"}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--ref', help='Build only the committed files at this Git ref; leave the source tree unchanged.')
    parser.add_argument('--base-ref', help='Installed source commit used to identify new migration files in the release index.')
    parser.add_argument('--skip-updater-bootstrap', action='store_true', help='Omit the legacy UI 2.16.0 updater compatibility files.')
    parser.add_argument('--services', nargs='+', choices=SERVICES, help='Build only these services, in the canonical deployment order.')
    args = parser.parse_args()
    build(args.output, args.ref, not args.skip_updater_bootstrap, args.base_ref, tuple(args.services) if args.services is not None else None)
