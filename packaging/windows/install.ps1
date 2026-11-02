#Requires -Version 5.1
<#
.SYNOPSIS
  Installs DeathPlanSwitch as two start-up Scheduled Tasks running as NT AUTHORITY\LOCAL SERVICE.
.PARAMETER PhpPath
  Full path to php.exe. Default: php.exe found on PATH.
.PARAMETER Listen
  Address and port for the web server. Default 127.0.0.1:8080.
#>
param(
    [string]$PhpPath = '',
    [string]$Listen = '127.0.0.1:8080'
)
$ErrorActionPreference = 'Stop'

$id = [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not (New-Object Security.Principal.WindowsPrincipal($id)).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script from an elevated (Administrator) PowerShell.'
}
if ($Listen -notmatch '^[A-Za-z0-9.\-\[\]:]+:[0-9]{1,5}$') { throw "Invalid -Listen value: $Listen" }

# Find the app: next to this script (release folder) or two levels up (source checkout).
if (Test-Path -LiteralPath (Join-Path $PSScriptRoot 'bin\scheduler.php')) {
    $src = $PSScriptRoot
} else {
    $src = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}
if (-not (Test-Path -LiteralPath (Join-Path $src 'bin\scheduler.php'))) { throw "App files not found near $PSScriptRoot" }

function Test-ReparsePoint([string]$Path) {
    $i = Get-Item -LiteralPath $Path -Force -ErrorAction SilentlyContinue
    return [bool]($i -and ($i.Attributes -band [IO.FileAttributes]::ReparsePoint))
}
# Every file and folder under $Root, never descending into junctions or symbolic links (those are returned, not entered).
function Get-TreeItems([string]$Root) {
    foreach ($c in @(Get-ChildItem -LiteralPath $Root -Force)) {
        $c
        if ($c.PSIsContainer -and -not ($c.Attributes -band [IO.FileAttributes]::ReparsePoint)) { Get-TreeItems $c.FullName }
    }
}
# Local service account name for this Windows language (the SID is S-1-5-19).
$svcAccount = [Security.Principal.SecurityIdentifier]::new('S-1-5-19').Translate([Security.Principal.NTAccount]).Value

# Resolve php.exe once; the absolute path is stored in the wrapper (no PATH lookup at run time).
if ($PhpPath -eq '') {
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { $PhpPath = $cmd.Source }
}
if ($PhpPath -eq '' -or -not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw 'php.exe not found. Install PHP 8.1 or newer (for example: winget install PHP.PHP.8.4) or pass -PhpPath.'
}
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
if ($PhpPath -match '["%&|<>^]') { throw "Unsupported characters in PHP path: $PhpPath" }
if ($PhpPath.StartsWith('\\')) { throw "PHP on a network path is not supported: $PhpPath" }

# The tasks run php.exe, and this script runs it too (elevated). Anyone who can replace php.exe, a DLL
# next to it, or the folder itself gets code execution. Refuse unless only trusted accounts can write.
$trustedSids = @(
    'S-1-5-18', 'S-1-5-32-544', 'S-1-3-0',
    'S-1-5-80-956008885-3418522649-1831038044-1853292631-2271478464'   # SYSTEM, Administrators, CREATOR OWNER, TrustedInstaller
)
$FSR = [Security.AccessControl.FileSystemRights]
$writeMask  = [int]($FSR::WriteData -bor $FSR::AppendData -bor $FSR::WriteExtendedAttributes -bor $FSR::WriteAttributes -bor
              $FSR::Delete -bor $FSR::DeleteSubdirectoriesAndFiles -bor $FSR::ChangePermissions -bor $FSR::TakeOwnership) -bor 0x50000000
# For parent folders, creating new entries is harmless; replacing or renaming the child is not.
$replaceMask = [int]($FSR::Delete -bor $FSR::DeleteSubdirectoriesAndFiles -bor $FSR::ChangePermissions -bor $FSR::TakeOwnership) -bor 0x10000000

function Get-UntrustedWriters([string]$Path, [int]$Mask, [bool]$SkipInheritOnly) {
    $acl = Get-Acl -LiteralPath $Path
    $found = @()
    $owner = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
    if ($trustedSids -notcontains $owner) { $found += "owner $owner" }
    foreach ($r in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
        if ($r.AccessControlType -ne 'Allow') { continue }
        if ($SkipInheritOnly -and ($r.PropagationFlags -band [Security.AccessControl.PropagationFlags]::InheritOnly)) { continue }
        if (($trustedSids -notcontains $r.IdentityReference.Value) -and (([int]$r.FileSystemRights -band $Mask) -ne 0)) {
            $found += "$($r.IdentityReference.Value) ($($r.FileSystemRights))"
        }
    }
    return $found
}

