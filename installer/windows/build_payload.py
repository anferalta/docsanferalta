"""Build the Windows installer's clean app + locked vendor payload. No runtime secrets."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import runpy

root = Path(__file__).resolve().parents[2]
runpy.run_path(str(root / 'scripts/criar_pacote_fonte.py'), run_name='__main__')
source = max((root / 'dist').glob('anferaltadocs-source-*.zip'), key=lambda p: p.stat().st_mtime_ns)
lock = json.loads((root / 'composer.lock').read_text(encoding='utf-8-sig'))
installed = json.loads((root / 'vendor/composer/installed.json').read_text(encoding='utf-8-sig'))
expected = {p['name']: p['version'] for p in lock['packages']}
actual = {p['name']: p['version'] for p in installed['packages']}
if expected != actual:
    raise RuntimeError('vendor must match composer.lock exactly; run composer install first')

payload = root / 'dist/windows-payload.zip'
files = {}
with ZipFile(source) as archive:
    for name in archive.namelist():
        files[name] = archive.read(name)
for path in (root / 'vendor').rglob('*'):
    if not path.is_file():
        continue
    if path.is_symlink() or not path.resolve().is_relative_to(root / 'vendor'):
        raise RuntimeError('Symlink in vendor')
    relative = path.relative_to(root).as_posix()
    if any(part in {'.git', '.env'} for part in path.parts):
        raise RuntimeError('Unexpected private file in vendor')
    files[relative] = path.read_bytes()
folded = [p.casefold() for p in files]
if len(set(folded)) != len(folded):
    raise RuntimeError('Windows path collision')
forbidden = [p for p in files if p == '.env' or p.startswith(('secrets/', 'storage/', 'backups/', 'logs/', '.git/', 'public/uploads/', 'App/storage/', 'App/public/', 'App/Uploads/'))]
if forbidden:
    raise RuntimeError('Private paths in payload')
manifest = {p: hashlib.sha256(content).hexdigest() for p, content in sorted(files.items())}
with ZipFile(payload, 'w', ZIP_DEFLATED) as archive:
    for name, content in sorted(files.items()):
        archive.writestr(name, content)
    archive.writestr('setup-manifest.json', json.dumps(manifest, indent=2))
with ZipFile(payload) as archive:
    if archive.testzip() is not None:
        raise RuntimeError('Corrupt ZIP')
report = {'files': len(files), 'bytes': payload.stat().st_size, 'sha256': hashlib.sha256(payload.read_bytes()).hexdigest(), 'vendor_matches_lock': True, 'private_paths': forbidden}
(root / 'dist/windows-payload-report.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report))
