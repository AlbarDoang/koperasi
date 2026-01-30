param(
  [string]$User = $env:ADMIN_TEST_USER,
  [string]$Cookie = $env:ADMIN_TEST_COOKIE,
  [string]$Base = $env:ADMIN_BASE,
  [string]$Amount = $env:ADMIN_TEST_AMOUNT,
  [string]$Tenor = $env:ADMIN_TEST_TENOR,
  [string]$Tujuan = $env:ADMIN_TEST_TUJUAN
)

if (-not $User -or -not $Cookie) {
  Write-Error "Please set ADMIN_TEST_USER and ADMIN_TEST_COOKIE environment variables or pass parameters."
  exit 2
}
if (-not $Base) { $Base = 'http://localhost/gas_web' }
if (-not $Amount) { $Amount = '100000' }
if (-not $Tenor) { $Tenor = '6' }
if (-not $Tujuan) { $Tujuan = "admin-test-$(Get-Random -Maximum 9999)" }

$script = Join-Path -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition) -ChildPath 'admin_test_pinjaman_run.php'
$cmd = "php `"$script`" --user=$User --cookie='$Cookie' --base='$Base' --amount=$Amount --tenor=$Tenor --tujuan='$Tujuan'"
Write-Host "Running: $cmd"
Invoke-Expression $cmd