"""Install only the known static marketing files into the local public root."""
from pathlib import Path
from zipfile import ZipFile
import re

ROOT=Path(__file__).resolve().parents[1]
SOURCE=Path('C:/Users/Administrador/.codex/visualizations/2026/11/10/01a1c6c0-f466-7d13-b176-b32b9a01c0e4/anferaltadocs-site/release')
files={}
with ZipFile(SOURCE/'AnferaltaDocs-Site-Fase-02-1.zip') as z:
    for name in ['index.html','styles.css','app.js','contact-request.js','assets/favicon.svg']:
        files[name]=z.read('www/anferalta-site/'+name)
with ZipFile(SOURCE/'AnferaltaDocs-Paginas-Setores.zip') as z:
    for name in ['index.html','escolas.html','empresas.html','particulares.html','sector-pages.css','sector-pages.js']:
        files[name]=z.read('www/anferalta-site/'+name)
for name,data in files.items():
    if name.endswith('.html'):
        text=data.decode('utf-8')
        text=text.replace('name="robots" content="index,follow"','name="robots" content="noindex,nofollow"')
        text=re.sub(r'<link rel="canonical"[^>]*>|<meta property="og:url"[^>]*>','',text)
        # Navigation stays on the local origin, including application login.
        text=text.replace('href="https://anferalta.com/','href="/')
        data=text.encode('utf-8')
    target=ROOT/'public/anferalta-site'/name
    target.parent.mkdir(parents=True,exist_ok=True)
    if target.exists() and target.read_bytes()!=data:
        backup=ROOT/'storage/temp/site-local-antes'/name
        backup.parent.mkdir(parents=True,exist_ok=True)
        if not backup.exists():backup.write_bytes(target.read_bytes())
    target.write_bytes(data)
print(f'Preparados {len(files)} ficheiros estáticos em public/anferalta-site.')
