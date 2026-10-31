<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy tables: tbl_cuti, tbl_jatahcuti, ajuancuti, tbl_ijin, history_ijin, izin_keluar, kalender_libur, ajuanlembur
     */
    public function up(): void
    {
        // 1. Leave Types (tbl_cuti)
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_cuti lama
            $table->string('name', 100);
            $table->unsignedInteger('default_days_allowance')->default(12);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Leave Quotas (tbl_jatahcuti)
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->year('year')->index();
            $table->unsignedInteger('initial_quota')->default(12);
            $table->unsignedInteger('used_quota')->default(0);
            $table->unsignedInteger('remaining_quota')->default(12);
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
            $table->unique(['employee_nip', 'leave_type_id', 'year']);
        });

        // 3. Leave Requests (ajuancuti)
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_cuti lama
            $table->string('employee_nip', 30)->index();
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->date('request_date')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('duration_days');
            $table->text('reason')->nullable();
            $table->string('emergency_address', 255)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            
            $table->unsignedTinyInteger('approval_status')->default(0); // 0=Pending, 1=Approved, 2=Rejected
            $table->string('approved_by_nip', 30)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 4. Permission Types (tbl_ijin)
        Schema::create('permission_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_ijin lama
            $table->string('name', 100);
            $table->boolean('is_salary_deducted')->default(false);
            $table->timestamps();
        });

        // 5. Permission Requests (history_ijin)
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->foreignId('permission_type_id')->constrained('permission_types')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->text('attachment_url')->nullable();
            $table->unsignedTinyInteger('approval_status')->default(0);
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 6. Exit Permits (izin_keluar)
        Schema::create('exit_permits', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->date('date');
            $table->time('exit_time');
            $table->time('return_time')->nullable();
            $table->text('reason');
            $table->unsignedTinyInteger('approval_status')->default(0);
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 7. Holidays (kalender_libur & tbl_libur)
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 150);
            $table->boolean('is_collective_leave')->default(false); // Cuti bersama
            $table->timestamps();
        });

        // 8. Overtime Requests Master (Normalisasi dari ajuanlembur)
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->index(); // kdlembur lama
            $table->string('reference_number', 100)->nullable();
            $table->string('applicant_nip', 30)->index();
            $table->date('request_date')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('duration')->nullable();
            $table->text('reason')->nullable();
            
            // Workflow Hierarki Multi-Approval
            $table->unsignedTinyInteger('approval_status')->default(0);
            $table->string('supervisor_title_1', 100)->nullable();
            $table->string('supervisor_title_2', 100)->nullable();
            $table->string('supervisor_title_3', 100)->nullable();
            $table->unsignedTinyInteger('supervisor_status_1')->default(0);
            $table->unsignedTinyInteger('supervisor_status_2')->default(0);
            $table->unsignedTinyInteger('supervisor_status_3')->default(0);
            $table->dateTime('supervisor_approved_at_1')->nullable();
            $table->dateTime('supervisor_approved_at_2')->nullable();
            $table->dateTime('supervisor_approved_at_3')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamps();
            $table->foreign('applicant_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 9. Overtime Participants Detail (Unpivot npp1..30, tgs1..30, jam1..30)
        Schema::create('overtime_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained('overtime_requests')->onDelete('cascade');
            $table->string('employee_nip', 30)->index();
            $table->string('task_description', 255)->nullable();
            $table->time('duration')->nullable();
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_participants');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('exit_permits');
        Schema::dropIfExists('permission_requests');
        Schema::dropIfExists('permission_types');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_quotas');
        Schema::dropIfExists('leave_types');
    }
};
