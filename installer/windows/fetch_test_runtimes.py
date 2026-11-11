"""Fetch official runtime archives into ignored dist for isolated validation only."""
import concurrent.futures
import hashlib
import json
from pathlib import Path
import urllib.request

root = Path(__file__).resolve().parents[2] / 'dist/runtime-cache'
root.mkdir(parents=True, exist_ok=True)
items = {
    'php': ('https://downloads.php.net/~windows/releases/php-8.4.27-1-nts-Win32-vs17-x64.zip', 'php.zip'),
    'mysql': ('https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.13-winx64.zip', 'mysql.zip'),
    'vc': ('https://aka.ms/vc14/vc_redist.x64.exe', 'vc_redist.x64.exe'),
    'rewrite': ('https://download.microsoft.com/download/1/2/8/128E2E22-C1B9-44A4-BE2A-5859ED1D4592/rewrite_amd64_en-US.msi', 'rewrite.msi'),
}

def fetch(item):
    key, (url, name) = item
    target = root / name
    if not target.exists():
        with urllib.request.urlopen(url, timeout=120) as source, target.with_suffix('.part').open('wb') as output:
            final = source.url
            while block := source.read(1024 * 1024):
                output.write(block)
        target.with_suffix('.part').replace(target)
    else:
        final = url
    data = target.read_bytes()
    return key, dict(url=url, file=name, sha256=hashlib.sha256(data).hexdigest(), md5=hashlib.md5(data).hexdigest(), bytes=len(data), resolved_url=final)

with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    results = dict(pool.map(fetch, items.items()))
if results['php']['sha256'] != 'dbae154a0a89791f36c3d48e68bae797878333e7d6cb5721e74617de59b63ab7':
    raise RuntimeError('PHP differs from the official SHA256')
if results['mysql']['md5'] != '7b94489d5524dea9b27097068bea25a8':
    raise RuntimeError('MySQL differs from the official download checksum')
(root / 'download-report.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
