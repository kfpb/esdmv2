<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // drop kolom employment_status lama
            $table->dropColumn('employment_status');

            // add kolom employee_status_id baru yang mengacu ke tabel employee_statuses
            $table->foreignId('employee_status_id')
                ->nullable()
                ->after('employment_type')
                ->constrained('employee_statuses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['employee_status_id']);
            $table->dropColumn('employee_status_id');
            $table->string('employment_status', 150)->after('employment_type');
        });
    }
};
