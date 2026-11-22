<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 Sunday .. 6 Saturday
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 80)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'classroom_id', 'weekday']);
            $table->index(['school_id', 'teacher_profile_id', 'weekday']);
        });
    }

    public function down(): void { Schema::dropIfExists('class_schedule_entries'); }
};
