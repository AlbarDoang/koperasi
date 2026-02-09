#!/usr/bin/env bash
# scripts/smoke_pinjaman.sh
# Curl-based smoke tests for api/pinjaman endpoints
# Usage:
#   export BASE_URL="http://192.168.43.151/gas/gas_web"
#   export API_TOKEN_VALID="<valid_token>"
#   ./scripts/smoke_pinjaman.sh
#
# This script runs 4 checks:
# 1) ajukan (happy path) -> expects HTTP 201 and JSON {status:true}
# 2) list (valid token) -> expects HTTP 200 and JSON {status:true}
# 3) list (invalid token) -> expects HTTP 401
# 4) ajukan (bad request) -> expects HTTP 400 and JSON {status:false}

set -euo pipefail

BASE_URL="${BASE_URL:-http://192.168.43.151/gas/gas_web}"
API_TOKEN_VALID="${API_TOKEN_VALID:-REPLACE_WITH_VALID_TOKEN}"
API_TOKEN_INVALID="${API_TOKEN_INVALID:-invalid-token-123}"

TMPDIR="$(mktemp -d)"
cleanup(){ rm -rf "$TMPDIR"; }
trap cleanup EXIT

fail() {
  echo "âŒ FAIL: $*"
  exit 1
}

pass() {
  echo "âœ… PASS: $*"
}

test_ajukan_happy() {
  echo "-> ajukan (happy path)"
  resp="$TMPDIR/ajukan_happy.json"
  http_code=$(curl -s -w '%{http_code}' -o "$resp" \
    -X POST "$BASE_URL/api/pinjaman/ajukan.php" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $API_TOKEN_VALID" \
    -d '{"jumlah_pinjaman":1000000,"tenor":12,"tujuan_penggunaan":"Modal usaha"}')

  if [ "$http_code" -ne 201 ]; then
    echo "HTTP $http_code"
    cat "$resp"
    fail "ajukan (happy) expected 201"
  fi

  if grep -q '"status"[[:space:]]*:[[:space:]]*true' "$resp"; then
    pass "ajukan (happy) HTTP 201, returned status:true"
  else
    cat "$resp"
    fail "ajukan (happy) missing status:true"
  fi
}

test_list_valid_token() {
  echo "-> list (valid token)"
  resp="$TMPDIR/list_valid.json"
  http_code=$(curl -s -w '%{http_code}' -o "$resp" \
    -H "Authorization: Bearer $API_TOKEN_VALID" \
    "$BASE_URL/api/pinjaman/list.php?limit=5")

  if [ "$http_code" -ne 200 ]; then
    echo "HTTP $http_code"
    cat "$resp"
    fail "list (valid token) expected 200"
  fi

  if grep -q '"status"[[:space:]]*:[[:space:]]*true' "$resp"; then
    pass "list (valid token) HTTP 200, returned status:true"
  else
    cat "$resp"
    fail "list (valid token) missing status:true"
  fi
}

test_unauthorized_invalid_token() {
  echo "-> list (invalid token) expecting 401"
  resp="$TMPDIR/list_invalid.json"
  http_code=$(curl -s -w '%{http_code}' -o "$resp" \
    -H "Authorization: Bearer $API_TOKEN_INVALID" \
    "$BASE_URL/api/pinjaman/list.php")

  if [ "$http_code" -ne 401 ]; then
    echo "HTTP $http_code"
    cat "$resp"
    fail "Expected 401 for invalid token"
  fi
  pass "invalid token correctly returned 401"
}

test_ajukan_bad_json() {
  echo "-> ajukan (bad JSON / invalid fields)"
  resp="$TMPDIR/ajukan_bad.json"
  # invalid jumlah_pinjaman (non-numeric) + empty tujuan_penggunaan to trigger validations
  http_code=$(curl -s -w '%{http_code}' -o "$resp" \
    -X POST "$BASE_URL/api/pinjaman/ajukan.php" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $API_TOKEN_VALID" \
    -d '{"jumlah_pinjaman":"notanumber","tenor":"x","tujuan_penggunaan":""}')

  if [ "$http_code" -ne 400 ]; then
    echo "HTTP $http_code"
    cat "$resp"
    fail "Expected 400 for invalid request data"
  fi

  if grep -q '"status"[[:space:]]*:[[:space:]]*false' "$resp"; then
    pass "ajukan (bad JSON/invalid fields) returned 400 status:false"
  else
    cat "$resp"
    fail "ajukan (bad) missing status:false"
  fi
}

# Run tests
test_ajukan_happy
test_list_valid_token
test_unauthorized_invalid_token
test_ajukan_bad_json

echo
echo "ALL smoke tests passed âœ…"

