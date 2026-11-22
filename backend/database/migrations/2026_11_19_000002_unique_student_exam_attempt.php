<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Keep the latest attempt per student/exam before enforcing one attempt.
        $duplicates = DB::table('exam_attempts')->select('exam_id','student_profile_id')
            ->groupBy('exam_id','student_profile_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($duplicates as $group) {
            $keep = DB::table('exam_attempts')->where('exam_id',$group->exam_id)
                ->where('student_profile_id',$group->student_profile_id)->max('id');
            DB::table('exam_attempts')->where('exam_id',$group->exam_id)
                ->where('student_profile_id',$group->student_profile_id)->where('id','<>',$keep)->delete();
        }
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unique(['exam_id','student_profile_id'], 'exam_attempts_exam_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropUnique('exam_attempts_exam_student_unique');
        });
    }
};
