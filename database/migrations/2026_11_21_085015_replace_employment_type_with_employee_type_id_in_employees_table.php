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
            // drop kolom employment_type lama
            $table->dropColumn('employment_type');
            // add kolom employee_type_id baru yang mengacu ke tabel employee_types
            $table->foreignId('employee_type_id')
                ->nullable()
                ->after('employee_status_id')
                ->constrained('employee_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['employee_type_id']);
            $table->dropColumn('employee_type_id');
            $table->string('employment_type', 150)->after('employee_status_id');
        });
    }
};
