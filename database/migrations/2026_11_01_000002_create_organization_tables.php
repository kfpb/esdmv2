<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy tables: bagian, sub_bagian, jabatan, golongan, tbl_gol, jb_struk, jb_fungsi, tbl_struktur
     */
    public function up(): void
    {
        // 1. Divisions / Departments (bagian)
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_bagian lama
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Sub-Divisions / Units (sub_bagian)
        Schema::create('sub_divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_subbagian lama
            $table->foreignId('division_id')->constrained('divisions')->onDelete('cascade');
            $table->string('code', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Positions / Job Roles (jabatan)
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_jabatan lama
            $table->string('code', 50)->nullable();
            $table->string('name', 150);
            $table->enum('type', ['Structural', 'Functional', 'General'])->default('General');
            $table->unsignedInteger('level')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Grades / Ranks (golongan & tbl_gol)
        Schema::create('grades', function (Blueprint $table) {
            $table->string('code', 10)->primary(); // e.g. 'IV/a', 'III/b' (kdgol)
            $table->string('name', 50)->nullable();
            $table->string('rank', 100)->nullable(); // Pangkat
            $table->decimal('standard_base_salary', 15, 2)->default(0);
            $table->timestamps();
        });

        // 5. Structural Positions Master (jb_struk)
        Schema::create('structural_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedInteger('echelon')->nullable(); // Eselon
            $table->timestamps();
        });

        // 6. Functional Positions Master (jb_fungsi)
        Schema::create('functional_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('tier', 50)->nullable(); // Jenjang
            $table->timestamps();
        });

        // 7. Organization Hierarchy Structure (tbl_struktur)
        Schema::create('organization_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organization_structures')->onDelete('set null');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('set null');
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->string('title', 150);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_structures');
        Schema::dropIfExists('functional_positions');
        Schema::dropIfExists('structural_positions');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('sub_divisions');
        Schema::dropIfExists('divisions');
    }
};
