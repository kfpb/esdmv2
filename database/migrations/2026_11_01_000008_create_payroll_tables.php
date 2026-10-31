<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy tables: tbl_gaji, tbl_payroll
     */
    public function up(): void
    {
        // 1. Salary Structures (tbl_gaji)
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->unique()->index();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_holder', 150)->nullable();
            
            // Earnings (decimal)
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('position_allowance', 15, 2)->default(0);
            $table->decimal('family_allowance', 15, 2)->default(0);
            $table->decimal('attendance_allowance', 15, 2)->default(0);
            $table->decimal('performance_allowance', 15, 2)->default(0);
            
            // Deductions
            $table->decimal('health_insurance_deduction', 15, 2)->default(0);
            $table->decimal('employment_insurance_deduction', 15, 2)->default(0);
            $table->decimal('tax_deduction', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);

            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 2. Payroll Payments (tbl_payroll)
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->string('slip_number', 100)->unique();
            $table->string('employee_nip', 30)->index();
            $table->unsignedTinyInteger('month')->index();
            $table->year('year')->index();
            
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            
            $table->string('payment_status', 30)->default('Draft'); // Draft, Approved, Paid
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
            $table->unique(['employee_nip', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('salary_structures');
    }
};
