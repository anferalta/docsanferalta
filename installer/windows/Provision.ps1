# Invoked only by the elevated clean-machine wizard, after read-only preflight.
[CmdletBinding()]
param([Parameter(Mandatory=$true)][string] $Config, [switch] $LibraryOnly)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2
$utf8 = New-Object Text.UTF8Encoding($false)
[Console]::OutputEncoding = $utf8
function Write-Utf8([string] $Path, [string] $Text) { [IO.File]::WriteAllText($Path, $Text, $utf8) }
function Invoke-Native([string] $File, [string[]] $Arguments, [int[]] $Allowed = @(0), [hashtable] $Environment = @{}) {
    # Windows quoting follows CommandLineToArgvW; no shell expansion, no secrets in args.
    $quoted = foreach ($arg in $Arguments) { '"' + [regex]::Replace([regex]::Replace($arg, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"' }
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $File; $start.Arguments = $quoted -join ' '
    $start.UseShellExecute = $false; $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true
    foreach ($key in @($start.EnvironmentVariables.Keys)) { if ($key -match '^(DB_|APP_|ADMIN_|MAIL_|BACKUP_|SESSION_|ALLOW_LEGACY_)') { $start.EnvironmentVariables.Remove($key) } }
    foreach ($key in $Environment.Keys) { $start.EnvironmentVariables[$key]=$Environment[$key] }
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    try {
        if (!$process.Start()) { throw 'Process start failed' }
        $output = $process.StandardOutput.ReadToEndAsync(); $errorOutput = $process.StandardError.ReadToEndAsync()
        if (!$process.WaitForExit(1200000)) { $process.Kill(); throw 'Operation timed out' }
        $process.WaitForExit()
        if ($process.ExitCode -notin $Allowed) { throw ('A operação falhou: {0}; código {1}.' -f [IO.Path]::GetFileName($File), $process.ExitCode) }
        return [pscustomobject]@{ Code=$process.ExitCode; Output=$output.Result; Error=$errorOutput.Result }
    } finally { $process.Dispose() }
}
function Set-PrivateDirectory([string] $Path) {
    [IO.Directory]::CreateDirectory($Path) | Out-Null
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true,$false)
    foreach ($sid in 'S-1-5-18','S-1-5-32-544') {
        $identity = New-Object Security.Principal.SecurityIdentifier($sid)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity,'FullControl','ContainerInherit,ObjectInherit','None','Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
}
function Grant-Path([string] $Path, [string] $Identity, [string] $Rights) {
    Invoke-Native "$env:WINDIR\System32\icacls.exe" @($Path,'/grant:r',($Identity+':'+$Rights)) | Out-Null
}
function Expand-VerifiedZip([string] $Archive, [string] $Target, [string] $StripPrefix='') {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [IO.Directory]::CreateDirectory($Target) | Out-Null
    $prefix = [IO.Path]::GetFullPath($Target).TrimEnd('\') + '\'
    $zip = [IO.Compression.ZipFile]::OpenRead($Archive)
    $seen = New-Object 'Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    try {
        foreach ($entry in $zip.Entries) {
            $name = $entry.FullName
            if ($StripPrefix) { if (!$name.StartsWith($StripPrefix,[StringComparison]::Ordinal)) { throw 'Unexpected archive prefix' }; $name=$name.Substring($StripPrefix.Length) }
            if (!$name) { continue }
            if ($name.Contains('\') -or $name.Contains(':') -or $name.StartsWith('/') -or $name -match '(^|/)(\.\.?|CON(\..*)?|PRN(\..*)?|AUX(\..*)?|NUL(\..*)?|COM[1-9](\..*)?|LPT[1-9](\..*)?)(/|$)' -or $name -match '[. ](/|$)') { throw 'Unsafe archive path' }
            $destination = [IO.Path]::GetFullPath((Join-Path $Target $name))
            if (!$destination.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or !$seen.Add($destination)) { throw 'Invalid or duplicate archive path' }
            # Reparse points are refused, including existing interrupted extraction paths.
            $parent = [IO.Path]::GetDirectoryName($destination)
            for ($p=$parent; $p -and $p.Length -ge $prefix.TrimEnd('\').Length; $p=[IO.Path]::GetDirectoryName($p)) {
                if ((Test-Path -LiteralPath $p) -and ((Get-Item -LiteralPath $p -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Reparse point in extraction path' }
            }
            if ($name.EndsWith('/')) { [IO.Directory]::CreateDirectory($destination) | Out-Null; continue }
            [IO.Directory]::CreateDirectory($parent) | Out-Null
            if ((Test-Path -LiteralPath $destination) -and ((Get-Item -LiteralPath $destination -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Reparse point in extraction target' }
            [IO.Compression.ZipFileExtensions]::ExtractToFile($entry,$destination,$true)
        }
    } finally { $zip.Dispose() }
}
if ($LibraryOnly) { return }

$inputConfig = Get-Content -LiteralPath $Config -Raw -Encoding UTF8 | ConvertFrom-Json
$root = [IO.Path]::GetFullPath($inputConfig.Root).TrimEnd('\')
$private = Join-Path $root 'bootstrap'
if ([IO.Path]::GetFullPath($Config) -ne (Join-Path $private 'input.json')) { throw 'Invalid private configuration path' }
if (!(Test-Path -LiteralPath (Join-Path $root '.bootstrap-owner.json'))) { throw 'Installer ownership marker missing' }
$owner = Get-Content -LiteralPath (Join-Path $root '.bootstrap-owner.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if ($owner.Root -ne $root -or $owner.Domain -ne $inputConfig.Domain -or $owner.Email -ne $inputConfig.Email) { throw 'Installer ownership mismatch' }
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (!$principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Administrator rights required' }
$lock = [IO.File]::Open((Join-Path $private 'provision.lock'),'OpenOrCreate','ReadWrite','None')
$stateFile = Join-Path $private 'state.json'
$state = @{}
if (Test-Path -LiteralPath $stateFile) {
    $loaded = Get-Content -LiteralPath $stateFile -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($property in $loaded.PSObject.Properties) { $state[$property.Name]=$property.Value }
}
function Stage([string] $Name, [scriptblock] $Action) {
    if ($state.ContainsKey($Name) -and $state[$Name] -eq 'complete') { Write-Output ('Etapa já concluída: '+$Name); return }
    $script:currentStage=$Name
    Write-Output ('A executar: '+$Name)
    $state[$Name]='started'
    Write-Utf8 $stateFile ($state | ConvertTo-Json)
    & $Action
    $state[$Name]='complete'
    Write-Utf8 $stateFile ($state | ConvertTo-Json)
}
$app = Join-Path $root 'app'
$phpRoot = Join-Path $root 'runtime\php'
$mysqlRoot = Join-Path $root 'runtime\mysql'
$mysqlData = Join-Path $root 'data\mysql'
$php = Join-Path $phpRoot 'php.exe'
$serviceName = 'AnferaltaDocsMySQL'
$siteName = 'AnferaltaDocs'
try {
    $script:currentStage='certificate'
    if ($state.ContainsKey('complete')) { throw 'Installation already complete' }
    # Certificate validation is repeated on every resume, before provisioning.
    Add-Type -Path (Join-Path $PSScriptRoot 'CertificatePolicy.cs')
    $pfxPassword = [IO.File]::ReadAllText((Join-Path $private 'pfx-password.txt'))
    $certificate = [AnferaltaDocsSetup.CertificatePolicy]::Validate((Join-Path $private 'server.pfx'),$pfxPassword,$inputConfig.Domain)
    $thumbprint = $certificate.Thumbprint; $certificate.Dispose()
    Stage 'downloads' {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $catalog = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'runtimes.json') -Raw | ConvertFrom-Json
        [IO.Directory]::CreateDirectory((Join-Path $root 'downloads')) | Out-Null
        foreach ($item in $catalog.PSObject.Properties) {
            $package=$item.Value; $file=Join-Path $root ('downloads\'+$package.file)
            if (!(Test-Path -LiteralPath $file)) {
                Write-Output ('A descarregar '+$item.Name+' '+$package.version)
                $part=$file+'.part'
                Invoke-WebRequest -Uri $package.url -OutFile $part -UseBasicParsing
                if ((Get-FileHash -LiteralPath $part -Algorithm SHA256).Hash -ne $package.sha256) { throw 'Downloaded runtime hash mismatch' }
                [IO.File]::Move($part,$file)
            }
            if ((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash -ne $package.sha256) { throw 'Cached runtime hash mismatch' }
            if ($package.PSObject.Properties.Name -contains 'signer') {
                $signature=Get-AuthenticodeSignature -LiteralPath $file
                if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch ('O='+[regex]::Escape($package.signer)+'(,|$)')) { throw 'Microsoft signature required' }
            }
        }
    }
    # Reverify cached files even when downloads completed in a previous run.
    $catalog = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'runtimes.json') -Raw | ConvertFrom-Json
    foreach ($item in $catalog.PSObject.Properties) { if ((Get-FileHash -LiteralPath (Join-Path $root ('downloads\'+$item.Value.file)) -Algorithm SHA256).Hash -ne $item.Value.sha256) { throw 'Runtime hash mismatch' } }
    Stage 'iis' {
        if (Get-Command Install-WindowsFeature -ErrorAction SilentlyContinue) {
            $result=Install-WindowsFeature Web-Server,Web-CGI,Web-Static-Content,Web-Http-Errors,Web-Filtering,Web-Mgmt-Console,Web-Scripting-Tools -IncludeManagementTools
            if (!$result.Success) { throw 'IIS role installation failed' }
            $restart=$result.RestartNeeded -eq 'Yes'
        } else {
            $result=Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole,IIS-WebServer,IIS-CommonHttpFeatures,IIS-StaticContent,IIS-HttpErrors,IIS-Security,IIS-RequestFiltering,IIS-ApplicationDevelopment,IIS-CGI,IIS-WebServerManagementTools,IIS-ManagementConsole,IIS-ManagementScriptingTools -All -NoRestart
            $restart=[bool]$result.RestartNeeded
        }
        if ($restart) { $state['iis']='complete'; Write-Utf8 $stateFile ($state|ConvertTo-Json); Write-Output 'É necessário reiniciar o Windows e voltar a executar o instalador na mesma pasta.'; exit 3010 }
        Import-Module WebAdministration
        if (Test-Path 'IIS:\Sites\Default Web Site') { Stop-WebSite 'Default Web Site'; Set-ItemProperty 'IIS:\Sites\Default Web Site' -Name serverAutoStart -Value $false }
    }
    Stage 'vc-runtime' {
        $result=Invoke-Native (Join-Path $root 'downloads\vc_redist.x64.exe') @('/install','/quiet','/norestart') @(0,1638,3010)
        if ($result.Code -eq 3010) { $state['vc-runtime']='complete'; Write-Utf8 $stateFile ($state|ConvertTo-Json); Write-Output 'Reinicie o Windows e retome o instalador.'; exit 3010 }
    }
    Stage 'rewrite' {
        $result=Invoke-Native "$env:WINDIR\System32\msiexec.exe" @('/i',(Join-Path $root 'downloads\rewrite.msi'),'/qn','/norestart') @(0,3010)
        if ($result.Code -eq 3010) { $state['rewrite']='complete'; Write-Utf8 $stateFile ($state|ConvertTo-Json); Write-Output 'Reinicie o Windows e retome o instalador.'; exit 3010 }
    }
    Stage 'php' {
        Expand-VerifiedZip (Join-Path $root 'downloads\php.zip') $phpRoot
        [IO.Directory]::CreateDirectory((Join-Path $root 'data\php-temp')) | Out-Null
        $ca=''
        foreach ($cert in Get-ChildItem Cert:\LocalMachine\Root) { $ca += "-----BEGIN CERTIFICATE-----`r`n"+[Convert]::ToBase64String($cert.RawData,[Base64FormattingOptions]::InsertLineBreaks)+"`r`n-----END CERTIFICATE-----`r`n" }
        Write-Utf8 (Join-Path $phpRoot 'cacert.pem') $ca
        $ini=@"
extension_dir="$phpRoot\ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=zip
date.timezone=Europe/Lisbon
expose_php=Off
display_errors=Off
log_errors=On
zend.exception_ignore_args=On
error_log="$root\data\php-temp\php-error.log"
memory_limit=512M
upload_max_filesize=50M
post_max_size=100M
max_execution_time=120
cgi.force_redirect=0
cgi.fix_pathinfo=1
fastcgi.impersonate=1
session.use_strict_mode=1
session.cookie_httponly=1
session.cookie_secure=1
session.cookie_samesite=Lax
session.save_path="$app\storage\sessions"
upload_tmp_dir="$root\data\php-temp"
sys_temp_dir="$root\data\php-temp"
openssl.cafile="$phpRoot\cacert.pem"
curl.cainfo="$phpRoot\cacert.pem"
"@
        Write-Utf8 (Join-Path $phpRoot 'php.ini') $ini
        Invoke-Native $php @((Join-Path $app 'install.php'),'--check') | Out-Null
    }
    Stage 'mysql-files' { Expand-VerifiedZip (Join-Path $root 'downloads\mysql.zip') $mysqlRoot 'mysql-8.4.13-winx64/' }
    $mysql=Join-Path $mysqlRoot 'bin\mysqld.exe'
    $mysqlIni=Join-Path $root 'runtime\mysql.ini'
    Stage 'mysql-initialize' {
        foreach ($name in 'root','maintenance','application') {
            $file=Join-Path $private ('mysql-'+$name+'.key')
            if (!(Test-Path -LiteralPath $file)) {
                $bytes=New-Object byte[] 32; $rng=[Security.Cryptography.RandomNumberGenerator]::Create(); try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
                Write-Utf8 $file ([BitConverter]::ToString($bytes).Replace('-','').ToLowerInvariant())
            }
        }
        if ((Test-Path -LiteralPath $mysqlData) -and (Get-ChildItem -LiteralPath $mysqlData -Force | Select-Object -First 1)) { throw 'Non-empty MySQL data directory; do not reinitialize. Manual recovery required.' }
        Set-PrivateDirectory $mysqlData
        Write-Utf8 $mysqlIni (@"
[mysqld]
basedir=$($mysqlRoot.Replace('\','/'))
datadir=$($mysqlData.Replace('\','/'))
port=3306
bind-address=127.0.0.1
mysqlx=OFF
local-infile=OFF
secure-file-priv=NULL
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
log-error=$($mysqlData.Replace('\','/'))/mysql-error.log
"@)
        Invoke-Native $mysql @(('--defaults-file='+$mysqlIni),'--initialize-insecure') | Out-Null
        Write-Utf8 (Join-Path $private 'mysql-initialized') 'Owned initialized MySQL data directory.'
    }
    Stage 'mysql-service' {
        $existing=Get-Service -Name $serviceName -ErrorAction SilentlyContinue
        if (!$existing) { Invoke-Native $mysql @('--install',$serviceName,('--defaults-file='+$mysqlIni)) | Out-Null }
        # Validate the recorded service binary before changing its account or starting it.
        $serviceRegistry=Get-ItemProperty -LiteralPath ('HKLM:\SYSTEM\CurrentControlSet\Services\'+$serviceName)
        if (!$serviceRegistry.ImagePath.Contains($mysql) -or !$serviceRegistry.ImagePath.Contains($mysqlIni)) { throw 'Unexpected MySQL service registration' }
        Invoke-Native "$env:WINDIR\System32\sc.exe" @('config',$serviceName,'obj=','NT AUTHORITY\LocalService','start=','auto') | Out-Null
        Invoke-Native "$env:WINDIR\System32\sc.exe" @('sidtype',$serviceName,'unrestricted') | Out-Null
        Grant-Path $root ('NT SERVICE\'+$serviceName) '(RX)'
        Grant-Path (Join-Path $root 'runtime') ('NT SERVICE\'+$serviceName) '(RX)'
        Grant-Path $mysqlRoot ('NT SERVICE\'+$serviceName) '(OI)(CI)(RX)'
        Grant-Path $mysqlIni ('NT SERVICE\'+$serviceName) '(R)'
        Grant-Path (Join-Path $root 'data') ('NT SERVICE\'+$serviceName) '(RX)'
        Grant-Path $mysqlData ('NT SERVICE\'+$serviceName) '(OI)(CI)(M)'
        Start-Service -Name $serviceName
        (Get-Service $serviceName).WaitForStatus('Running',[TimeSpan]::FromSeconds(60))
    }
    if ((Get-Service $serviceName).Status -ne 'Running') { Start-Service $serviceName }
    $dbConfig=Join-Path $private 'database.json'
    Write-Utf8 $dbConfig (@{private=$private;data=$mysqlData;port=3306}|ConvertTo-Json)
    Stage 'database-accounts' {
        # Wait for the SQL listener; a Windows Running state alone is insufficient.
        $ready=$false
        for ($attempt=0;$attempt -lt 60;$attempt++) {
            $client=New-Object Net.Sockets.TcpClient
            try { $client.Connect('127.0.0.1',3306); $ready=$true } catch { Start-Sleep -Milliseconds 500 } finally { $client.Dispose() }
            if ($ready) { break }
        }
        if (!$ready) { throw 'MySQL did not start listening' }
        Invoke-Native $php @((Join-Path $PSScriptRoot 'bootstrap-database.php'),$dbConfig) | Out-Null
    }
    $installConfig=Join-Path $private 'install.json'
    Write-Utf8 $installConfig (@{APP_URL=('https://'+$inputConfig.Domain);DB_HOST='127.0.0.1';DB_PORT=3306;DB_NAME='anferaltadocs';DB_USER='anferalta_maintenance';DB_PASS_FILE=(Join-Path $private 'mysql-maintenance.key');ADMIN_EMAIL=$inputConfig.Email;ADMIN_NAME=$inputConfig.Administrator;ADMIN_PASSWORD_FILE=(Join-Path $private 'admin-password.txt')}|ConvertTo-Json)
    Stage 'application' {
        # If PHP completed but the outer stage was interrupted, verify its own ledger.
        $ledger=Join-Path $app 'storage\installation-state.json'
        $phpCompleted=(Test-Path -LiteralPath $ledger) -and ((Get-Content -LiteralPath $ledger -Raw|ConvertFrom-Json).phase -eq 'complete') -and !(Test-Path -LiteralPath (Join-Path $app 'storage\.installation-incomplete'))
        if (!$phpCompleted) {
            $arguments=@((Join-Path $app 'install.php'),('--config='+$installConfig),'--yes')
            if (Test-Path -LiteralPath (Join-Path $app 'storage\.installation-incomplete')) { $arguments+='--resume' }
            Invoke-Native $php $arguments | Out-Null
        }
    }
    Stage 'application-account' {
        $envFile=Join-Path $app '.env'
        $content=[IO.File]::ReadAllText($envFile)
        if ($content -notmatch '(?m)^DB_USER="anferalta_(maintenance|app)"\r?$') { throw 'Unexpected application database account' }
        $content=[regex]::Replace($content,'(?m)^DB_USER="anferalta_(maintenance|app)"\r?$','DB_USER="anferalta_app"')
        Write-Utf8 (Join-Path $app 'secrets\database-password.txt') ([IO.File]::ReadAllText((Join-Path $private 'mysql-application.key')))
        Write-Utf8 $envFile $content
    }
    Stage 'iis-site' {
        Import-Module WebAdministration
        $existingSite=Test-Path ('IIS:\Sites\'+$siteName)
        if ($existingSite) {
            $site=Get-Item ('IIS:\Sites\'+$siteName)
            if ($site.physicalPath -ne (Join-Path $app 'public') -or $site.applicationPool -ne $siteName) { throw 'Unexpected existing IIS site' }
            $bindings=@(Get-WebBinding -Name $siteName)
            if ($bindings.Count -ne 1 -or $bindings[0].protocol -ne 'https' -or $bindings[0].bindingInformation -ne ('*:443:'+$inputConfig.Domain)) { throw 'Unexpected IIS bindings' }
            Stop-WebSite $siteName
        }
        # No previous IIS installation was accepted by clean-machine preflight.
        if (Test-Path 'IIS:\Sites\Default Web Site') { Stop-WebSite 'Default Web Site' }
        if (!(Test-Path ('IIS:\AppPools\'+$siteName))) { New-WebAppPool -Name $siteName | Out-Null }
        Set-ItemProperty ('IIS:\AppPools\'+$siteName) -Name managedRuntimeVersion -Value ''
        Set-ItemProperty ('IIS:\AppPools\'+$siteName) -Name processModel.identityType -Value ApplicationPoolIdentity
        $cgi=Join-Path $phpRoot 'php-cgi.exe'
        $fastCgi=@(Get-WebConfiguration -PSPath 'MACHINE/WEBROOT/APPHOST' -Filter 'system.webServer/fastCgi/application' | Where-Object { $_.fullPath -eq $cgi })
        if (!$fastCgi.Count) { Add-WebConfigurationProperty -PSPath 'MACHINE/WEBROOT/APPHOST' -Filter 'system.webServer/fastCgi' -Name '.' -Value @{fullPath=$cgi;instanceMaxRequests=10000;requestTimeout=150;activityTimeout=150} }
        $handler=[Security.SecurityElement]::Escape($cgi)
        Write-Utf8 (Join-Path $app 'public\web.config') (@"
<?xml version="1.0" encoding="utf-8"?>
<configuration><system.webServer>
<directoryBrowse enabled="false" />
<handlers><clear /><add name="AnferaltaFrontController" path="index.php" verb="GET,HEAD,POST" modules="FastCgiModule" scriptProcessor="$handler" resourceType="File" requireAccess="Script" /><add name="StaticFile" path="*" verb="GET,HEAD" modules="StaticFileModule" resourceType="File" requireAccess="Read" /></handlers>
<rewrite><rules>
<rule name="Block private paths" stopProcessing="true"><match url="(^|/)(\.|vendor(/|$)|App(/|$)|secrets(/|$)|storage(/|$)|backups(/|$)|uploads(/|$))|\.php(/|$)" ignoreCase="true" /><conditions><add input="{URL}" pattern="^/index\.php$" negate="true" /></conditions><action type="CustomResponse" statusCode="404" statusReason="Not Found" statusDescription="Not Found" /></rule>
<rule name="Front controller" stopProcessing="true"><match url=".*" /><conditions><add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" /></conditions><action type="Rewrite" url="index.php" /></rule>
</rules></rewrite>
<httpErrors errorMode="Custom" existingResponse="PassThrough" />
<security><requestFiltering><requestLimits maxAllowedContentLength="104857600" /></requestFiltering></security>
</system.webServer></configuration>
"@)
        $secure=ConvertTo-SecureString -String $pfxPassword -AsPlainText -Force
        Import-PfxCertificate -FilePath (Join-Path $private 'server.pfx') -CertStoreLocation Cert:\LocalMachine\My -Password $secure | Out-Null
        $serverCert=Get-Item ('Cert:\LocalMachine\My\'+$thumbprint)
        if (!$serverCert.HasPrivateKey) { throw 'Imported certificate has no private key' }
        if (!$existingSite) { New-Website -Name $siteName -PhysicalPath (Join-Path $app 'public') -ApplicationPool $siteName -IPAddress '*' -Port 443 -HostHeader $inputConfig.Domain -Ssl -SslFlags 1 | Out-Null }
        Stop-WebSite $siteName
        Set-WebConfigurationProperty -PSPath 'MACHINE/WEBROOT/APPHOST' -Location $siteName -Filter 'system.webServer/security/authentication/anonymousAuthentication' -Name userName -Value ''
        Invoke-Native "$env:WINDIR\System32\inetsrv\appcmd.exe" @('unlock','config',$siteName,'/section:system.webServer/handlers') | Out-Null
        (Get-WebBinding -Name $siteName -Protocol https).AddSslCertificate($thumbprint,'My')
    }
    Stage 'permissions' {
        $web='IIS AppPool\'+$siteName
        Grant-Path $root $web '(RX)'
        Grant-Path $app $web '(OI)(CI)(RX)'
        Grant-Path (Join-Path $root 'runtime') $web '(RX)'
        Grant-Path $phpRoot $web '(OI)(CI)(RX)'
        Grant-Path (Join-Path $root 'data') $web '(RX)'
        Grant-Path (Join-Path $root 'data\php-temp') $web '(OI)(CI)(M)'
        Grant-Path (Join-Path $app 'storage') $web '(OI)(CI)(M)'
        Set-PrivateDirectory (Join-Path $app 'secrets')
        # Reset inherited read ACEs on secret files before granting only the runtime credential.
        Invoke-Native "$env:WINDIR\System32\icacls.exe" @((Join-Path $app 'secrets'),'/reset','/T','/Q') | Out-Null
        Set-PrivateDirectory (Join-Path $app 'secrets')
        Grant-Path (Join-Path $app 'secrets') $web '(RX)'
        Grant-Path (Join-Path $app 'secrets\database-password.txt') $web '(R)'
        # Root/maintenance credentials and recovery keys remain Administrator/SYSTEM only.
        Set-PrivateDirectory (Join-Path $app 'backups')
    }
    Stage 'validation' {
        Import-Module WebAdministration
        Start-WebSite $siteName
        # Test the actual local listener using domain SNI + Windows trust, without changing DNS/hosts.
        $client=New-Object Net.Sockets.TcpClient('127.0.0.1',443)
        try {
            $ssl=New-Object Net.Security.SslStream($client.GetStream(),$false)
            try {
                $ssl.ReadTimeout=30000; $ssl.WriteTimeout=30000
                $ssl.AuthenticateAsClient($inputConfig.Domain)
                $request="GET /login HTTP/1.1`r`nHost: $($inputConfig.Domain)`r`nConnection: close`r`n`r`n"
                $bytes=[Text.Encoding]::ASCII.GetBytes($request); $ssl.Write($bytes,0,$bytes.Length)
                $reader=New-Object IO.StreamReader($ssl)
                $response=$reader.ReadToEnd()
                if ($response -notmatch '^HTTP/1\.[01] 200 ' -or $response -notmatch '(?i)<form') { throw 'HTTPS login route validation failed' }
            } finally { $ssl.Dispose() }
        } finally { $client.Dispose() }
        Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'status') | Out-Null
    }
    Stage 'initial-backup' {
        $result=Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'backup') @(0) @{DB_USER='anferalta_maintenance';DB_PASS_FILE=(Join-Path $private 'mysql-maintenance.key')}
        $backup=$result.Output|ConvertFrom-Json
        # CLI verifies its completed encrypted archive again with the actual recovery key.
        $archive=Get-ChildItem -LiteralPath (Join-Path $app 'backups\Completos') -Filter '*.zip' | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1
        if (!$archive) { throw 'Initial backup not found' }
        Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'verify',('--archive='+$archive.FullName)) | Out-Null
    }
    Stage 'firewall' {
        $existing=Get-NetFirewallRule -Name 'AnferaltaDocs-HTTPS-LAN' -ErrorAction SilentlyContinue
        if ($existing) {
            $address=$existing|Get-NetFirewallAddressFilter
            $port=$existing|Get-NetFirewallPortFilter
            if ($existing.Direction -ne 'Inbound' -or $existing.Action -ne 'Allow' -or $existing.Profile.ToString() -ne 'Domain, Private' -or $port.Protocol -ne 'TCP' -or $port.LocalPort -ne '443' -or @(Compare-Object @($address.RemoteAddress|Sort-Object) @($inputConfig.Networks -split ','|Sort-Object)).Count) { throw 'Existing firewall rule differs from setup intent' }
        }
        Get-NetFirewallRule -Name 'IIS-WebServerRole-HTTP-In-TCP','IIS-WebServerRole-HTTPS-In-TCP' -ErrorAction SilentlyContinue | Disable-NetFirewallRule
        if (!$existing) { New-NetFirewallRule -Name 'AnferaltaDocs-HTTPS-LAN' -DisplayName 'AnferaltaDocs HTTPS - rede empresarial' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 443 -Profile Domain,Private -RemoteAddress ($inputConfig.Networks -split ',') | Out-Null }
    }
    $state['complete']='complete'; Write-Utf8 $stateFile ($state|ConvertTo-Json)
    # Delete only redundant setup inputs after successful validation. Persistent DB keys remain private.
    foreach ($name in 'server.pfx','pfx-password.txt','admin-password.txt','install.json','database.json') {
        $file=Join-Path $private $name
        if (Test-Path -LiteralPath $file) { try { Remove-Item -LiteralPath $file -Force } catch { Write-Output ('Não foi possível remover a entrada privada '+$name+'. Remova-a manualmente após confirmar a instalação.') } }
    }
    Write-Output ('Instalação concluída: https://'+$inputConfig.Domain+'/login')
    Write-Output 'Configure o DNS da empresa para este servidor e valide o acesso a partir de outra máquina. Guarde a chave de recuperação fora do servidor.'
} catch {
    # Detailed exceptions may include paths/configuration; keep diagnostics in the private folder.
    Write-Utf8 (Join-Path $private 'last-error.txt') ($_ | Out-String)
    Write-Output ('A instalação parou na etapa '+$script:currentStage+'. Consulte bootstrap\last-error.txt no servidor. Não foram apagados dados.')
    exit 1
} finally { $lock.Dispose() }
