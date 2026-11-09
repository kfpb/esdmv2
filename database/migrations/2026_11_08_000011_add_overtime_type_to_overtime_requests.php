<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom overtime_type ke tabel overtime_requests.
     * Memetakan kolom id_lembur dari tabel legacy ajuanlembur (JOIN tbl_lembur).
     * 4 jenis lembur KFPB: 1=Hari Kerja, 2=Sabtu/Minggu, 3=Hari Besar/Raya, 4=Flextime.
     */
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('overtime_type')->default(1)->after('applicant_nip')
                  ->comment('1=Lembur Hari Kerja, 2=Lembur Sabtu/Minggu, 3=Lembur Hari Besar/Raya, 4=Lembur Flextime');
        });

        // Seed master jenis lembur (tbl_lembur legacy): pastikan data tbl_lembur tersedia via overtime_types
        // Data ini tidak memerlukan tabel baru karena digunakan sebagai enum KFPB yang statis.
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn('overtime_type');
        });
    }
};
