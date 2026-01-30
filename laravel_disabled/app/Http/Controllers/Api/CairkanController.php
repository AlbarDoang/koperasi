<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class CairkanController extends Controller
{
    // POST /api/cairkan
    public function store(Request $req)
    {
        $req->validate([
            'id_pengguna' => 'required|integer',
            'id_jenis_tabungan' => 'required|integer',
            'nominal' => 'required|numeric|min:1'
        ]);

        $userId = (int)$req->input('id_pengguna');
        $jenisId = (int)$req->input('id_jenis_tabungan');
        $nominal = (float)$req->input('nominal');
        $note = $req->input('keterangan', '');

        try {
            $result = DB::transaction(function () use ($userId, $jenisId, $nominal, $note) {
                // lock the user row to serialize operations per-user
                $user = DB::table('pengguna')->where('id', $userId)->lockForUpdate()->first();
                if (!$user) {
                    throw new \Exception('Pengguna tidak ditemukan');
                }

                // compute available per-jenis
                $totalIn = DB::table('tabungan_masuk')
                    ->where('id_pengguna', $userId)
                    ->where('id_jenis_tabungan', $jenisId)
                    ->where(function($q){ $q->whereNull('status')->orWhere('status','berhasil'); })
                    ->sum('jumlah');

                // When `status` column exists on tabungan_keluar we only count APPROVED withdrawals
                $queryOut = DB::table('tabungan_keluar')
                    ->where('id_pengguna', $userId)
                    ->where('id_jenis_tabungan', $jenisId);
                if (\Illuminate\Support\Facades\Schema::hasColumn('tabungan_keluar', 'status')) {
                    $queryOut = $queryOut->where('status', 'approved');
                }
                $totalOut = $queryOut->sum('jumlah');

                $available = (float)$totalIn - (float)$totalOut;
                if ($available < $nominal) {
                    throw new \Exception('Saldo tabungan tidak mencukupi');
                }

                DB::table('tabungan_keluar')->insert([
                    'id_pengguna' => $userId,
                    'id_jenis_tabungan' => $jenisId,
                    'jumlah' => $nominal,
                    'status' => 'pending',
                    'keterangan' => 'pending: ' . $note,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return ['ok' => true, 'message' => 'Permintaan pencairan diajukan', 'available' => $available];
            });

            return response()->json($result);
        } catch (QueryException $e) {
            $sqlstate = $e->errorInfo[0] ?? null;
            if ($sqlstate === '45000') {
                return response()->json(['ok' => false, 'message' => $e->errorInfo[2] ?? 'Saldo tabungan tidak mencukupi'], 400);
            }
            \Log::error('DB error CairkanController@store: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Gagal memproses permintaan'], 500);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
