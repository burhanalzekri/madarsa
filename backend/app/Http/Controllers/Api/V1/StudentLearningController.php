<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentLearningController extends Controller
{
    private function studentId(Request $request): int
    {
        $profile = DB::table('student_profiles')->where('school_id', CurrentSchool::id())
            ->where('user_id', $request->user()->id)->first();
        if (!$profile) abort(403, 'هذا الحساب غير مرتبط بملف طالب.');
        return (int) $profile->id;
    }

    public function dashboard(Request $request)
    {
        $studentId = $this->studentId($request);
        $schoolId = CurrentSchool::id();
        $progress = DB::table('lesson_progress')->where('school_id', $schoolId)->where('student_profile_id', $studentId);
        return response()->json([
            'progress' => [
                'completed_lessons' => (clone $progress)->whereNotNull('completed_at')->count(),
                'started_lessons' => (clone $progress)->count(),
                'average_completion' => (int) round((clone $progress)->avg('completion_percent') ?? 0),
            ],
            'assignments' => DB::table('assignments as a')
                ->join('student_enrollments as e', 'e.classroom_id', '=', 'a.classroom_id')
                ->leftJoin('assignment_submissions as s', function ($join) use ($studentId) {
                    $join->on('s.assignment_id', '=', 'a.id')->where('s.student_profile_id', '=', $studentId);
                })
                ->where('a.school_id', $schoolId)->where('e.student_profile_id', $studentId)
                ->where('a.is_published', true)->where('e.status', 'active')
                ->select('a.id','a.title','a.instructions','a.due_at','a.max_score','s.answer_text','s.submitted_at','s.score','s.teacher_feedback')->orderBy('a.due_at')->limit(10)->get(),
            'upcoming_exams' => DB::table('exams as x')
                ->join('student_enrollments as e', 'e.classroom_id', '=', 'x.classroom_id')
                ->where('x.school_id', $schoolId)->where('e.student_profile_id', $studentId)
                ->where('x.is_published', true)->where('e.status', 'active')
                ->whereNotExists(function ($q) use ($studentId) {
                    $q->select(DB::raw(1))->from('exam_attempts as ea')->whereColumn('ea.exam_id','x.id')
                        ->where('ea.student_profile_id',$studentId)->whereIn('ea.status',['submitted','submitted_for_review','graded']);
                })
                ->select('x.id','x.title','x.starts_at','x.duration_minutes','x.total_score')->orderBy('x.starts_at')->limit(10)->get(),
        ]);
    }

    public function saveProgress(Request $request, int $lessonId)
    {
        $data = $request->validate(['completion_percent' => ['required','integer','min:0','max:100']]);
        $studentId = $this->studentId($request); $schoolId = CurrentSchool::id();
        $lesson = DB::table('lessons as l')->join('curriculum_units as cu', 'cu.id', '=', 'l.curriculum_unit_id')
            ->join('grade_subjects as gs', 'gs.id', '=', 'cu.grade_subject_id')
            ->where('l.id', $lessonId)->where('l.is_published', true)->select('gs.grade_id')->first();
        if (!$lesson) abort(404);
        $enrolled = DB::table('student_enrollments as e')->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->where('e.school_id', $schoolId)->where('e.student_profile_id', $studentId)->where('e.status', 'active')
            ->where('c.grade_id', $lesson->grade_id)->exists();
        abort_unless($enrolled, 403, 'هذا الدرس غير متاح لصفك الدراسي.');
        $now = now();
        $key = ['school_id' => $schoolId, 'student_profile_id' => $studentId, 'lesson_id' => $lessonId];
        $existing = DB::table('lesson_progress')->where($key)->first();
        DB::table('lesson_progress')->updateOrInsert($key, [
            'completion_percent' => max((int) ($existing->completion_percent ?? 0), $data['completion_percent']),
            'started_at' => $existing->started_at ?? $now, 'last_viewed_at' => $now,
            'completed_at' => ($data['completion_percent'] === 100 || ($existing->completion_percent ?? 0) == 100) ? ($existing->completed_at ?? $now) : null,
            'updated_at' => $now, 'created_at' => $existing->created_at ?? $now,
        ]);
        return response()->json(['message' => 'تم حفظ تقدم الدرس.', 'completion_percent' => $data['completion_percent']]);
    }

    public function submitAssignment(Request $request, int $assignmentId)
    {
        $studentId = $this->studentId($request); $schoolId = CurrentSchool::id();
        $data = $request->validate(['answer_text' => ['nullable','string','max:20000']]);
        $assignment = DB::table('assignments')->where('id',$assignmentId)->where('school_id',$schoolId)->where('is_published',true)->first();
        if (!$assignment) abort(404);
        $enrolled = DB::table('student_enrollments')->where('student_profile_id',$studentId)
            ->where('classroom_id',$assignment->classroom_id)->where('status','active')->exists();
        if (!$enrolled) abort(403, 'هذا الواجب غير مخصص لشعبتك.');
        if ($assignment->due_at && now()->gt($assignment->due_at)) {
            throw ValidationException::withMessages(['assignment' => ['انتهى موعد تسليم الواجب.']]);
        }
        $existing = DB::table('assignment_submissions')->where('assignment_id', $assignmentId)
            ->where('student_profile_id', $studentId)->first();
        if ($existing && ($existing->graded_by !== null || $existing->score !== null)) {
            throw ValidationException::withMessages(['assignment' => ['تم اعتماد هذا الواجب من المعلم ولا يمكن تعديل التسليم.']]);
        }
        DB::table('assignment_submissions')->updateOrInsert(
            ['assignment_id'=>$assignmentId,'student_profile_id'=>$studentId],
            ['answer_text'=>$data['answer_text'] ?? null,'submitted_at'=>now(),'updated_at'=>now(),'created_at'=>$existing->created_at ?? now()]
        );
        return response()->json(['message'=>'تم تسليم الواجب بنجاح.']);
    }
}
