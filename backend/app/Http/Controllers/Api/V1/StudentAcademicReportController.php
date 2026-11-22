<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAcademicReportController extends Controller
{
    public function student(Request $request)
    {
        $profile = DB::table('student_profiles')
            ->where('school_id', CurrentSchool::id())
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($profile, 404, 'ملف الطالب غير موجود.');
        return response()->json($this->buildReport((int) $profile->id));
    }

    public function parent(Request $request, int $studentId)
    {
        $schoolId = CurrentSchool::id();
        $linked = DB::table('parent_student_links')
            ->where('school_id', $schoolId)
            ->where('parent_user_id', $request->user()->id)
            ->where('student_profile_id', $studentId)
            ->exists();

        abort_unless($linked, 404, 'لا يوجد طالب مرتبط بهذا الحساب.');
        return response()->json($this->buildReport($studentId));
    }

    private function buildReport(int $studentId): array
    {
        $schoolId = CurrentSchool::id();
        $student = DB::table('student_profiles as sp')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->where('sp.id', $studentId)->where('sp.school_id', $schoolId)
            ->select('u.name', 'sp.student_number')->first();
        abort_unless($student, 404, 'ملف الطالب غير موجود في هذه المدرسة.');

        $attendanceRows = DB::table('attendance_records')
            ->where('school_id', $schoolId)->where('student_profile_id', $studentId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        $examRows = DB::table('exam_attempts as a')
            ->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->join('grade_subjects as gs', 'gs.id', '=', 'e.grade_subject_id')
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->where('e.school_id', $schoolId)->where('a.student_profile_id', $studentId)
            ->where('a.status', 'graded')->whereNotNull('a.score')
            ->select('s.id as subject_id', 's.name as subject', DB::raw('COUNT(*) as exams_count'), DB::raw('AVG((a.score / NULLIF(e.total_score, 0)) * 100) as average_percent'))
            ->groupBy('s.id', 's.name')->get()->keyBy('subject_id');

        $assignmentRows = DB::table('assignment_submissions as sub')
            ->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->join('grade_subjects as gs', 'gs.id', '=', 'a.grade_subject_id')
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->where('a.school_id', $schoolId)->where('sub.student_profile_id', $studentId)
            ->whereNotNull('sub.score')
            ->select('s.id as subject_id', 's.name as subject', DB::raw('COUNT(*) as assignments_count'), DB::raw('AVG((sub.score / NULLIF(a.max_score, 0)) * 100) as average_percent'))
            ->groupBy('s.id', 's.name')->get()->keyBy('subject_id');

        $subjectIds = $examRows->keys()->merge($assignmentRows->keys())->unique();
        $subjects = $subjectIds->map(function ($id) use ($examRows, $assignmentRows) {
            $exam = $examRows->get($id);
            $assignment = $assignmentRows->get($id);
            $examAverage = $exam ? (float) $exam->average_percent : null;
            $assignmentAverage = $assignment ? (float) $assignment->average_percent : null;
            $averages = array_values(array_filter([$examAverage, $assignmentAverage], fn ($value) => $value !== null));
            return [
                'subject' => $exam->subject ?? $assignment->subject,
                'exams_count' => (int) ($exam->exams_count ?? 0),
                'assignments_count' => (int) ($assignment->assignments_count ?? 0),
                'exam_average' => $examAverage === null ? null : round($examAverage, 2),
                'assignment_average' => $assignmentAverage === null ? null : round($assignmentAverage, 2),
                'overall_average' => count($averages) ? round(array_sum($averages) / count($averages), 2) : null,
            ];
        })->values();

        $gradedAverages = $subjects->pluck('overall_average')->filter(fn ($value) => $value !== null);
        return [
            'student' => ['name' => $student->name, 'student_number' => $student->student_number],
            'attendance' => [
                'present' => (int) ($attendanceRows['present'] ?? 0),
                'absent' => (int) ($attendanceRows['absent'] ?? 0),
                'late' => (int) ($attendanceRows['late'] ?? 0),
                'excused' => (int) ($attendanceRows['excused'] ?? 0),
                'total' => (int) $attendanceRows->sum(),
            ],
            'subjects' => $subjects,
            'overall_average' => $gradedAverages->count() ? round((float) $gradedAverages->avg(), 2) : null,
            'generated_at' => now()->toDateTimeString(),
            'note' => 'المعدلات محسوبة من الاختبارات والواجبات التي اعتمد المعلم درجاتها فقط، ولا تمثل نتيجة فصلية نهائية ما لم تعتمدها إدارة المدرسة.',
        ];
    }
}
