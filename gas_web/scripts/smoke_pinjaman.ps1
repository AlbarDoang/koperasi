# ================================
# Smoke Test Pinjaman API
# ================================

if (-not $env:API_TOKEN_VALID) {
    Write-Host "❌ API_TOKEN_VALID belum diset"
    exit 1
}

if (-not $env:BASE_URL) {
    $env:BASE_URL = "http://localhost/gas/gas_web"
}

$BASE_URL = $env:BASE_URL
$TOKEN = $env:API_TOKEN_VALID

Write-Host "▶ BASE_URL: $BASE_URL"
Write-Host "▶ TOKEN: $TOKEN"
Write-Host "==============================="

# ---------- 1. Ajukan Pinjaman ----------
Write-Host "`n[TEST] Ajukan Pinjaman"

$body = @{
    jumlah_pinjaman = 1000000
    tenor = 10
    tujuan_penggunaan = "Test smoke pinjaman"
} | ConvertTo-Json

$response = curl.exe -s -w "`n%{http_code}" `
    -X POST "$BASE_URL/api/pinjaman/ajukan.php" `
    -H "Authorization: Bearer $TOKEN" `
    -H "Content-Type: application/json" `
    -d $body

$responseParts = $response -split "`n"
$httpCode = $responseParts[-1]
$bodyJson = $responseParts[0]

Write-Host "HTTP: $httpCode"
Write-Host "BODY: $bodyJson"

# ---------- 2. List Pinjaman ----------
Write-Host "`n[TEST] List Pinjaman"

$response = curl.exe -s -w "`n%{http_code}" `
    -X GET "$BASE_URL/api/pinjaman/list.php" `
    -H "Authorization: Bearer $TOKEN"

$responseParts = $response -split "`n"
$httpCode = $responseParts[-1]
$bodyJson = $responseParts[0]

Write-Host "HTTP: $httpCode"
Write-Host "BODY: $bodyJson"

# ---------- 3. Token Salah ----------
Write-Host "`n[TEST] Token Invalid"

$response = curl.exe -s -w "`n%{http_code}" `
    -X GET "$BASE_URL/api/pinjaman/list.php" `
    -H "Authorization: Bearer SALAH_TOKEN"

$responseParts = $response -split "`n"
$httpCode = $responseParts[-1]
$bodyJson = $responseParts[0]

Write-Host "HTTP: $httpCode"
Write-Host "BODY: $bodyJson"

Write-Host "`n✅ Smoke test selesai"