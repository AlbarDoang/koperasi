# Development notes — User edit safety

Purpose: Ensure admin edits of user profile do NOT change `status_akun`, `approved_at`, or `is_active`.

Summary of changes:
- `flutter_api/admin_update_user.php` now:
  - Rejects any attempt to change `status_akun` (returns 400-ish JSON error).
  - Only updates allowed fields: `nama_lengkap`, `no_hp`, `alamat_domisili`, `tanggal_lahir`.
  - Uses prepared statements and phone normalization (`helpers.php::sanitizePhone()`)
  - Checks for duplicate phone number.

- `flutter_api/update_biodata.php` now:
  - Normalizes phone number, validates it, checks uniqueness if changed.
  - Uses a prepared UPDATE statement for allowed fields only.

Testing:
- Quick test script: `php scripts/test_admin_update_user.php <user_id>`
  - Verifies that attempts to change `status_akun` via `admin_update_user.php` are rejected
  - Verifies that profile updates do not affect `status_akun`.

Notes / rationale:
- Status transitions (approve/reject) remain handled only by approval endpoints (`approve_user_process.php`, `aktivasi_akun.php`, etc.).
- This prevents accidental status regressions when admin modifies profile fields.

If you want, I can add automated integration tests or add server-side logging for rejected status-change attempts.

---

## Profile photo storage and proxy

Changes made to handle profile photo storage securely:

- Profile photos are stored outside the webroot at: `C:\\gas_storage\\foto profil`
- PHP config and helpers: `flutter_api/storage_config.php` (new constants and helper functions)
- Upload handler: `flutter_api/update_foto_profil.php` now validates MIME (JPEG/PNG), enforces max 5MB, generates secure random filenames, stores filenames in DB (no public URL), and returns a short-lived signed proxy URL to access the image.
- 2026-01-08: Added per-user profile storage and timestamp tracking. `update_foto_profil.php` now stores files under `PROFILE_STORAGE_BASE/<user_id>/`, updates `foto_profil_updated_at` (UNIX ts) and returns JSON `{ success: true, foto_profil: "<filename>", foto_profil_updated_at: <ts> }` (also includes `foto_profil_url` and `foto_profil_key` for compatibility). Proxy `login/user/foto_profil_image.php` now sets no-cache headers and serves files from per-user folders. Mobile clients should use a cache-busting query param `?t=<foto_profil_updated_at>` and rebuild widget with `ValueKey` to ensure immediate refresh.
- Proxy endpoint: `login/user/foto_profil_image.php` verifies session (admin or owner) or a short-lived signature (`sig` + `exp`) and streams the image with correct Content-Type. Legacy files under `uploads/foto_profil` are still supported and will be migrated to new storage on first access (best-effort).

How to test locally:

1. Ensure PROFILE_STORAGE_BASE directory exists and PHP can write to it. The code will auto-create it when possible:
   - `C:\\gas_storage\\foto profil`
2. Upload a photo via script: `php scripts/test_foto_profil.php <id_pengguna> <path_to_image>` (example: `php scripts/test_foto_profil.php 12 /path/to/photo.jpg`)
3. The script will print the API response and attempt to fetch the signed URL (should return HTTP 200 and image bytes).
4. Verify admin UI displays images normally (since admin session is active in browser), and mobile client receives a signed URL from `get_profil.php` which it can use directly (no additional headers required).

Security notes:

- Files are not directly accessible under the webroot anymore; all access is via the proxy which enforces access control.
- Signed URLs are short-lived (5 minutes) and HMAC protected using `PROFILE_IMAGE_SECRET` (change this in production via env variable `PROFILE_IMAGE_SECRET`).
- Consider rotating secrets and/or implementing token-based validation for long-lived access if needed.