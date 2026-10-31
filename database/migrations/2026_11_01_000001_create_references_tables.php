<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy tables: tbl_agama, tbl_bank, prov, kabkot, kec
     */
    public function up(): void
    {
        // 1. Religions (tbl_agama)
        Schema::create('religions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_agama lama
            $table->string('name', 50);
            $table->timestamps();
        });

        // 2. Banks (tbl_bank)
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_bank lama
            $table->string('code', 20)->nullable();
            $table->string('name', 100);
            $table->string('branch', 100)->nullable();
            $table->timestamps();
        });

        // 3. Provinces (prov)
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // e.g. '31', '32'
            $table->string('name', 100);
            $table->timestamps();
        });

        // 4. Regencies / Cities (kabkot)
        Schema::create('regencies', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // e.g. '3171'
            $table->string('province_id', 10)->index();
            $table->string('name', 100);
            $table->timestamps();

            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
        });

        // 5. Districts (kec)
        Schema::create('districts', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('regency_id', 10)->index();
            $table->string('name', 100);
            $table->timestamps();

            $table->foreign('regency_id')->references('id')->on('regencies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('religions');
    }
};
