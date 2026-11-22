<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->longText('instructions')->nullable();
            $t->timestamp('due_at')->nullable(); $t->decimal('max_score',8,2)->default(100)->unsigned();
            $t->boolean('is_published')->default(false); $t->timestamps();
            $t->index(['school_id','classroom_id','is_published']);
        });
        Schema::create('assignment_submissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $t->longText('answer_text')->nullable(); $t->string('attachment_path')->nullable();
            $t->timestamp('submitted_at')->nullable(); $t->decimal('score',8,2)->nullable()->unsigned();
            $t->text('teacher_feedback')->nullable(); $t->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->unique(['assignment_id','student_profile_id']);
        });
        Schema::create('exams', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->text('instructions')->nullable(); $t->timestamp('starts_at')->nullable();
            $t->unsignedInteger('duration_minutes')->default(30); $t->decimal('total_score',8,2)->default(100)->unsigned();
            $t->boolean('is_published')->default(false); $t->timestamps();
        });
        Schema::create('exam_questions', function (Blueprint $t) {
            $t->id(); $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->string('question_type',30); $t->longText('question_text'); $t->json('options')->nullable();
            $t->string('correct_answer')->nullable(); $t->decimal('score',8,2)->default(1)->unsigned();
            $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('exam_attempts', function (Blueprint $t) {
            $t->id(); $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $t->json('answers')->nullable(); $t->decimal('score',8,2)->nullable()->unsigned();
            $t->timestamp('started_at')->nullable(); $t->timestamp('submitted_at')->nullable();
            $t->string('status',20)->default('in_progress'); $t->timestamps();
            $t->index(['exam_id','student_profile_id']);
        });
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('classroom_id')->constrained()->cascadeOnDelete(); $t->date('attendance_date');
            $t->string('status',20); $t->text('notes')->nullable(); $t->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $t->timestamps(); $t->unique(['student_profile_id','attendance_date']);
        });
        Schema::create('live_sessions', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_subject_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->string('provider',30)->default('bigbluebutton');
            $t->string('provider_meeting_id')->nullable()->unique(); $t->timestamp('starts_at');
            $t->timestamp('ended_at')->nullable(); $t->string('recording_url')->nullable();
            $t->string('status',20)->default('scheduled'); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('live_sessions'); Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('exam_attempts'); Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams'); Schema::dropIfExists('assignment_submissions'); Schema::dropIfExists('assignments');
    }
};
