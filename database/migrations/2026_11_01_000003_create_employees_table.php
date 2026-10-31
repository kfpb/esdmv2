<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy table: pegawai
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique()->index(); // Employee Registration Number
            $table->string('nik', 30)->nullable()->index(); // National ID Number
            $table->string('full_name', 150);
            $table->string('front_title', 30)->nullable();
            $table->string('back_title', 50)->nullable();
            $table->enum('gender', ['Male', 'Female'])->default('Male');
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('religion_id')->nullable()->constrained('religions')->onDelete('set null');
            $table->string('marital_status', 30)->nullable();
            $table->unsignedTinyInteger('children_count')->default(0);
            
            // Address & Contacts
            $table->text('address')->nullable();
            $table->string('neighborhood_unit', 20)->nullable(); // RT/RW
            $table->string('province_id', 10)->nullable()->index();
            $table->string('regency_id', 10)->nullable()->index();
            $table->string('district_id', 10)->nullable()->index();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->string('email', 100)->nullable()->index();

            // Placement & Employment
            $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('set null');
            $table->foreignId('sub_division_id')->nullable()->constrained('sub_divisions')->onDelete('set null');
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->string('grade_code', 10)->nullable()->index();
            $table->string('employment_type', 50)->nullable(); // Permanent, Contract, Intern
            $table->string('employment_status', 50)->nullable(); // Civil Servant, Non-Civil Servant
            $table->string('activity_status', 30)->default('Active')->index(); // Active, Retired, Resigned, On Leave
            
            // Career Dates & Social Security
            $table->date('join_date')->nullable();
            $table->date('decree_date')->nullable(); // Tanggal SK
            $table->date('retirement_date')->nullable();
            $table->string('health_insurance_number', 50)->nullable(); // BPJS Kesehatan
            $table->string('employment_insurance_number', 50)->nullable(); // BPJS Ketenagakerjaan
            $table->string('tax_number', 50)->nullable(); // NPWP
            
            // Photo & Biometric Face ID
            $table->text('photo_url')->nullable();
            $table->string('face_id', 100)->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('grade_code')->references('code')->on('grades')->onDelete('set null');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('regency_id')->references('id')->on('regencies')->onDelete('set null');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
