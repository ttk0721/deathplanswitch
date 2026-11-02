#Requires -Version 5.1
<#
.SYNOPSIS
  Removes the DeathPlanSwitch tasks and program files. Keeps data unless -RemoveData.
#>
param([switch]$RemoveData)
$ErrorActionPreference = 'Stop'

$id = [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not (New-Object Security.Principal.WindowsPrincipal($id)).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script from an elevated (Administrator) PowerShell.'
}

$app  = Join-Path $env:ProgramFiles 'DeathPlanSwitch'
$data = Join-Path $env:ProgramData  'DeathPlanSwitch'

foreach ($t in 'DeathPlanSwitch Web', 'DeathPlanSwitch Scheduler') {
    if (Get-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue) {
        Stop-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue
        Unregister-ScheduledTask -TaskName $t -Confirm:$false
    }
}
Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($app) } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

# Delete a tree without following junctions or symbolic links (a link is removed, never its target).
function Test-ReparsePoint([string]$Path) {
    $i = Get-Item -LiteralPath $Path -Force -ErrorAction SilentlyContinue
    return ($i -and ($i.Attributes -band [IO.FileAttributes]::ReparsePoint))
}
function Remove-TreeNoFollow([string]$Path) {
    foreach ($c in @(Get-ChildItem -LiteralPath $Path -Force -ErrorAction SilentlyContinue)) {
        if ($c.Attributes -band [IO.FileAttributes]::ReparsePoint) {
            if ($c.PSIsContainer) { [IO.Directory]::Delete($c.FullName) } else { [IO.File]::Delete($c.FullName) }
        } elseif ($c.PSIsContainer) {
            Remove-TreeNoFollow $c.FullName
        } else {
            Remove-Item -LiteralPath $c.FullName -Force
        }
    }
    [IO.Directory]::Delete($Path)
}

foreach ($p in $app, $data) {
    if ((Test-Path -LiteralPath $p) -and (Test-ReparsePoint $p) -and ($p -eq $app -or $RemoveData)) {
        throw "Refusing to delete ${p}: it is a junction or symbolic link. Remove the link yourself after checking where it points."
    }
}

if (Test-Path -LiteralPath $app) { Remove-TreeNoFollow $app }

if ($RemoveData) {
    if (Test-Path -LiteralPath $data) { Remove-TreeNoFollow $data }
    Write-Host 'Removed tasks, program files and data.'
} else {
    Write-Host "Removed tasks and program files. Data kept in $data (use -RemoveData to delete it)."
}
