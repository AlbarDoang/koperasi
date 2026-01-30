Integration test for account activation and PIN flow

Overview

This is a single self-contained PHP CLI script that validates the following flows end-to-end (server-side + DB checks):

- Register (stage 1) -> creates `pengguna` with `status_akun='draft'`
- Register (stage 2) -> upload `foto_ktp` & `foto_selfie`, sets `status_akun='submitted'`
- Activation (send OTP, verify OTP) -> sets `status_akun='pending'`
- Admin approve -> sets `status_akun='approved'`
- Login after verification -> returns `needs_set_pin=true` if PIN not set
- Set PIN (form POST) -> stores hashed PIN in `pengguna.pin`
- Login after PIN -> returns `needs_set_pin=false`

It also runs a rejection scenario where admin rejects a submission and the login attempt returns a 403 including the rejection reason.

How to run

1. Ensure your local webserver is running and the application is reachable. Default base URL used by tests:
   http://localhost/gas/gas_web

2. From the repository root run:
   php tests/integration/flow_test.php --base-url=http://localhost/gas/gas_web

3. Optional DB overrides:
   php tests/integration/flow_test.php --db-host=localhost --db-user=root --db-pass="" --db-name=tabungan

Notes and requirements

- The script requires PHP CLI with cURL and mysqli extensions enabled.
- It will generate temporary 1x1 PNG files at runtime for uploads; no binary assets are committed.
- The script reads OTP codes directly from the local `otp_codes` table to complete OTP verification (this is necessary for automated tests since WhatsApp delivery isn't available in CI).
- No schema changes or migrations are performed by the tests.

If you want, I can also provide a PowerShell wrapper to run the script with defaults on Windows.

Windows / PowerShell

- Run the included wrapper with defaults:

  powershell.exe -ExecutionPolicy Bypass -File tests\integration\run-tests.ps1

- To override the base URL or DB connection pass parameters:

  powershell.exe -ExecutionPolicy Bypass -File tests\integration\run-tests.ps1 -BaseUrl "http://localhost/gas/gas_web" -DbHost localhost -DbUser root -DbPass "" -DbName tabungan

The wrapper will pretty-print [OK] and [FAIL] lines and exit with a non-zero code if tests fail. It uses the PHP CLI available on your PATH by default; pass -PhpExe to provide a full path to a php.exe if needed.
