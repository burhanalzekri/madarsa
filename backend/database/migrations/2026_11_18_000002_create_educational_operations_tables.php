<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('short_name');
            $table->string('school_type')->nullable();
            $table->string('educational_level')->nullable();
            $table->string('governorate')->nullable();
            $table->string('district')->nullable();
            $table->string('website')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('platform_name')->default('مدرستي');
            $table->boolean('setup_completed')->default(false);
            $table->timestamp('setup_locked_at')->nullable();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // أ، ب، ج
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['grade_id', 'academic_year_id', 'name']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('student_number')->unique();
            $table->string('national_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('active');
            $table->date('admission_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'user_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number')->unique();
            $table->string('specialization')->nullable();
            $table->string('qualification')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->text('bio')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'user_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->date('enrolled_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['student_profile_id', 'academic_year_id']);
            $table->index(['school_id', 'classroom_id', 'status']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_profile_id', 'grade_subject_id', 'classroom_id', 'academic_year_id'], 'teacher_assignment_unique');
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create('educational_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('resource_type', 30); // pdf, document, image, video, link
            $table->string('disk')->default('private');
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_published')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'resource_type', 'is_published']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_profile_id', 'lesson_id']);
            $table->index(['school_id', 'student_profile_id']);
        });

        Schema::create('guardian_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['student_profile_id', 'guardian_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_links');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('educational_resources');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('classrooms');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'description', 'school_type', 'educational_level', 'governorate', 'district', 'website', 'cover_path', 'platform_name', 'setup_completed', 'setup_locked_at']);
        });
    }
};
