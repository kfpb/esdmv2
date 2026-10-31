<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy tables: tbl_absen, tbl_group_shift, tbl_group_absen, absensi, absensi_wajah
     */
    public function up(): void
    {
        // 1. Work Shifts (tbl_absen)
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_abs lama
            $table->string('code', 50)->nullable();
            $table->string('name', 100);
            $table->time('check_in_time');
            $table->time('check_out_time');
            $table->time('late_tolerance_time')->nullable();
            $table->time('check_in_start_time')->nullable();
            $table->time('check_in_end_time')->nullable();
            $table->time('check_out_start_time')->nullable();
            $table->time('check_out_end_time')->nullable();
            $table->boolean('is_night_shift')->default(false);
            $table->timestamps();
        });

        // 2. Shift Groups (tbl_group_shift)
        Schema::create('shift_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. Shift Group Schedules (tbl_group_absen)
        Schema::create('shift_group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_group_id')->constrained('shift_groups')->onDelete('cascade');
            $table->foreignId('work_shift_id')->constrained('work_shifts')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 1=Mon .. 7=Sun
            $table->timestamps();
        });

        // 4. Attendances (absensi)
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_absensi lama
            $table->string('employee_nip', 30)->index();
            $table->date('attendance_date')->index();
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->onDelete('set null');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            
            $table->boolean('is_present_in')->default(false); // status_masuk
            $table->boolean('is_present_out')->default(false); // status_keluar
            $table->boolean('is_late')->default(false); // terlambat
            $table->boolean('is_early_leave')->default(false); // pulangcepat
            
            // GPS Location
            $table->string('check_in_latitude', 30)->nullable();
            $table->string('check_in_longitude', 30)->nullable();
            $table->string('check_out_latitude', 30)->nullable();
            $table->string('check_out_longitude', 30)->nullable();
            
            // References to permissions & overtime
            $table->unsignedBigInteger('permission_type_id')->nullable()->index();
            $table->unsignedBigInteger('overtime_request_id')->nullable()->index();
            $table->boolean('is_overtime')->default(false);
            $table->boolean('is_overtime_verified')->default(false);
            $table->string('overtime_duration', 50)->nullable();
            
            // Financial Decimals (clean converted from varchar 15)
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('deduction_amount', 15, 2)->default(0);

            $table->string('work_mode', 20)->default('WFO'); // WFO / WFH
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_nip', 'attendance_date']);
            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 5. Face Biometrics (absensi_wajah)
        Schema::create('face_biometrics', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('set null');
            $table->date('recorded_date')->nullable()->index();
            $table->time('recorded_time')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('image_url')->nullable();
            $table->longText('raw_descriptor')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_biometrics');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shift_group_schedules');
        Schema::dropIfExists('shift_groups');
        Schema::dropIfExists('work_shifts');
    }
};
