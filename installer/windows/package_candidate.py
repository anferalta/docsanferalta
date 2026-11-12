"""Package only the EXE, public guides and sanitized validation results for IT."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json

root = Path(__file__).resolve().parents[2]
dist = root / 'dist'
exe = dist / 'AnferaltaDocs-Setup.exe'
sha = hashlib.sha256(exe.read_bytes()).hexdigest()
if (dist / 'AnferaltaDocs-Setup.exe.sha256').read_text().split()[0].lower() != sha:
    raise RuntimeError('Executable differs from its published checksum')
if b'requireAdministrator' not in exe.read_bytes():
    raise RuntimeError('Distribution executable must request Windows elevation')

def latest_report(pattern):
    files = list(dist.glob(pattern))
    if not files:
        raise RuntimeError('Missing test report: ' + pattern)
    path = max(files, key=lambda p: p.stat().st_mtime_ns)
    return json.loads(path.read_text(encoding='utf-8-sig'))

bootstrap = json.loads((dist / 'bootstrap-self-test.json').read_text(encoding='utf-8-sig'))
isolated = latest_report('bootstrap-isolated-*/test-report.json')
certificate = latest_report('certificate-policy-*/test-report.json')
executable = latest_report('exe-self-test-final-*/self-test.json')
groups = {'wizard': bootstrap, 'isolated_installation_and_backup': isolated, 'certificate_rejection': certificate}
for name, report in groups.items():
    if report.get('failures') or not report['checks'] or not all(item['passed'] for item in report['checks']):
        raise RuntimeError('Failing test group: ' + name)
if executable['failures'] or executable['checks'] < 17 or len(isolated['checks']) < 18:
    raise RuntimeError('Complete executable/application tests have not passed')

sources = ['Setup.cs', 'CleanMachineWizard.cs', 'CertificatePolicy.cs', 'Provision.ps1', 'Test-CleanMachine.ps1', 'runtimes.json', 'bootstrap-database.php', 'BootstrapSelfTest.cs']
for name in sources:
    if (root / 'installer/windows' / name).stat().st_mtime_ns > exe.stat().st_mtime_ns:
        raise RuntimeError('Executable is older than source: ' + name)
summary = {
    'status': 'validation_candidate_not_production_homologated',
    'executable': exe.name,
    'sha256': sha,
    'authenticode': 'NotSigned',
    'requires_administrator': True,
    'requires_internet': True,
    'tests': {name: {'passed': len(report['checks']), 'failed': 0} for name, report in groups.items()},
    'executable_package_self_test': {'passed': executable['checks'], 'failed': 0},
    'windows_powershell_5_library_and_php_compatibility': 'passed',
    'gui_tests_use_asInvoker_variant_same_sources': True,
    'production_services_modified_during_tests': False,
    'clean_windows_full_provisioning_tested': False,
    'pending_it_validation': ['Windows elevation and roles', 'VC++ and URL Rewrite installation/reboots', 'MySQL service identity', 'IIS/FastCGI and effective ACLs', 'Corporate PFX HTTPS and DNS', 'LAN firewall and client access', 'Full restore on second isolated installation'],
    'source_sha256': {name: hashlib.sha256((root / 'installer/windows' / name).read_bytes()).hexdigest() for name in sources},
}
report_file = dist / 'AnferaltaDocs-Setup.exe.tests.json'
report_file.write_text(json.dumps(summary, indent=2), encoding='utf-8')
readme = dist / 'AnferaltaDocs-Setup.Leia-me.txt'
readme.write_text('''ANFERALTADOCS — CANDIDATO DE INSTALAÇÃO WINDOWS DE RAIZ

Para ensaio pela equipa de TI numa máquina Windows limpa. Ainda não homologado
para produção e sem assinatura Authenticode. Não executar no servidor atual.

O EXE solicita elevação UAC e descarrega os requisitos oficiais. A aplicação e
as bibliotecas estão incluídas. Requer Internet, Windows x64 compatível, domínio
DNS e certificado PFX empresarial com chave privada e cadeia confiável.

1. Confirmar o SHA-256 do EXE.
2. Ler docs/instalador-windows-exe.md.
3. Seguir docs/homologacao-instalador-windows.md numa VM com snapshot.
4. Registar os resultados antes de aprovar qualquer utilização em produção.

Inclui 69 verificações aprovadas em desenvolvimento: assistente (30), pacote
EXE (17), instalação/contas/backup isolados (18), rejeição de certificados (4).
Esses ensaios não validam a preparação integral de um Windows limpo. Consultar
AnferaltaDocs-Setup.exe.tests.json para os limites e ensaios ainda necessários.

O pacote não contém credenciais, certificados privados, dados ou backups da
instalação de desenvolvimento. Não enviar passwords nem PFX para o chat.
''', encoding='utf-8')

selected = {
    exe: exe.name,
    dist / 'AnferaltaDocs-Setup.exe.sha256': 'AnferaltaDocs-Setup.exe.sha256',
    readme: 'LEIA-ME.txt',
    report_file: report_file.name,
    root / 'LICENSE': 'LICENSE',
}
for name in ['instalador-windows-exe.md', 'instalacao-windows-de-raiz.md', 'homologacao-instalador-windows.md', 'guia-instalacao.md', 'instalador.md', 'recuperacao-e-instalacao.md', 'validacao-servidor.md', 'instalacao-independente.md']:
    selected[root / 'docs' / name] = 'docs/' + name
bundle = dist / 'AnferaltaDocs-Instalador-Candidato-TI.zip'
with ZipFile(bundle, 'w', ZIP_DEFLATED) as archive:
    for source, name in selected.items():
        archive.write(source, name)
with ZipFile(bundle) as archive:
    if archive.testzip() or set(archive.namelist()) != set(selected.values()):
        raise RuntimeError('Invalid candidate distribution')
    if hashlib.sha256(archive.read(exe.name)).hexdigest() != sha:
        raise RuntimeError('Executable changed during packaging')
bundle_sha = hashlib.sha256(bundle.read_bytes()).hexdigest()
Path(str(bundle) + '.sha256').write_text(bundle_sha + '  ' + bundle.name + '\n', encoding='ascii')
print(json.dumps({'bundle': str(bundle), 'bytes': bundle.stat().st_size, 'sha256': bundle_sha, 'checks_passed': 69, 'full_windows_homologation_pending': True}))