$phpDir = Split-Path -Parent $PhpPath
$bad = @()
# A junction or symbolic link anywhere in the path would hide the real folder, whose parents go unchecked.
$p = $PhpPath
while ($p) {
    if (Test-ReparsePoint $p) { $bad += "${p}: is a junction or symbolic link; pass the real path to php.exe" }
    $n = Split-Path -Parent $p
    if ($n -eq $p) { break }
    $p = $n
}
if ($bad.Count -eq 0) {
    foreach ($w in (Get-UntrustedWriters $phpDir $writeMask $true)) { $bad += "folder ${phpDir}: $w" }
    # Everything inside the PHP folder (php.exe, DLLs, ext\, php.ini): explicit entries and other owners count too.
    foreach ($item in (Get-TreeItems $phpDir)) {
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { $bad += "$($item.FullName): is a junction or symbolic link"; continue }
        foreach ($w in (Get-UntrustedWriters $item.FullName $writeMask $true)) { $bad += "$($item.FullName): $w" }
    }
    $up = Split-Path -Parent $phpDir
    while ($up) {
        foreach ($w in (Get-UntrustedWriters $up $replaceMask $true)) { $bad += "parent folder ${up}: $w" }
        $next = Split-Path -Parent $up
        if ($next -eq $up) { break }
        $up = $next
    }
}
if ($bad.Count -gt 15) { $bad = @($bad | Select-Object -First 15) + '... and more' }
if ($bad.Count -gt 0) {
    $msg = "Refusing to install: PHP is in a location that non-administrators can modify:`n  " + ($bad -join "`n  ") + @"

The tasks run this php.exe, so whoever can replace it controls the service. Fix it, then run install.ps1 again:
  icacls "$phpDir" /setowner *S-1-5-32-544 /T /C /L
  icacls "$phpDir" /reset /T /C /L
  icacls "$phpDir" /inheritance:r /grant:r *S-1-5-18:(OI)(CI)F *S-1-5-32-544:(OI)(CI)F *S-1-5-32-545:(OI)(CI)RX
If a parent folder is listed, apply the setowner (without /T /C /L) and the inheritance commands to it, or move PHP to "$env:ProgramFiles\PHP" (administrators only by default).
"@
    throw $msg
}
$check = & $PhpPath -r 'echo PHP_VERSION_ID; foreach (["curl","mbstring","openssl","sodium"] as $e) { if (!extension_loaded($e)) echo " missing:$e"; }'
$parts = ($check -join ' ').Trim() -split ' '
if ([int]$parts[0] -lt 80100) { throw "PHP 8.1 or newer is required (found version ID $($parts[0]))." }
$missing = @($parts | Where-Object { $_ -like 'missing:*' } | ForEach-Object { $_.Substring(8) })
if ($missing.Count -gt 0) {
    throw "Enable these PHP extensions in php.ini: $($missing -join ', ')"
}

$app  = Join-Path $env:ProgramFiles 'DeathPlanSwitch'
$data = Join-Path $env:ProgramData  'DeathPlanSwitch'
$taskNames = 'DeathPlanSwitch Web', 'DeathPlanSwitch Scheduler'

# Idempotent: stop existing tasks and their php processes before replacing files.
foreach ($t in $taskNames) {
    if (Get-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue) {
        Stop-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue
    }
}
Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($app) } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

# Copy the app. Data lives elsewhere and is never touched.
New-Item -ItemType Directory -Force -Path $app | Out-Null
$xd = @('packaging', '.git', '.github', 'tests', 'docs', 'docker') | ForEach-Object { Join-Path $src $_ }
$xd += Join-Path $src 'data'
$null = & robocopy $src $app /MIR /NFL /NDL /NJH /NJS /NP /XD $xd /XF install.ps1 uninstall.ps1 install.sh README.txt run-web.cmd run-scheduler.cmd
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }
# data\.htaccess is part of the app (the real data folder is separate).
New-Item -ItemType Directory -Force -Path (Join-Path $app 'data') | Out-Null
if (Test-Path -LiteralPath (Join-Path $src 'data\.htaccess')) {
    Copy-Item -LiteralPath (Join-Path $src 'data\.htaccess') -Destination (Join-Path $app 'data\.htaccess') -Force
}

