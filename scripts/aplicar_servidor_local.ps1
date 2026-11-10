# Local Windows/WAMP deployment helper. Run elevated; no DB or credential changes.
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $root
$report = Join-Path $root 'storage/temp/server-validation-result.json'
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [System.Security.Principal.WindowsPrincipal]::new($identity)
if (-not $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'O Windows exige uma sessao elevada para reiniciar o servico Apache.'
}
$httpd = 'C:/wamp/bin/apache/apache2.4.62.1/bin/httpd.exe'
$destination = 'C:/wamp/bin/apache/apache2.4.62.1/conf/extra/httpd-vhosts.conf'
$backup = Join-Path $root ('secrets/tls/vhosts-admin-before-' + (Get-Date -Format yyyyMMddHHmmss) + '.conf')
try {
    Copy-Item -LiteralPath $destination -Destination $backup
    Copy-Item -LiteralPath (Join-Path $root 'deploy/apache-vhosts-local.conf') -Destination $destination
    & $httpd -t
    if ($LASTEXITCODE -ne 0) {
        Copy-Item -LiteralPath $backup -Destination $destination
        throw 'A configuracao falhou na verificacao; foi reposta a anterior.'
    }
    Restart-Service -Name wampapache64 -ErrorAction Stop
    & (Join-Path $root 'scripts/validar_servidor.ps1')
    [ordered]@{success=$true;checked_at=(Get-Date).ToString('o');url='https://anferalta.local';checks=16} | ConvertTo-Json | Set-Content -LiteralPath $report -Encoding utf8
} catch {
    [ordered]@{success=$false;checked_at=(Get-Date).ToString('o');error=$_.Exception.Message} | ConvertTo-Json | Set-Content -LiteralPath $report -Encoding utf8
    throw
}
