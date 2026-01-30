#!/usr/bin/env bash
# scripts/admin_test_pinjaman.sh
# Usage:
#   export API_TOKEN='...'
#   export PHPSESSID='...'
#   ./scripts/admin_test_pinjaman.sh

API=${API_URL:-http://localhost/gas_web/api/pinjaman/ajukan.php}
ADMIN=${ADMIN_URL:-http://localhost/gas_web/admin/pinjaman_approval.php}
TOKEN=${API_TOKEN:-}
COOKIE=${PHPSESSID:-}

if [ -z "$TOKEN" ] || [ -z "$COOKIE" ]; then
  echo "Please set API_TOKEN and PHPSESSID environment variables."
  echo "Example: export API_TOKEN=abc123; export PHPSESSID=def456; ./scripts/admin_test_pinjaman.sh"
  exit 1
fi

BODY='{"jumlah_pinjaman":100000,"tenor":6,"tujuan_penggunaan":"Test pinjaman via admin_test"}'

RESP=$(curl -s -X POST "$API" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' --data-binary "$BODY")
echo "API response: $RESP"
ID=$(echo "$RESP" | sed -n 's/.*"id"[[:space:]]*:[[:space:]]*\([0-9]\+\).*/\1/p')
if [ -z "$ID" ]; then
  echo "No id returned by API. Response above."
  exit 2
fi

sleep 1
PAGE=$(curl -s -b "PHPSESSID=$COOKIE" "$ADMIN")
if echo "$PAGE" | grep -q "$ID"; then
  echo "Success: ID $ID appears on admin page"
  exit 0
else
  echo "ID $ID NOT found on admin page. Admin page snippet:"
  echo "$PAGE" | sed -n '1,120p'
  exit 3
fi