# Data folder: LOCAL SERVICE (modify), SYSTEM and Administrators (full); nothing else.
$dataOwners = @('S-1-5-18', 'S-1-5-32-544')
if (Test-Path -LiteralPath $data) {
    # Pre-existing folder: adopt it only when it is our own (owned by SYSTEM or Administrators, nothing planted inside).
    if (Test-ReparsePoint $data) { throw "Refusing to use ${data}: it is a junction or symbolic link. Remove it and run install.ps1 again." }
    $o = (Get-Acl -LiteralPath $data).GetOwner([Security.Principal.SecurityIdentifier]).Value
    if ($dataOwners -notcontains $o) { throw "Refusing to use ${data}: it is owned by $o. Delete it (after checking it holds nothing you need) and run install.ps1 again." }
    foreach ($item in (Get-TreeItems $data)) {
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Refusing to use ${data}: $($item.FullName) is a junction or symbolic link." }
        $io = (Get-Acl -LiteralPath $item.FullName).GetOwner([Security.Principal.SecurityIdentifier]).Value
        if (($dataOwners + 'S-1-5-19') -notcontains $io) { throw "Refusing to use ${data}: $($item.FullName) is owned by $io." }
    }
} else {
    New-Item -ItemType Directory -Path $data | Out-Null
}
if (Test-ReparsePoint $data) { throw "Refusing to use ${data}: it is a junction or symbolic link." }
$null = & icacls $data /setowner '*S-1-5-32-544' /T /C /L
if ($LASTEXITCODE -ne 0) { throw 'icacls could not take ownership of the data folder.' }
$null = & icacls $data /reset /T /C /L
if ($LASTEXITCODE -ne 0) { throw 'icacls could not reset the data folder.' }
$null = & icacls $data /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' '*S-1-5-19:(OI)(CI)M'
if ($LASTEXITCODE -ne 0) { throw 'icacls failed on the data folder.' }
# Check again after the lock-down: nothing may have been swapped in meanwhile.
foreach ($item in (Get-TreeItems $data)) {
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Refusing to use ${data}: $($item.FullName) is a junction or symbolic link." }
}

# The program folder stays read-only for the service account (read and execute only).
$null = & icacls $app /grant '*S-1-5-19:(OI)(CI)RX'
if ($LASTEXITCODE -ne 0) { throw 'icacls failed on the program folder.' }

# Wrappers set the environment; they live in the program folder (writable by administrators only, readable by LOCAL SERVICE).
$webCmd   = Join-Path $app 'run-web.cmd'
$schedCmd = Join-Path $app 'run-scheduler.cmd'
$web = @(
    '@echo off',
    "set `"DPS_DATA=$data`"",
    "set `"DPS_LISTEN=$Listen`"",
    "`"$PhpPath`" -S $Listen -t `"$app`" `"$app\bin\router.php`""
)
$sched = @(
    '@echo off',
    "set `"DPS_DATA=$data`"",
    "set `"DPS_LISTEN=$Listen`"",
    "`"$PhpPath`" `"$app\bin\scheduler.php`""
)
Set-Content -LiteralPath $webCmd   -Value $web   -Encoding ASCII
Set-Content -LiteralPath $schedCmd -Value $sched -Encoding ASCII

$principal = New-ScheduledTaskPrincipal -UserId $svcAccount -LogonType ServiceAccount -RunLevel Limited
$settings  = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Seconds 0) -MultipleInstances IgnoreNew
$trigger = New-ScheduledTaskTrigger -AtStartup
foreach ($pair in @(@('DeathPlanSwitch Web', $webCmd), @('DeathPlanSwitch Scheduler', $schedCmd))) {
    $action = New-ScheduledTaskAction -Execute "$env:SystemRoot\System32\cmd.exe" -Argument "/c `"$($pair[1])`""
    Register-ScheduledTask -TaskName $pair[0] -Action $action -Trigger $trigger -Principal $principal `
        -Settings $settings -Description 'DeathPlanSwitch' -Force | Out-Null
    Start-ScheduledTask -TaskName $pair[0]
}

Write-Host "Installed. Open http://$Listen/ to finish setup."
Write-Host "Program files: $app"
Write-Host "Data:          $data"
