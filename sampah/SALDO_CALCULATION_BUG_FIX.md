# Saldo Calculation Bug Fix - Withdrawal/Pencairan System

**Date:** 2026-01-21  
**Issue:** Saldo inconsistency after rejected withdrawal request  
**Severity:** 🔴 CRITICAL - Affects core withdrawal functionality

## Problem Description

### Scenario
1. User deposits Rp 500.000 to Tabungan Qurban ✓
2. User creates withdrawal request for Rp 250.000 ✓
3. Admin rejects the withdrawal request ✗
4. **BUG:** System shows:
   - Dashboard saldo: Rp 500.000 (CORRECT)
   - Withdrawal validation: "Only Rp 250.000 available" (WRONG)
   - User cannot withdraw Rp 400.000 → error "Saldo tidak mencukupi"

### Root Cause

**File:** [cairkan_tabungan.php](cairkan_tabungan.php#L302-L303)  
**Lines:** 302-303

```php
// BUGGY CODE (counts ALL withdrawals including pending & rejected)
$stmtOut = $connect->prepare(
    "SELECT COALESCE(SUM(jumlah),0) AS total_out 
     FROM tabungan_keluar 
     WHERE id_pengguna = ? AND id_jenis_tabungan = ?"
);
```

**Issue:** The query sums **ALL** `tabungan_keluar` rows without filtering by status:
- ✗ Includes PENDING withdrawals (blocking new withdrawals)
- ✗ Includes REJECTED withdrawals (WRONG - should be ignored)
- ✓ Includes APPROVED withdrawals (correct)

**Formula Used:**
```
available_saldo = total_masuk - total_keluar_ALL
                = 500k - (250k_rejected) ← WRONG
                = 250k
```

**Formula Should Be:**
```
available_saldo = total_masuk - total_keluar_approved
                = 500k - 0k
                = 500k ✓
```

### Why Dashboard Shows Correct Saldo

**File:** [get_saldo_tabungan.php](get_saldo_tabungan.php#L101-L110)  
**Lines:** 101-110

```php
$statusClause = $has_status_col ? " AND status = 'approved'" : "";
$sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total_out 
           FROM tabungan_keluar 
           WHERE id_pengguna = ?" . $statusClause;  // ✓ CORRECT
```

This endpoint correctly filters by `status = 'approved'`, so dashboard shows Rp 500.000.

## Solution

### Code Changes

**File:** [cairkan_tabungan.php](cairkan_tabungan.php#L302-L303)

**Before:**
```php
$stmtOut = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
```

**After:**
```php
$stmtOut = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? AND status = 'approved'");
```

### Changes Summary
1. ✅ Added `AND status = 'approved'` filter to line 302
2. ✅ Now consistent with `get_saldo_tabungan.php` implementation
3. ✅ Verified all other API endpoints already use correct filtering
4. ✅ Rejection flow in `approve_penarikan.php` correctly sets `status = 'rejected'`

### Affected API Endpoints (Verification)

| Endpoint | Saldo Calculation | Status | Notes |
|----------|------------------|--------|-------|
| `cairkan_tabungan.php` | `total_keluar - ALL` | 🔴 FIXED | Was missing status filter |
| `get_saldo_tabungan.php` | `total_keluar - approved` | ✅ CORRECT | Uses `AND status = 'approved'` |
| `get_summary_by_jenis.php` | `total_keluar - approved` | ✅ CORRECT | Uses `$statusClause` |
| `get_total_tabungan.php` | `total_keluar - approved` | ✅ CORRECT | Uses `$statusClause` |
| `get_rincian_tabungan.php` | `total_keluar - approved` | ✅ CORRECT | Uses `$statusClause` |

## Approval Flow Verification

**File:** [approve_penarikan.php](approve_penarikan.php#L310-L330)

### When Admin Rejects (CORRECT BEHAVIOR):
```php
// Lines 318-326
$stmtReject = $connect->prepare(
    "UPDATE tabungan_keluar 
     SET status = 'rejected', rejected_reason = ?, updated_at = NOW() 
     WHERE id = ? AND status = 'pending'"
);
```

✅ Sets `status = 'rejected'` → Future saldo calculations exclude this row

### When Admin Approves (CORRECT BEHAVIOR):
```php
// Lines 150-230
// 1. Deducts from tabungan_masuk
// 2. Sets tabungan_keluar.status = 'approved'
// 3. Credits pengguna.saldo
```

✅ Sets `status = 'approved'` → Included in saldo calculation as intended

## Testing Verification

### Test Scenario: Deposit → Request → Reject → Request Again

```sql
-- Setup: User ID 1, Tabungan Qurban ID 1
INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, status, created_at)
VALUES (1, 1, 500000, 'berhasil', NOW());

-- Step 1: Create withdrawal request for 250k
INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, status, created_at)
VALUES (1, 1, 250000, 'pending', NOW());

-- Step 2: Admin rejects
UPDATE tabungan_keluar 
SET status = 'rejected', rejected_reason = 'Alasan penolakan'
WHERE id = 1;

-- Step 3: Saldo check (should show 500k available)
SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk 
WHERE id_pengguna = 1 AND id_jenis_tabungan = 1;  -- 500000

SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar 
WHERE id_pengguna = 1 AND id_jenis_tabungan = 1 AND status = 'approved';  -- 0

-- Available: 500000 - 0 = 500000 ✓

-- Step 4: Create new withdrawal request for 400k (should succeed)
INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, status, created_at)
VALUES (1, 1, 400000, 'pending', NOW());

-- Step 5: Saldo check (should show 500k - 0 = 500k still available because pending not approved yet)
SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar 
WHERE id_pengguna = 1 AND id_jenis_tabungan = 1 AND status = 'approved';  -- 0 (pending doesn't count)

-- Available: 500000 - 0 = 500000 ✓ (can still withdraw more if admin approves)
```

### Expected Behavior After Fix

1. **Dashboard:** Always shows `total_masuk - approved_only = consistent balance`
2. **Withdrawal Validation:** Uses same calculation → consistent with dashboard
3. **Rejected Withdrawals:** No longer affect available saldo
4. **Pending Withdrawals:** Only show in request history, not counted as spent until approved

## Migration Notes

### Database State
No migrations needed. The issue is purely in the PHP query logic.

### Backwards Compatibility
✅ FULL COMPATIBILITY - Only affects validation logic, not data structure

### Recovery for Existing Users
If user reports saldo issue, rerun withdrawal request:
1. Previously rejected request no longer blocks new requests
2. Dashboard and validation now show consistent saldo
3. User can successfully withdraw correct amount

## Files Modified

| File | Lines | Change |
|------|-------|--------|
| [cairkan_tabungan.php](cairkan_tabungan.php#L302) | 302 | Added `AND status = 'approved'` |

## Deployment Status

- ✅ Code changed
- ⏳ Pending: Flutter app rebuild for testing
- ⏳ Pending: Functional testing with reject scenario
- ⏳ Pending: Production deployment after verification

## Related Issues Fixed Previously

1. ✅ FormatException error → Fixed syntax errors in `buat_mulai_nabung.php` and `update_status_mulai_nabung.php`
2. ✅ Saldo inconsistency (THIS FIX) → Fixed query filter in `cairkan_tabungan.php`

## Checklist

- [x] Identified root cause (missing status filter)
- [x] Applied fix to `cairkan_tabungan.php`
- [x] Verified other endpoints already correct
- [x] Verified approval flow (reject sets status='rejected')
- [ ] Test withdrawal → reject → withdraw again scenario
- [ ] Verify dashboard and validation show same saldo
- [ ] Deploy to production
- [ ] Monitor saldo_audit.log for issues
