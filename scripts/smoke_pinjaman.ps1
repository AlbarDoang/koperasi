<#
.SYNOPSIS
  scripts\smoke_pinjaman.ps1

DESCRIPTION
  PowerShell smoke tests for api/pinjaman endpoints.

USAGE
  # set env var API_TOKEN_VALID first, and optionally BASE_URL
  $env:API_TOKEN_VALID = "<valid_token>"
  $env:BASE_URL = "http://192.168.43.151/gas/gas_web"
  pwsh ./scripts/smoke_pinjaman.ps1

This script runs these checks:
  1) ajukan (happy path) -> expects HTTP 201 + {"status":true}
  2) list (valid token) -> expects HTTP 200 + {"status":true}
  3) list (invalid token) -> expects HTTP 401
  4) ajukan (bad request) -> expects HTTP 400 + {"status":false}
#>

param()

$BASE_URL = $env:BASE_URL
if (-not $BASE_URL) { $BASE_URL = "http://192.168.43.151/gas/gas_web" }
$API_TOKEN_VALID = $env:API_TOKEN_VALID
if (-not $API_TOKEN_VALID) { Write-Error "Set API_TOKEN_VALID in environment before running."; exit 2 }
$API_TOKEN_INVALID = $env:API_TOKEN_INVALID
if (-not $API_TOKEN_INVALID) { $API_TOKEN_INVALID = "invalid-token-123" }

function Fail($msg){ Write-Host "âŒ FAIL: $msg"; exit 1 }
function Pass($msg){ Write-Host "âœ… PASS: $msg" }

function Invoke-Test {
  param($Method, $Url, $Headers = @{}, $Body = $null, $ExpectedCode)

  try {
    $resp = Invoke-WebRequest -Uri $Url -Method $Method -Headers $Headers -Body $Body -ContentType "application/json" -UseBasicParsing -ErrorAction Stop
    $code = $resp.StatusCode
    $content = $resp.Content
  } catch [System.Net.WebException] {
    $wr = $_.Exception.Response
    if ($wr -ne $null) {
      $sr = New-Object System.IO.StreamReader($wr.GetResponseStream())
      $content = $sr.ReadToEnd()
      $code = [int]$wr.StatusCode
    } else {
      Fail "Network/Request error: $($_.Exception.Message)"
    }
  }
  if ($code -ne $ExpectedCode) {
    Write-Host "HTTP $code"
    Write-Host $content
    Fail "Expected HTTP $ExpectedCode"
  }
  return $content
}

# 1) ajukan happy
Write-Host "-> ajukan (happy)"
$body = '{"jumlah_pinjaman":1000000,"tenor":12,"tujuan_penggunaan":"Modal usaha"}'
$hdr = @{ Authorization = "Bearer $API_TOKEN_VALID"; 'Content-Type' = 'application/json'; Accept = 'application/json' }
$content = Invoke-Test -Method Post -Url "$BASE_URL/api/pinjaman/ajukan.php" -Headers $hdr -Body $body -ExpectedCode 201
if ($content -match '"status"\s*:\s*true') { Pass "ajukan (happy)" } else { Write-Host $content; Fail "ajukan (happy) missing status:true" }

# 2) list valid token
Write-Host "-> list (valid token)"
$content = Invoke-Test -Method Get -Url "$BASE_URL/api/pinjaman/list.php?limit=5" -Headers $hdr -ExpectedCode 200
if ($content -match '"status"\s*:\s*true') { Pass "list (valid token)" } else { Write-Host $content; Fail "list( valid ) missing status:true" }

# 3) unauthorized invalid token
Write-Host "-> list (invalid token) expect 401"
$hdr2 = @{ Authorization = "Bearer $API_TOKEN_INVALID" }
$content = Invoke-Test -Method Get -Url "$BASE_URL/api/pinjaman/list.php" -Headers $hdr2 -ExpectedCode 401
Pass "invalid token returned 401"

# 4) ajukan bad json / invalid fields
Write-Host "-> ajukan (bad request)"
$badbody = '{"jumlah_pinjaman":"notanumber","tenor":"x","tujuan_penggunaan":""}'
$content = Invoke-Test -Method Post -Url "$BASE_URL/api/pinjaman/ajukan.php" -Headers $hdr -Body $badbody -ExpectedCode 400
if ($content -match '"status"\s*:\s*false') { Pass "ajukan (bad) returned 400 status:false" } else { Write-Host $content; Fail "ajukan(bad) missing status:false" }

Write-Host ""
Write-Host "ALL smoke tests passed âœ…"
