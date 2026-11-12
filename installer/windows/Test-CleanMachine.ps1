# Read-only preflight for the future clean-machine installer. Never provisions services.
[CmdletBinding()]
param([string] $Destination = 'C:\AnferaltaDocs', [switch] $Resume)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object Text.UTF8Encoding($false)
$checks = [System.Collections.Generic.List[object]]::new()
function Add-Check([string] $Name, [bool] $Passed, [string] $Detail) {
    $checks.Add([pscustomobject]@{ name = $Name; passed = $Passed; detail = $Detail })
}
if ($env:OS -ne 'Windows_NT') { throw 'Este diagnóstico requer Windows.' }
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
Add-Check 'administrator' ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) 'A instalação de serviços e funções Windows exige elevação.'
Add-Check 'architecture' ([Environment]::Is64BitOperatingSystem -and [Environment]::Is64BitProcess) 'Executar em Windows x64, através de PowerShell x64.'
$os = Get-ItemProperty -LiteralPath 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion'
$build = [int] $os.CurrentBuildNumber
$server = $os.InstallationType -like 'Server*'
$supported = ($server -and $os.InstallationType -eq 'Server' -and $build -ge 17763) -or (!$server -and $build -ge 22000 -and $os.EditionID -match '^(Professional|Enterprise|Education)')
Add-Check 'windows' $supported ('{0}; build {1}; {2}' -f $os.ProductName, $build, $os.EditionID)
try {
    $resolved = [IO.Path]::GetFullPath($Destination)
    $root = [IO.Path]::GetPathRoot($resolved)
    $local = $resolved -match '^[A-Za-z]:\\' -and $resolved.TrimEnd('\') -ne $root.TrimEnd('\')
    $empty = !(Test-Path -LiteralPath $resolved) -or ((Test-Path -LiteralPath $resolved -PathType Container) -and !(Get-ChildItem -LiteralPath $resolved -Force | Select-Object -First 1))
    $owned = $Resume -and (Test-Path -LiteralPath (Join-Path $resolved '.bootstrap-owner.json')) -and (Test-Path -LiteralPath (Join-Path $resolved 'bootstrap') -PathType Container)
    Add-Check 'destination' ($local -and ($empty -or $owned)) 'É necessária uma pasta nova e vazia ou uma preparação registada pelo próprio assistente.'
    $drive = [IO.DriveInfo]::new($root)
    Add-Check 'filesystem' ($drive.IsReady -and $drive.DriveFormat -eq 'NTFS') 'NTFS é necessário para as permissões privadas.'
    $minimum = if ($Resume) { 1GB } else { 10GB }
    Add-Check 'free_space' ($drive.AvailableFreeSpace -ge $minimum) 'Reservar pelo menos 10 GiB inicialmente; a retoma requer pelo menos 1 GiB livre.'
} catch {
    Add-Check 'destination' $false 'Não foi possível verificar o destino e o volume; nenhuma pasta foi criada.'
}
$pending = (Test-Path -LiteralPath 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Component Based Servicing\RebootPending') -or (Test-Path -LiteralPath 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\RebootRequired')
$session = Get-ItemProperty -LiteralPath 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager'
$pending = $pending -or [bool] $session.PendingFileRenameOperations
Add-Check 'reboot' (!$pending) 'Reinícios pendentes devem ser concluídos antes de preparar os serviços.'
if (!$Resume) { try {
    $listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in 80,443,3306 })
    foreach ($port in 80,443,3306) {
        Add-Check ('port_' + $port) (!($listeners | Where-Object LocalPort -eq $port)) ('Porta TCP {0} livre; serviços existentes não serão parados.' -f $port)
    }
} catch { Add-Check 'ports' $false 'Não foi possível verificar as portas; a instalação deve parar antes de modificar o Windows.' }
try {
    $conflicts = @(Get-Service -ErrorAction Stop | Where-Object { $_.Name -match '^(W3SVC|WAS|AnferaltaDocs.*|MySQL.*|MariaDB.*|wamp.*|Apache.*)$' })
    Add-Check 'services' ($conflicts.Count -eq 0) ('Serviços incompatíveis com preparação de raiz: ' + (($conflicts | Select-Object -ExpandProperty Name) -join ', '))
} catch { Add-Check 'services' $false 'Não foi possível consultar os serviços; a instalação deve parar antes de modificar o Windows.' } }
[pscustomobject]@{
    mode = 'read-only'
    ready = !($checks | Where-Object { !$_.passed })
    checks = @($checks.ToArray())
} | ConvertTo-Json -Depth 5
