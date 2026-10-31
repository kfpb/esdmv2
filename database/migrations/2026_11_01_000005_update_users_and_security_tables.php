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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_nip', 30)->nullable()->unique()->after('id')->index();
            $table->string('username', 50)->nullable()->unique()->after('employee_nip');
            $table->string('role', 50)->default('employee')->after('password'); // admin, director, hrd, supervisor, employee
            $table->boolean('is_active')->default(true)->after('role');
            $table->text('photo_url')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('set null');
        });

        // Trusted Devices (user_trusted_device)
        Schema::create('user_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('device_id', 150);
            $table->string('device_name', 100)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->boolean('is_trusted')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
            $table->unique(['employee_nip', 'device_id']);
        });

        // Audit Logs (audit_trail)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('employee_nip', 30)->nullable()->index();
            $table->string('event', 50); // created, updated, deleted, login, approval
            $table->string('auditable_type', 150)->nullable();
            $table->string('auditable_id', 50)->nullable();
            $table->text('description')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_trusted_devices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_nip']);
            $table->dropColumn(['employee_nip', 'username', 'role', 'is_active', 'photo_url', 'last_login_at', 'last_login_ip']);
        });
    }
};
