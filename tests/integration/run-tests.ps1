<#
PowerShell wrapper to run integration tests on Windows and pretty-print results.
Usage:
  powershell.exe -ExecutionPolicy Bypass -File tests\integration\run-tests.ps1 -BaseUrl "http://localhost/gas/gas_web"
Parameters:
  -BaseUrl (default: http://localhost/gas/gas_web)
  -DbHost (default: localhost)
  -DbUser (default: root)
  -DbPass (default: '')
  -DbName (default: tabungan)
  -PhpExe (default: php)
  -Verbose
#>
param(
    [string]$BaseUrl = 'http://localhost/gas/gas_web',
    [string]$DbHost = 'localhost',
    [string]$DbUser = 'root',
    [string]$DbPass = '',
    [string]$DbName = 'tabungan',
    [string]$PhpExe = 'php',
    [switch]$Verbose
)

function Write-Ok($s) { Write-Host $s -ForegroundColor Green }
function Write-Fail($s) { Write-Host $s -ForegroundColor Red }
function Write-Info($s) { Write-Host $s -ForegroundColor Cyan }

# Validate php executable
try {
    $phpVersion = & $PhpExe -v 2>$null
} catch {
    Write-Fail "PHP CLI not found at '$PhpExe'. Please install PHP CLI or pass -PhpExe 'C:\\path\\to\\php.exe'"
    exit 2
}

Write-Info "Running integration tests against: $BaseUrl"

$cmd = @(
    $PhpExe,
    'tests/integration/flow_test.php',
    "--base-url=$BaseUrl",
    "--db-host=$DbHost",
    "--db-user=$DbUser",
    "--db-pass=$DbPass",
    "--db-name=$DbName"
) -join ' '

if ($Verbose) { Write-Host "Command: $cmd" }

# Run process and capture output
$procInfo = New-Object System.Diagnostics.ProcessStartInfo
$procInfo.FileName = $PhpExe
$procInfo.Arguments = "tests/integration/flow_test.php --base-url=$BaseUrl --db-host=$DbHost --db-user=$DbUser --db-pass=$DbPass --db-name=$DbName"
$procInfo.RedirectStandardOutput = $true
$procInfo.RedirectStandardError = $true
$procInfo.UseShellExecute = $false
$procInfo.CreateNoWindow = $true

$proc = New-Object System.Diagnostics.Process
$proc.StartInfo = $procInfo
$proc.Start() | Out-Null

# Read output line by line and pretty print
$exitCode = 0
while (-not $proc.HasExited) {
    while (-not $proc.StandardOutput.EndOfStream) {
        $line = $proc.StandardOutput.ReadLine()
        if ($line -match "\[OK\]") { Write-Ok $line }
        elseif ($line -match "\[FAIL\]") { Write-Fail $line; $exitCode = 1 }
        elseif ($line -match "ALL TESTS PASSED") { Write-Host $line -BackgroundColor DarkGreen -ForegroundColor White }
        else { Write-Host $line }
    }
    Start-Sleep -Milliseconds 100
}

# Flush remaining output
while (-not $proc.StandardOutput.EndOfStream) {
    $line = $proc.StandardOutput.ReadLine()
    if ($line -match "\[OK\]") { Write-Ok $line }
    elseif ($line -match "\[FAIL\]") { Write-Fail $line; $exitCode = 1 }
    elseif ($line -match "ALL TESTS PASSED") { Write-Host $line -BackgroundColor DarkGreen -ForegroundColor White }
    else { Write-Host $line }
}

# Also read stderr
$errOutput = $proc.StandardError.ReadToEnd()
if ($errOutput -ne "") {
    Write-Host "----- STDERR -----" -ForegroundColor Yellow
    Write-Host $errOutput
    $exitCode = 1
}

$proc.WaitForExit()
if ($proc.ExitCode -ne 0) { $exitCode = $proc.ExitCode }

if ($exitCode -eq 0) {
    Write-Ok "\nIntegration tests completed successfully."; exit 0
} else {
    Write-Fail "\nIntegration tests failed (exit code $exitCode). Check output above for details."; exit $exitCode
}
