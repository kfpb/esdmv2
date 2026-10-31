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
        // 1. Families (keluarga)
        Schema::create('employee_families', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('name', 150);
            $table->string('relationship', 50); // Spouse, Child, Parent
            $table->enum('gender', ['Male', 'Female'])->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('highest_education', 50)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('insurance_number', 50)->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 2. Education (pendidikan)
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('level', 30); // High School, Bachelor, Master, Doctorate
            $table->string('institution_name', 150);
            $table->string('major', 100)->nullable();
            $table->string('entry_year', 10)->nullable();
            $table->string('graduation_year', 10)->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('certificate_file_url')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 3. Trainings (pelatihan)
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('title', 200);
            $table->text('organizer')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('duration_hours')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('certificate_file_url')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 4. Experiences (pengalaman_kerja)
        Schema::create('employee_experiences', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('company_name', 150);
            $table->string('last_position', 100);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('job_description')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 5. Awards (penghargaan)
        Schema::create('employee_awards', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('title', 200);
            $table->string('issuer', 150)->nullable();
            $table->date('awarded_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 6. Documents (tbl_upload_berkas)
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->string('document_type', 100); // National_ID, Family_Card, Diploma, Decree
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
        });

        // 7. Career Histories (h_jabatan, h_jbstruk, h_jbfungsi)
        Schema::create('employee_position_histories', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip', 30)->index();
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('set null');
            $table->string('grade_code', 10)->nullable()->index();
            $table->string('decree_number', 100)->nullable();
            $table->date('decree_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_nip')->references('nip')->on('employees')->onDelete('cascade');
            $table->foreign('grade_code')->references('code')->on('grades')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_position_histories');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_awards');
        Schema::dropIfExists('employee_experiences');
        Schema::dropIfExists('employee_trainings');
        Schema::dropIfExists('employee_educations');
        Schema::dropIfExists('employee_families');
    }
};
