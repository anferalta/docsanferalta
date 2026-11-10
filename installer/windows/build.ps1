$ErrorActionPreference = 'Stop'
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Push-Location -LiteralPath $root
try {
    python -X utf8 installer/windows/build_payload.py
    if ($LASTEXITCODE -ne 0) { throw 'Payload build failed' }
    $compiler = Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
    if (-not (Test-Path -LiteralPath $compiler)) { throw 'Install the .NET Framework compiler before building.' }
    & $compiler /nologo /target:winexe /platform:x64 /optimize+ /utf8output /out:dist/AnferaltaDocs-Setup.exe /win32manifest:installer/windows/app.manifest /resource:dist/windows-payload.zip,AnferaltaDocs.Payload.zip /reference:System.dll /reference:System.Core.dll /reference:System.Drawing.dll /reference:System.Windows.Forms.dll /reference:System.Web.Extensions.dll /reference:System.IO.Compression.dll /reference:System.IO.Compression.FileSystem.dll installer/windows/Setup.cs
    if ($LASTEXITCODE -ne 0) { throw 'EXE compilation failed' }
    $hash = Get-FileHash -LiteralPath dist/AnferaltaDocs-Setup.exe -Algorithm SHA256
    ($hash.Hash.ToLowerInvariant() + '  AnferaltaDocs-Setup.exe') | Set-Content -LiteralPath dist/AnferaltaDocs-Setup.exe.sha256 -Encoding ascii
    [pscustomobject]@{File=$hash.Path;Bytes=(Get-Item -LiteralPath $hash.Path).Length;SHA256=$hash.Hash;Signature=(Get-AuthenticodeSignature -LiteralPath $hash.Path).Status} | Format-List
} finally { Pop-Location }
