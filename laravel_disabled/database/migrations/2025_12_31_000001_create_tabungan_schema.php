<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateTabunganSchema extends Migration
{
    /**
     * Run the migrations.
     * We rely on DB::unprepared to run the complex trigger SQL and view.
     */
    public function up()
    {
        $sql = file_get_contents(database_path('migrations/sql/001_create_tabungan_schema.sql'));
        // DB::unprepared accepts a string containing multiple statements
        DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Rollback carefully: remove view and triggers first then tables
        DB::unprepared('DROP VIEW IF EXISTS v_saldo_tabungan');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_delete');

        DB::statement('DROP TABLE IF EXISTS saldo_audit');
        DB::statement('DROP TABLE IF EXISTS transaksi');
        DB::statement('DROP TABLE IF EXISTS tabungan_keluar');
        DB::statement('DROP TABLE IF EXISTS tabungan_masuk');
        DB::statement('DROP TABLE IF EXISTS jenis_tabungan');
        DB::statement('DROP TABLE IF EXISTS pengguna');
    }
}
