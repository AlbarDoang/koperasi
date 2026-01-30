#!/usr/bin/env bash
# scripts/run_admin_test_pinjaman.sh
# Wrapper to run scripts/admin_test_pinjaman_run.php using environment variables
# Usage:
#   export ADMIN_TEST_USER=1
#   export ADMIN_TEST_COOKIE='PHPSESSID=abcd'
#   export ADMIN_BASE='http://localhost/gas_web' # optional
#   ./scripts/run_admin_test_pinjaman.sh

USER=${ADMIN_TEST_USER:-}
COOKIE=${ADMIN_TEST_COOKIE:-}
BASE=${ADMIN_BASE:-http://localhost/gas_web}
AMOUNT=${ADMIN_TEST_AMOUNT:-100000}
TENOR=${ADMIN_TEST_TENOR:-6}
TUJUAN=${ADMIN_TEST_TUJUAN:-}

if [ -z "$USER" ] || [ -z "$COOKIE" ]; then
  echo "Please set ADMIN_TEST_USER and ADMIN_TEST_COOKIE environment variables." >&2
  exit 2
fi

PHP=php
SCRIPT="$(dirname "$0")/admin_test_pinjaman_run.php"
ARGS=(--user="$USER" --cookie="$COOKIE" --base="$BASE" --amount="$AMOUNT" --tenor="$TENOR")
if [ -n "$TUJUAN" ]; then
  ARGS+=(--tujuan="$TUJUAN")
fi

$PHP "$SCRIPT" "${ARGS[@]}" | jq .
exit $?