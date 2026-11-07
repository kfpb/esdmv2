<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            // drop kolom document_type lama
            $table->dropColumn('document_type');

            // add kolom document_id baru yang mengacu ke tabel documents
            $table->foreignId('document_id')
                ->nullable()
                ->after('employee_nip')
                ->constrained('documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');

            $table->string('document_type');
        });
    }
};