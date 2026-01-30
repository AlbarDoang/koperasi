<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Models\User; // if your app uses different model, adjust
use Illuminate\Support\Facades\DB;

class WithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // run the migration SQL file if not using standard Laravel migrations in this test
        Artisan::call('migrate');
    }

    public function test_user_request_and_admin_approve_updates_wallet()
    {
        // create user and jenis
        $userId = DB::table('pengguna')->insertGetId(['nama_lengkap' => 'Test User', 'saldo' => 0]);
        $jenisId = DB::table('jenis_tabungan')->insertGetId(['nama_jenis' => 'Tabungan X']);

        // create a deposit that counts
        DB::table('tabungan_masuk')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 100000, 'status' => 'berhasil', 'created_at' => now(), 'updated_at' => now()]);

        // request withdrawal 50000
        $res = $this->postJson('/api/cairkan', ['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'nominal' => 50000]);
        $res->assertStatus(200)->assertJson(['ok' => true]);

        // Assert user's wallet NOT changed on submit
        $saldo_now = DB::table('pengguna')->where('id', $userId)->value('saldo');
        $this->assertEquals(0, (float)$saldo_now, 'User wallet must not change on submit');

        // assert pending row exists
        $this->assertDatabaseHas('tabungan_keluar', ['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 50000, 'status' => 'pending']);

        // Best-effort: check that a notification about the pending request was created (or detected as duplicate-skipped in logs)
        $noti = DB::table('notifikasi')->where('id_pengguna', $userId)->where('title', 'Permintaan Pencairan Diajukan')->first();
        if (!$noti) {
            $logPath = base_path('../gas_web/flutter_api/notification_filter.log');
            $this->assertFileExists($logPath, "Notification not found in DB and no notification log at {$logPath}");
            $logContent = file_get_contents($logPath);
            $this->assertStringContainsString("SKIPPED (duplicate) user={$userId}", $logContent, "No notif created and no duplicate skip log found. Log content: " . substr($logContent, -400));
        }

        $tk = DB::table('tabungan_keluar')->where('id_pengguna', $userId)->where('jumlah', 50000)->first();

        // Approve via admin endpoint
        $res2 = $this->postJson('/api/admin/pencairan/approve', ['id' => $tk->id, 'action' => 'approve', 'admin_id' => 1]);
        $res2->assertStatus(200)->assertJson(['ok' => true]);

        // Assert user's wallet increased (approve credits the dashboard wallet)
        $saldo = DB::table('pengguna')->where('id', $userId)->value('saldo');
        $this->assertEquals(50000, (float)$saldo, 'User wallet must increase by approved amount');

        // assert tabungan_keluar status updated and per-jenis balance decreased
        $this->assertDatabaseHas('tabungan_keluar', ['id' => $tk->id, 'status' => 'approved']);
        $remPerJenis = DB::table('tabungan_masuk')->where('id_pengguna', $userId)->where('id_jenis_tabungan', $jenisId)->sum('jumlah');
        $this->assertEquals(50000, (float)$remPerJenis, 'Per-jenis remaining should be reduced by approved amount');

        // Post-approve: check notification and transaksi rows were created and are not duplicated
        $notiCount = DB::table('notifikasi')->where('id_pengguna', $userId)->where('title', 'Pencairan disetujui')->count();
        $this->assertGreaterThanOrEqual(1, $notiCount, 'Expected at least one approval notification');
        $this->assertLessThanOrEqual(1, $notiCount, 'Duplicate approval notifications detected');

        $txCount = DB::table('transaksi')->where('id_tabungan', $userId)->where('jenis_transaksi', 'pencairan_approved')->where('jumlah', 50000)->count();
        $this->assertEquals(1, $txCount, 'Expected exactly one transaksi row for approval');
    }

    public function test_trigger_prevents_over_withdraw_via_direct_insert()
    {
        $userId = DB::table('pengguna')->insertGetId(['nama_lengkap' => 'Test2', 'saldo' => 0]);
        $jenisId = DB::table('jenis_tabungan')->insertGetId(['nama_jenis' => 'Tabungan Y']);
        DB::table('tabungan_masuk')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 1000, 'status' => 'berhasil', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        try {
            DB::table('tabungan_keluar')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 2000, 'keterangan' => 'direct', 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Saldo tabungan tidak mencukupi', $e->getMessage());
            throw $e;
        }
    }

    public function test_two_pending_approvals_cannot_double_spend()
    {
        $userId = DB::table('pengguna')->insertGetId(['nama_lengkap' => 'Race User', 'saldo' => 0]);
        $jenisId = DB::table('jenis_tabungan')->insertGetId(['nama_jenis' => 'Tabungan Race']);
        DB::table('tabungan_masuk')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 100000, 'status' => 'berhasil', 'created_at' => now(), 'updated_at' => now()]);

        // create two pending withdrawals that together exceed the balance
        $id1 = DB::table('tabungan_keluar')->insertGetId(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 80000, 'status' => 'pending', 'keterangan' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $id2 = DB::table('tabungan_keluar')->insertGetId(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 40000, 'status' => 'pending', 'keterangan' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        // Approve first
        $res1 = $this->postJson('/api/admin/pencairan/approve', ['id' => $id1, 'action' => 'approve', 'admin_id' => 1]);
        $res1->assertStatus(200)->assertJson(['ok' => true]);

        // Approve second -> should fail due to triggers or SQLSTATE 45000 when wallet credit attempted
        $res2 = $this->postJson('/api/admin/pencairan/approve', ['id' => $id2, 'action' => 'approve', 'admin_id' => 1]);
        $res2->assertStatus(400);
        $this->assertStringContainsString('Saldo', $res2->json('message'));
    }

    public function test_approve_deducts_across_multiple_masuk_rows()
    {
        $userId = DB::table('pengguna')->insertGetId(['nama_lengkap' => 'Multi Row User', 'saldo' => 0]);
        $jenisId = DB::table('jenis_tabungan')->insertGetId(['nama_jenis' => 'Tabungan Multi']);

        // insert two incoming rows (oldest 3000, newer 2000)
        DB::table('tabungan_masuk')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 3000, 'status' => 'berhasil', 'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5)]);
        DB::table('tabungan_masuk')->insert(['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'jumlah' => 2000, 'status' => 'berhasil', 'created_at' => now(), 'updated_at' => now()]);

        // request withdrawal that spans both rows (4500)
        $res = $this->postJson('/api/cairkan', ['id_pengguna' => $userId, 'id_jenis_tabungan' => $jenisId, 'nominal' => 4500]);
        $res->assertStatus(200)->assertJson(['ok' => true]);

        $tk = DB::table('tabungan_keluar')->where('id_pengguna', $userId)->where('jumlah', 4500)->first();
        $this->assertNotNull($tk, 'Pending withdrawal row must exist');

        // Approve it
        $res2 = $this->postJson('/api/admin/pencairan/approve', ['id' => $tk->id, 'action' => 'approve', 'admin_id' => 1]);
        $res2->assertStatus(200)->assertJson(['ok' => true]);

        // User dashboard wallet should increase by the approved amount (4500)
        $saldo = DB::table('pengguna')->where('id', $userId)->value('saldo');
        $this->assertEquals(4500, (float)$saldo, 'Dashboard wallet must increase by approved amount');

        // Remaining tabungan_masuk total should be 500
        $rem = DB::table('tabungan_masuk')->where('id_pengguna', $userId)->where('id_jenis_tabungan', $jenisId)->sum('jumlah');
        $this->assertEquals(500, (float)$rem);

        // check notification & transaksi for the approval
        $this->assertDatabaseHas('notifikasi', ['id_pengguna' => $userId, 'title' => 'Pencairan disetujui']);
        $this->assertDatabaseHas('transaksi', ['id_tabungan' => $userId, 'jenis_transaksi' => 'pencairan_approved', 'jumlah' => 4500]);
    }
}
