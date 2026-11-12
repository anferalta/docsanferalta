# Negative HTTPS certificate tests; does not import any certificate into a Windows store.
$ErrorActionPreference='Stop'
$root=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$fixture=Join-Path $root ('dist\certificate-policy-'+[Guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($fixture)|Out-Null
$sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
& "$env:WINDIR\System32\icacls.exe" $fixture /inheritance:r /grant:r ('*'+$sid+':(OI)(CI)F') '*S-1-5-18:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE) { throw 'Cannot secure certificate fixture' }
Add-Type -Path (Join-Path $PSScriptRoot 'CertificatePolicy.cs') -IgnoreWarnings
$before=@(Get-ChildItem Cert:\LocalMachine\Root).Count
$password=[Guid]::NewGuid().ToString('N')
$rsa=[Security.Cryptography.RSA]::Create(2048)
try {
    $request=[Security.Cryptography.X509Certificates.CertificateRequest]::new('CN=documents.fixture.invalid',$rsa,[Security.Cryptography.HashAlgorithmName]::SHA256,[Security.Cryptography.RSASignaturePadding]::Pkcs1)
    $san=[Security.Cryptography.X509Certificates.SubjectAlternativeNameBuilder]::new(); $san.AddDnsName('documents.fixture.invalid'); $request.CertificateExtensions.Add($san.Build())
    $oids=[Security.Cryptography.OidCollection]::new(); $oids.Add([Security.Cryptography.Oid]::new('1.3.6.1.5.5.7.3.1'))|Out-Null
    $request.CertificateExtensions.Add([Security.Cryptography.X509Certificates.X509EnhancedKeyUsageExtension]::new($oids,$true))
    $cert=$request.CreateSelfSigned([DateTimeOffset]::UtcNow.AddDays(-1),[DateTimeOffset]::UtcNow.AddDays(2))
    try {
        $pfx=Join-Path $fixture 'untrusted.pfx'
        [IO.File]::WriteAllBytes($pfx,$cert.Export([Security.Cryptography.X509Certificates.X509ContentType]::Pfx,$password))
        $checks=@()
        foreach($case in @(@{name='Untrusted certificate rejected';host='documents.fixture.invalid';password=$password},@{name='Incorrect PFX password rejected';host='documents.fixture.invalid';password='invalid-fixture-password'},@{name='Untrusted certificate for another domain rejected';host='other.fixture.invalid';password=$password})) {
            $refused=$false
            try { $result=[AnferaltaDocsSetup.CertificatePolicy]::Validate($pfx,$case.password,$case.host); $result.Dispose() } catch { $refused=$true }
            $checks+=@{name=$case.name;passed=$refused}
            if (!$refused) { throw ('Test failed: '+$case.name) }
        }
        $unchanged=@(Get-ChildItem Cert:\LocalMachine\Root).Count -eq $before
        $checks+=@{name='Machine root trust store unchanged';passed=$unchanged}
        if (!$unchanged) { throw 'Root store changed' }
        $report=Join-Path $fixture 'test-report.json'
        @{checks=$checks;certificates_imported=$false}|ConvertTo-Json -Depth 4|Set-Content -LiteralPath $report -Encoding UTF8
        Write-Output ('Certificate test report: '+$report)
    } finally { $cert.Dispose() }
} finally { $rsa.Dispose() }
