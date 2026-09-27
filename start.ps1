param(
      [ int ]   $Port      = 8000,
      [ string ]$IpAddress = "0.0.0.0"
     )
$ErrorActionPreference = "Stop"

$PhpCommand = Get-Command php -ErrorAction SilentlyContinue
$PhpPath = ""
if ( $PhpCommand )
{
    $PhpPath = $PhpCommand.Source
}

if ( -not ( Test-Path -LiteralPath $PhpPath ) )
{
    throw "PHP was not found. Add PHP 8.2 or later to PATH."
}

$RuntimePath = Join-Path $PSScriptRoot ".runtime"
New-Item -ItemType Directory -Force -Path $RuntimePath | Out-Null

$PhpArgs = @()
$Modules = & $PhpPath -m
if ( $Modules -notcontains "pdo_mysql" )
{
    $PhpArgs += @( "-d", "extension=pdo_mysql" )
}
if ( $Modules -notcontains "fileinfo" )
{
    $PhpArgs += @( "-d", "extension=fileinfo" )
}
$PhpArgs += @( "-d", "session.save_path=$RuntimePath", "-d", "display_errors=0", "-d", "log_errors=1" )

Push-Location $PSScriptRoot

try
{
    & $PhpPath @PhpArgs -S "${IpAddress}:$Port" router.php
}
finally
{
    Pop-Location
}
