<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parent_student_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 40)->default('ولي أمر');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['parent_user_id', 'student_profile_id']);
            $table->index(['school_id', 'student_profile_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('parent_student_links'); }
};
