# Development integration test. Uses only a new dist directory and a free loopback port.
[CmdletBinding()]
param()
$ErrorActionPreference='Stop'
$root=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$testRoot=Join-Path $root ('dist\bootstrap-isolated-'+[Guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($testRoot)|Out-Null
$sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
& "$env:WINDIR\System32\icacls.exe" $testRoot /inheritance:r /grant:r ('*'+$sid+':(OI)(CI)F') '*S-1-5-18:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE) { throw 'Cannot secure isolated fixture' }
. (Join-Path $PSScriptRoot 'Provision.ps1') -Config 'unused' -LibraryOnly
$checks=New-Object 'Collections.Generic.List[object]'
function Verify([bool]$Value,[string]$Name) { $checks.Add(@{name=$Name;passed=$Value}); if (!$Value) { throw ('Test failed: '+$Name) } }
$mysqlProcess=$null
$reservation=New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback,0)
$reservation.Start(); $port=$reservation.LocalEndpoint.Port; $reservation.Stop()
try {
    $catalog=Get-Content -LiteralPath (Join-Path $PSScriptRoot 'runtimes.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach($item in $catalog.PSObject.Properties) {
        Verify ((Get-FileHash -LiteralPath (Join-Path $root ('dist\runtime-cache\'+$item.Value.file)) -Algorithm SHA256).Hash -eq $item.Value.sha256) ('Runtime hash: '+$item.Name)
    }
    $probe=Join-Path $testRoot 'arguments.py'
    Write-Utf8 $probe "import json,sys,os; print(json.dumps({'args':sys.argv[1:], 'db':os.getenv('DB_PASS'), 'user':os.getenv('DB_USER')}))"
    $python=(Get-Command python.exe).Source
    $cases=@('plain','with spaces','quoted"value','C:\trailing space\','dollar$()&;literal','unicode-á')
    $env:DB_PASS='fixture-inherited-secret'
    try { $probeResult=Invoke-Native $python (@($probe)+$cases) @(0) @{DB_USER='fixture-user'} } finally { Remove-Item Env:\DB_PASS }
    $decoded=$probeResult.Output|ConvertFrom-Json
    Verify (@(Compare-Object $decoded.args $cases -SyncWindow 0).Count -eq 0) 'Native argument escaping preserves literals'
    Verify ($null -eq $decoded.db -and $decoded.user -eq 'fixture-user') 'Inherited secrets removed; explicit maintenance environment applied'
    $bad=Join-Path $testRoot 'unsafe.zip'
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip=[IO.Compression.ZipFile]::Open($bad,'Create')
    try { $entry=$zip.CreateEntry('../escape.txt'); $writer=New-Object IO.StreamWriter($entry.Open()); $writer.Write('unsafe'); $writer.Dispose() } finally { $zip.Dispose() }
    $refused=$false
    try { Expand-VerifiedZip $bad (Join-Path $testRoot 'unsafe-target') } catch { $refused=$true }
    Verify ($refused -and !(Test-Path -LiteralPath (Join-Path $testRoot 'escape.txt'))) 'ZIP traversal rejected before writing outside target'
    $phpRoot=Join-Path $testRoot 'php'; $mysqlRoot=Join-Path $testRoot 'mysql'
    Write-Output 'Extracting verified runtime archives into isolated fixture...'
    Expand-VerifiedZip (Join-Path $root 'dist\runtime-cache\php.zip') $phpRoot
    Expand-VerifiedZip (Join-Path $root 'dist\runtime-cache\mysql.zip') $mysqlRoot 'mysql-8.4.13-winx64/'
    Write-Utf8 (Join-Path $phpRoot 'php.ini') ('extension_dir="'+$phpRoot+'\ext"'+"`nextension=pdo_mysql`nextension=openssl`nextension=zip`nextension=gd`nextension=fileinfo`nextension=mbstring`ndate.timezone=Europe/Lisbon`n")
    $php=Join-Path $phpRoot 'php.exe'
    $result=Invoke-Native $php @('-r','echo PHP_VERSION;')
    Verify ($result.Output.Trim() -eq '8.4.27') 'Downloaded PHP executes in isolation'
    $mysql=Join-Path $mysqlRoot 'bin\mysqld.exe'; $data=Join-Path $testRoot 'data'
    $ini=Join-Path $testRoot 'mysql.ini'
    Write-Utf8 $ini ("[mysqld]`nbasedir="+$mysqlRoot.Replace('\','/')+"`ndatadir="+$data.Replace('\','/')+"`nport="+$port+"`nbind-address=127.0.0.1`nmysqlx=OFF`n")
    # Refuse conflict even with an unrelated process. Never stop any existing listener.
    $probeClient=New-Object Net.Sockets.TcpClient
    $occupied=$false
    try { $probeClient.Connect('127.0.0.1',$port); $occupied=$true } catch {} finally { $probeClient.Dispose() }
    if ($occupied) { throw 'Isolated test port is already occupied' }
    Invoke-Native $mysql @(('--defaults-file='+$ini),'--initialize-insecure') | Out-Null
    Write-Output ('Starting temporary MySQL on loopback port '+$port+'...')
    $start=New-Object Diagnostics.ProcessStartInfo
    $start.FileName=$mysql; $start.Arguments='"--defaults-file='+$ini+'" --console --no-monitor'; $start.UseShellExecute=$false; $start.CreateNoWindow=$true
    $start.RedirectStandardOutput=$true; $start.RedirectStandardError=$true
    $mysqlProcess=[Diagnostics.Process]::Start($start)
    $mysqlOut=$mysqlProcess.StandardOutput.ReadToEndAsync(); $mysqlErr=$mysqlProcess.StandardError.ReadToEndAsync()
    $ready=$false
    for($i=0;$i -lt 100;$i++) {
        $client=New-Object Net.Sockets.TcpClient
        try { $client.Connect('127.0.0.1',$port); $ready=$true } catch { Start-Sleep -Milliseconds 300 } finally { $client.Dispose() }
        if ($ready) { break }; if ($mysqlProcess.HasExited) { throw 'Isolated MySQL exited' }
    }
    Verify $ready 'Downloaded MySQL listens on isolated loopback port'
    foreach($name in 'root','maintenance','application') { $bytes=New-Object byte[] 32; $rng=[Security.Cryptography.RandomNumberGenerator]::Create(); try{$rng.GetBytes($bytes)}finally{$rng.Dispose()}; Write-Utf8 (Join-Path $testRoot ('mysql-'+$name+'.key')) ([BitConverter]::ToString($bytes).Replace('-','').ToLowerInvariant()) }
    Write-Utf8 (Join-Path $testRoot 'mysql-initialized') 'fixture'
    $config=Join-Path $testRoot 'database.json'; Write-Utf8 $config (@{private=$testRoot;data=$data;port=$port}|ConvertTo-Json)
    Invoke-Native $php @((Join-Path $PSScriptRoot 'bootstrap-database.php'),$config)|Out-Null
    Verify $true 'Fresh root credential and scoped accounts configured'
    Invoke-Native $php @((Join-Path $PSScriptRoot 'bootstrap-database.php'),$config)|Out-Null
    Verify $true 'Database account provisioning resumes with protected root credential'
    $verifyScript=Join-Path $testRoot 'verify.php'
    Write-Utf8 $verifyScript @'
<?php
$root=$argv[1];
$port=(int)$argv[2];
$connect=fn($user,$file)=>new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=anferaltadocs;charset=utf8mb4',$user,file_get_contents($root.'/'.$file),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$maintenance=$connect('anferalta_maintenance','mysql-maintenance.key');
$maintenance->exec('CREATE TABLE fixture(id INT PRIMARY KEY, value VARCHAR(20)) ENGINE=InnoDB');
$app=$connect('anferalta_app','mysql-application.key');
$app->exec("INSERT INTO fixture VALUES(1,'test')");
if($app->query('SELECT value FROM fixture')->fetchColumn()!=='test')exit(1);
$blocked=0;
foreach(['CREATE TABLE forbidden(id INT)','SELECT User FROM mysql.user','LOCK TABLES fixture READ'] as $sql){try{$app->exec($sql);}catch(PDOException $e){$blocked++;}}
if($blocked!==3)exit(2);
$maintenance->exec('LOCK TABLES fixture READ');$maintenance->exec('UNLOCK TABLES');
$maintenance->exec('DROP TABLE fixture');
echo 'scoped privileges verified';
'@
    $result=Invoke-Native $php @($verifyScript,$testRoot,[string]$port)
    Verify ($result.Output -eq 'scoped privileges verified') 'Application CRUD works; DDL/global access/backup locks are maintenance-only'
    $wrongConfig=Join-Path $testRoot 'wrong-database.json'; Write-Utf8 $wrongConfig (@{private=$testRoot;data=(Join-Path $testRoot 'wrong-data');port=$port}|ConvertTo-Json)
    $refused=$false
    try{Invoke-Native $php @((Join-Path $PSScriptRoot 'bootstrap-database.php'),$wrongConfig)|Out-Null}catch{$refused=$true}
    Verify $refused 'Unexpected MySQL data directory rejected'
    Write-Output 'Testing application installation, account switch and encrypted recovery archive...'
    $app=Join-Path $testRoot 'app'
    Expand-VerifiedZip (Join-Path $root 'dist\windows-payload.zip') $app
    $install=Join-Path $testRoot 'install.json'
    Write-Utf8 (Join-Path $testRoot 'admin-password.txt') ('Fixture-'+[Guid]::NewGuid().ToString('N'))
    Write-Utf8 $install (@{APP_URL='https://documents.fixture.invalid';DB_HOST='127.0.0.1';DB_PORT=$port;DB_NAME='anferaltadocs';DB_USER='anferalta_maintenance';DB_PASS_FILE=(Join-Path $testRoot 'mysql-maintenance.key');ADMIN_EMAIL='admin@fixture.invalid';ADMIN_NAME='Fixture';ADMIN_PASSWORD_FILE=(Join-Path $testRoot 'admin-password.txt')}|ConvertTo-Json)
    Invoke-Native $php @((Join-Path $app 'install.php'),'--check')|Out-Null
    Verify $true 'Bundled application requirements pass with downloaded PHP'
    Invoke-Native $php @((Join-Path $app 'install.php'),('--config='+$install),'--plan')|Out-Null
    Invoke-Native $php @((Join-Path $app 'install.php'),('--config='+$install),'--yes')|Out-Null
    Verify ((Test-Path -LiteralPath (Join-Path $app '.env')) -and !(Test-Path -LiteralPath (Join-Path $app 'storage\.installation-incomplete'))) 'Initial schema, migrations and administrator installed on MySQL LTS'
    $envFile=Join-Path $app '.env'
    Write-Utf8 $envFile ([IO.File]::ReadAllText($envFile).Replace('DB_USER="anferalta_maintenance"','DB_USER="anferalta_app"'))
    Write-Utf8 (Join-Path $app 'secrets\database-password.txt') ([IO.File]::ReadAllText((Join-Path $testRoot 'mysql-application.key')))
    Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'status')|Out-Null
    Verify $true 'Application connects with restricted runtime account'
    Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'backup') @(0) @{DB_USER='anferalta_maintenance';DB_PASS_FILE=(Join-Path $testRoot 'mysql-maintenance.key')}|Out-Null
    $archive=Get-ChildItem -LiteralPath (Join-Path $app 'backups\Completos') -Filter '*.zip'|Select-Object -First 1
    Verify ([bool]$archive) 'Complete encrypted backup created with maintenance account'
    Invoke-Native $php @((Join-Path $app 'cli\maintenance.php'),'verify',('--archive='+$archive.FullName))|Out-Null
    Verify $true 'Encrypted backup validates independently after runtime account switch'
} catch {
    $checks.Add(@{name=$_.Exception.Message;passed=$false})
    throw
} finally {
    if ($mysqlProcess -and !$mysqlProcess.HasExited) { $mysqlProcess.Kill(); $mysqlProcess.WaitForExit() }
    Write-Utf8 (Join-Path $testRoot 'test-report.json') (@{checks=$checks.ToArray();fixture=$testRoot;port=$port;production_services_modified=$false}|ConvertTo-Json -Depth 5)
    Write-Output ('Isolated test report: '+(Join-Path $testRoot 'test-report.json'))
}
