<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class PencairanController extends Controller
{
    // GET /api/admin/pencairan (DataTables server-side example)
    public function index(Request $req)
    {
        $start = (int)$req->input('start', 0);
        $length = (int)$req->input('length', 10);
        $search = $req->input('search')['value'] ?? '';
        $status = $req->input('status', 'pending');

        $query = DB::table('tabungan_keluar as tk')
            ->join('pengguna as p', 'p.id', 'tk.id_pengguna')
            ->select('tk.id', 'tk.id_pengguna', 'p.nama_lengkap', 'tk.created_at', 'tk.jumlah', 'tk.status', 'tk.keterangan');

        if ($status !== 'all') {
            if ($status === 'pending') {
                $query->where(function($q){
                    $q->where('tk.status', 'pending')->orWhere('tk.keterangan', 'like', '%pending%');
                });
            } else {
                $query->where('tk.status', $status);
            }
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('p.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('tk.id', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $query->count();
        $data = $query->orderBy('tk.created_at', 'desc')->skip($start)->take($length)->get();

        // Format DataTables response
        $out = [
            'draw' => intval($req->input('draw', 1)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data
        ];

        return response()->json($out);
    }

    // POST /api/admin/pencairan/approve
    public function approve(Request $req)
    {
        $req->validate([ 'id' => 'required|integer', 'action' => 'required|in:approve,reject', 'admin_id' => 'required|integer' ]);
        $id = (int)$req->input('id');
        $action = $req->input('action');
        $adminId = (int)$req->input('admin_id');
        $note = $req->input('note', '');

        try {
            $result = DB::transaction(function () use ($id, $action, $adminId, $note) {
                // Lock the withdrawal row for update
                $tk = DB::table('tabungan_keluar')->where('id', $id)->lockForUpdate()->first();
                if (!$tk) throw new \Exception('Data penarikan tidak ditemukan');

                // Resolve user and lock pengguna
                $user = DB::table('pengguna')->where('id', $tk->id_pengguna)->lockForUpdate()->first();
                if (!$user) throw new \Exception('Pengguna tidak ditemukan');

                if ($action === 'approve') {
                    // Validate still sufficient per triggers, wallet mutates here
                    // Update withdrawal status
                    DB::table('tabungan_keluar')->where('id', $id)->update([
                        'status' => 'approved',
                        'keterangan' => ($tk->keterangan ?? '') . ' (approved by ' . $adminId . ')',
                        'updated_at' => now()
                    ]);

                    // Credit user's wallet (authoritative wallet mutation at approval)
                    DB::table('pengguna')->where('id', $tk->id_pengguna)->update(['saldo' => DB::raw('saldo + ' . (float)$tk->jumlah)]);

                    // Audit
                    DB::table('transaksi')->insert([
                        'no_keluar' => 'TK-' . now()->format('YmdHis') . '-' . $id,
                        'nama' => $user->nama_lengkap,
                        'id_tabungan' => $user->id,
                        'kegiatan' => 'Pencairan Disetujui',
                        'jumlah_keluar' => $tk->jumlah,
                        'tanggal' => now(),
                        'petugas' => 'Admin:' . $adminId,
                        'created_at' => now()
                    ]);

                    $newSaldo = DB::table('pengguna')->where('id', $tk->id_pengguna)->value('saldo');
                    return ['ok' => true, 'message' => 'Penarikan disetujui', 'saldo' => $newSaldo];
                } else {
                    // Reject
                    DB::table('tabungan_keluar')->where('id', $id)->update([
                        'status' => 'rejected',
                        'keterangan' => ($tk->keterangan ?? '') . ' (rejected: ' . $note . ')',
                        'updated_at' => now()
                    ]);
                    return ['ok' => true, 'message' => 'Penarikan ditolak'];
                }
            });

            return response()->json($result);
        } catch (QueryException $e) {
            $sqlstate = $e->errorInfo[0] ?? null;
            if ($sqlstate === '45000') {
                return response()->json(['ok' => false, 'message' => $e->errorInfo[2] ?? 'Saldo tabungan tidak mencukupi'], 400);
            }
            \Log::error('DB error PencairanController@approve: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Gagal memproses approval'], 500);
        } catch (\Exception $e) {
            \Log::error('Error approving: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
