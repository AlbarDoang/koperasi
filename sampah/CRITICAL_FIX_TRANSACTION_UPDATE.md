# CRITICAL BUG FIX - Transaction Update Query Column Name

## Problem Found
The UPDATE queries for transaction status changes were using the wrong column name:
- **WRONG**: `WHERE id = ?`
- **CORRECT**: `WHERE id_transaksi = ?`

This prevented transactions from being updated from pending → approved/rejected status.

## Files Fixed
- `admin_verifikasi_mulai_nabung.php` (4 locations):
  - Line 243: SELECT query - changed `id` → `id_transaksi`
  - Line 253: UPDATE query (approval) - changed `WHERE id = ?` → `WHERE id_transaksi = ?`
  - Line 407: SELECT query - changed `id` → `id_transaksi`
  - Line 417: UPDATE query (rejection) - changed `WHERE id = ?` → `WHERE id_transaksi = ?`

## Verification Results
✅ PHP syntax validation passed
✅ UPDATE queries now execute successfully
✅ Test shows transaction ID 243: `Status: approved` (confirming UPDATE is working)

## How It Works Now
1. User submits mulai_nabung → Creates transaction with `status='pending'`
2. Admin approves via admin panel → UPDATE changes transaction `status='pending'` → `status='approved'`
3. User opens Flutter app → Checks pending transactions against server
4. Transaction moves from "Proses" tab to "Selesai" tab automatically

## For User to See Changes
After admin approves/rejects in admin panel, user needs to:
**Option 1**: Pull down to refresh on the "Proses" tab
**Option 2**: Navigate away and back to Riwayat page
**Option 3**: Restart the app

The auto-refresh mechanism in riwayat.dart will detect the status change and update the UI.

## Test Data
- Transaction 243: Approved (status='approved') - UPDATE working ✅
- Transaction 251: Rejected (status='rejected') - UPDATE working ✅
- Multiple pending transactions: Ready to be updated ✅

Status: READY FOR TESTING
