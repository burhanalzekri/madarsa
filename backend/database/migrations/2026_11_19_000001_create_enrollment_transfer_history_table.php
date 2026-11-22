<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('enrollment_transfer_history', function(Blueprint $t){$t->id();$t->foreignId('school_id')->constrained()->cascadeOnDelete();$t->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();$t->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();$t->foreignId('from_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();$t->foreignId('to_classroom_id')->constrained('classrooms')->cascadeOnDelete();$t->foreignId('academic_year_id')->constrained()->cascadeOnDelete();$t->foreignId('performed_by')->constrained('users')->cascadeOnDelete();$t->string('transfer_type',20)->default('transfer');$t->text('reason')->nullable();$t->timestamps();$t->index(['school_id','student_enrollment_id']);}); }
 public function down(): void { Schema::dropIfExists('enrollment_transfer_history'); }
};
