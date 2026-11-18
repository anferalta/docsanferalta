# HTTP smoke check of an existing, successful isolated bootstrap fixture.
[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$Fixture)
$ErrorActionPreference='Stop'
$root=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$fixturePath=[IO.Path]::GetFullPath($Fixture)
$allowed=[IO.Path]::GetFullPath((Join-Path $root 'dist')).TrimEnd('\')+'\bootstrap-isolated-'
if (!$fixturePath.StartsWith($allowed,[StringComparison]::OrdinalIgnoreCase)) {throw 'Expected isolated fixture under dist'}
$report=Get-Content -LiteralPath (Join-Path $fixturePath 'test-report.json') -Raw | ConvertFrom-Json
if (@($report.checks | Where-Object {!$_.passed}).Count -gt 0) {throw 'Bootstrap must pass first'}
$port=[int]$report.port
$db=$null; $web=$null
$checks=New-Object 'Collections.Generic.List[object]'
function Wait-Port([int]$Port) {
 for($i=0;$i -lt 100;$i++){
  $client=New-Object Net.Sockets.TcpClient
  try{$client.Connect('127.0.0.1',$Port);return} catch {Start-Sleep -Milliseconds 200} finally{$client.Dispose()}
 }
 throw 'Temporary server did not start'
}
function Is-Free([int]$Port){
 $client=New-Object Net.Sockets.TcpClient
 try{$client.Connect('127.0.0.1',$Port);return $false}catch{return $true}finally{$client.Dispose()}
}
try {
 if (!(Is-Free $port)) {throw 'Fixture database port occupied; no process will be stopped'}
 $listener=New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback,0)
 $listener.Start(); $webPort=$listener.LocalEndpoint.Port; $listener.Stop()
 $mysql=Join-Path $fixturePath 'mysql/bin/mysqld.exe'
 $db=Start-Process -FilePath $mysql -ArgumentList ('"--defaults-file='+(Join-Path $fixturePath 'mysql.ini')+'" --console --no-monitor') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $fixturePath 'web-mysql-out.log') -RedirectStandardError (Join-Path $fixturePath 'web-mysql-error.log')
 Wait-Port $port
 $app=Join-Path $fixturePath 'app'
 $router=Join-Path $fixturePath 'web-router.php'
 [IO.File]::WriteAllText($router,"<?php require __DIR__ . '/app/public/index.php';",(New-Object Text.UTF8Encoding($false)))
 $php=Join-Path $fixturePath 'php/php.exe'
 $arguments='-c "'+(Join-Path $fixturePath 'php/php.ini')+'" -S 127.0.0.1:'+$webPort+' -t "'+(Join-Path $app 'public')+'" "'+$router+'"'
 $web=Start-Process -FilePath $php -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $fixturePath 'web-out.log') -RedirectStandardError (Join-Path $fixturePath 'web-error.log')
 Wait-Port $webPort
 foreach($path in '/login','/recuperar','/registar'){
  $response=Invoke-WebRequest -Uri ('http://127.0.0.1:'+$webPort+$path) -UseBasicParsing
  $ok=$response.StatusCode -eq 200 -and $response.Content.Contains('name="_csrf"')
  $checks.Add(@{path=$path;passed=$ok;status=[int]$response.StatusCode})
  if(!$ok){throw ('Unexpected form response: '+$path)}
 }
 Write-Output 'HTTP forms: login, recovery and registration passed; no form submitted or email sent.'
} finally {
 foreach($process in @($web,$db)) {if($process -and !$process.HasExited){$process.Kill();$process.WaitForExit()}}
 [IO.File]::WriteAllText((Join-Path $fixturePath 'web-test-report.json'),(@{checks=$checks.ToArray();services_modified=$false;emails_sent=0}|ConvertTo-Json -Depth 4),(New-Object Text.UTF8Encoding($false)))
}
