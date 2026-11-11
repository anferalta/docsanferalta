param([switch] $TestBuild, [switch] $SkipPayload)
$ErrorActionPreference = 'Stop'
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Push-Location -LiteralPath $root
try {
    if (!$SkipPayload) {
        python -u -X utf8 installer/windows/build_payload.py
        if ($LASTEXITCODE -ne 0) { throw 'Payload build failed' }
    }
    $compiler = Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
    if (-not (Test-Path -LiteralPath $compiler)) { throw 'Install the .NET Framework compiler before building.' }
    $output = Join-Path $root $(if ($TestBuild) { 'dist\AnferaltaDocs-Setup-Test.exe' } else { 'dist\AnferaltaDocs-Setup.exe' })
    $manifest = Join-Path $root 'installer\windows\app.manifest'
    $payload = Join-Path $root 'dist\windows-payload.zip'
    if ($TestBuild) {
        $testManifest = Join-Path $root 'dist\test.manifest'
        ([IO.File]::ReadAllText($manifest).Replace('requireAdministrator','asInvoker')) | Set-Content -LiteralPath $testManifest -Encoding UTF8
        $manifest = $testManifest
    }
    $sources = @('Setup.cs','CleanMachineWizard.cs','CertificatePolicy.cs','BootstrapSelfTest.cs') | ForEach-Object { Join-Path $PSScriptRoot $_ }
    $resourceRoot = Join-Path $root 'dist\bootstrap-resources'
    New-Item -ItemType Directory -Path $resourceRoot -Force | Out-Null
    $resources = @('/resource:'+$payload+',AnferaltaDocs.Payload.zip')
    foreach ($name in 'Test-CleanMachine.ps1','Provision.ps1','CertificatePolicy.cs','bootstrap-database.php','runtimes.json') {
        $copy = Join-Path $resourceRoot $name
        $encoding = New-Object Text.UTF8Encoding($name.EndsWith('.ps1'))
        [IO.File]::WriteAllText($copy,[IO.File]::ReadAllText((Join-Path $PSScriptRoot $name)),$encoding)
        $resources += '/resource:'+$copy+',AnferaltaDocs.Bootstrap.'+$name
    }
    & $compiler /nologo /target:winexe /platform:x64 /optimize+ /codepage:65001 /utf8output ('/out:'+$output) ('/win32manifest:'+$manifest) $resources /reference:System.dll /reference:System.Core.dll /reference:System.Drawing.dll /reference:System.Windows.Forms.dll /reference:System.Web.Extensions.dll /reference:System.IO.Compression.dll /reference:System.IO.Compression.FileSystem.dll $sources
    if ($LASTEXITCODE -ne 0) { throw 'EXE compilation failed' }
    $hash = Get-FileHash -LiteralPath $output -Algorithm SHA256
    ($hash.Hash.ToLowerInvariant() + '  '+[IO.Path]::GetFileName($output)) | Set-Content -LiteralPath ($output+'.sha256') -Encoding ascii
    [pscustomobject]@{File=$hash.Path;Bytes=(Get-Item -LiteralPath $hash.Path).Length;SHA256=$hash.Hash;Signature=(Get-AuthenticodeSignature -LiteralPath $hash.Path).Status} | Format-List
} finally { Pop-Location }
