<#
scripts/admin_test_pinjaman.ps1
PowerShell helper to create a new pinjaman via API (Bearer token) and verify it appears on admin approval page using a session cookie.
Usage example:
  $token = 'USER_API_TOKEN'
  $cookie = 'PHPSESSID=abc123'
  .\scripts\admin_test_pinjaman.ps1 -BearerToken $token -PhpSessionCookie $cookie
#>
Param(
    [string]$ApiUrl = 'http://localhost/gas_web/api/pinjaman/ajukan.php',
    [string]$AdminUrl = 'http://localhost/gas_web/admin/pinjaman_approval.php',
    [Parameter(Mandatory=$true)][string]$BearerToken,
    [Parameter(Mandatory=$true)][string]$PhpSessionCookie
)

Write-Host "Creating pinjaman via API: $ApiUrl"
$body = @{ jumlah_pinjaman = 100000; tenor = 6; tujuan_penggunaan = 'Test pinjaman via admin_test' } | ConvertTo-Json
$headers = @{ Authorization = "Bearer $BearerToken"; 'Content-Type' = 'application/json' }
try {
    $resp = Invoke-RestMethod -Method Post -Uri $ApiUrl -Body $body -Headers $headers -ErrorAction Stop
} catch {
    Write-Error "API request failed: $_"
    exit 2
}
Write-Host "API response:"; $resp | ConvertTo-Json -Depth 4

if (-not $resp.id) {
    Write-Error "No 'id' returned from API. Cannot verify on admin page."
    exit 3
}
$id = $resp.id
Start-Sleep -Seconds 1
Write-Host "Fetching admin page as admin using session cookie: $AdminUrl"
$webHeaders = @{ Cookie = $PhpSessionCookie }
try {
    $page = Invoke-WebRequest -Uri $AdminUrl -Headers $webHeaders -UseBasicParsing -ErrorAction Stop
} catch {
    Write-Error "Fetching admin page failed: $_"
    exit 4
}

if ($page.Content -match "\b$id\b") {
    Write-Host "Success: Pengajuan ID $id found on admin approval page." -ForegroundColor Green
    exit 0
}

Write-Error "Pengajuan ID $id NOT found on admin page. Here is an excerpt:" 
$page.Content.Substring(0, [Math]::Min(3000, $page.Content.Length))
exit 5
