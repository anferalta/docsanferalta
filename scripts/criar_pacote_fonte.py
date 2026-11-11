"""Build a source-only ZIP from an explicit allowlist, never from a Git clone.

Includes clean schema, maintenance CLI and recovery procedures. Dependencies,
environment secrets and operational data are not bundled; provision the target
server and install dependencies from the lock file before running installation.
"""
from datetime import datetime, timezone
from hashlib import sha256
import json
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parents[1]
selected = set()


def include(path):
    resolved = path.resolve()
    if path.is_symlink() or not resolved.is_relative_to(root):
        raise ValueError(f"Unsafe source path: {path.name}")
    if path.is_file():
        selected.add(path)


for name in ['composer.json', 'composer.lock', 'LICENSE', '.env.example', 'install.php', 'install.example.json',
             '.htaccess', 'public/index.php', 'public/.htaccess',
             'docs/instalacao-independente.md']:
    include(root / name)
for folder in ['App', 'routes', 'cron', 'cli']:
    for path in (root / folder).rglob('*'):
        parts = path.relative_to(root).parts
        if any(part.lower() in {'storage', 'uploads', 'backups', 'logs', 'vendor', 'public'} for part in parts):
            continue
        if path.suffix in {'.php', '.twig'} and '_bak' not in path.stem:
            include(path)
for path in (root / 'scripts/migrations').glob('*.sql'):
    include(path)
for path in (root / 'database/migrations').glob('*.sql'):
    include(path)
for name in ['recuperacao-e-instalacao.md', 'validacao-servidor.md', 'instalador.md', 'guia-instalacao.md', 'instalador-windows-exe.md', 'instalacao-windows-de-raiz.md']:
    include(root / 'docs' / name)
assets = {'.css', '.js', '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp',
          '.ico', '.woff', '.woff2', '.ttf', '.eot'}
for path in (root / 'public/assets').rglob('*'):
    if path.suffix.lower() in assets:
        include(path)

output_dir = root / 'dist'
output_dir.mkdir(exist_ok=True)
if output_dir.is_symlink():
    raise ValueError('dist must not be a symlink')
output = output_dir / ('anferaltadocs-source-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ') + '.zip')
manifest = {}
with ZipFile(output, 'x', ZIP_DEFLATED) as archive:
    for path in sorted(selected):
        relative = path.relative_to(root).as_posix()
        content = path.read_bytes()
        manifest[relative] = sha256(content).hexdigest()
        archive.writestr(relative, content)
    archive.writestr('source-manifest.json', json.dumps(manifest, indent=2))
print(f'{output}\nFiles: {len(manifest)}')
