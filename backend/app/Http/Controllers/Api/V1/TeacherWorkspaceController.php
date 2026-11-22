<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherWorkspaceController extends Controller
{
    private function teacherId(Request $request): int
    {
        abort_unless($request->user()->role === 'teacher', 403, 'هذه الخدمة مخصصة لحساب المعلم.');
        $id = DB::table('teacher_profiles')->where('school_id', CurrentSchool::id())
            ->where('user_id', $request->user()->id)->value('id');
        abort_unless($id, 403, 'لا يوجد ملف معلم مرتبط بهذا الحساب.');
        return (int) $id;
    }

    private function ownsClassroom(int $teacherId, int $classroomId, int $gradeSubjectId): bool
    {
        $classroom = DB::table('classrooms')->where('id', $classroomId)->where('school_id', CurrentSchool::id())->first();
        if (!$classroom) return false;
        return DB::table('teacher_assignments as ta')->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
            ->where('ta.school_id', CurrentSchool::id())->where('ta.teacher_profile_id', $teacherId)
            ->where('ta.grade_subject_id', $gradeSubjectId)->where('gs.grade_id', $classroom->grade_id)
            ->where(function ($q) use ($classroomId) { $q->whereNull('ta.classroom_id')->orWhere('ta.classroom_id', $classroomId); })
            ->exists();
    }

    public function dashboard(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $schoolId = CurrentSchool::id();
        $classrooms = DB::table('teacher_assignments as ta')
            ->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
            ->join('classrooms as c', function ($join) {
                $join->on('c.grade_id', '=', 'gs.grade_id')
                    ->where(function ($q) { $q->whereNull('ta.classroom_id')->orWhereColumn('ta.classroom_id', 'c.id'); });
            })
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->where('ta.school_id', $schoolId)->where('ta.teacher_profile_id', $teacherId)
            ->where('c.school_id', $schoolId)->where('c.is_active', true)
            ->select('c.id as classroom_id', 'c.name as classroom', 's.name as subject', 'ta.grade_subject_id')
            ->distinct()->orderBy('c.name')->get();
        return response()->json([
            'classrooms' => $classrooms,
            'assignments_count' => DB::table('assignments')->where('school_id', $schoolId)->where('teacher_profile_id', $teacherId)->count(),
            'pending_submissions' => DB::table('assignment_submissions as s')->join('assignments as a', 'a.id', '=', 's.assignment_id')
                ->where('a.school_id', $schoolId)->where('a.teacher_profile_id', $teacherId)->whereNull('s.score')->count(),
            'upcoming_live_sessions' => DB::table('live_sessions')->where('school_id', $schoolId)->where('teacher_profile_id', $teacherId)
                ->whereNull('ended_at')->where('starts_at', '>=', now())->count(),
        ]);
    }

    public function courses(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $schoolId = CurrentSchool::id();
        $rows = DB::table('teacher_assignments as ta')
            ->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
            ->join('grades as g', 'g.id', '=', 'gs.grade_id')
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->join('terms as t', 't.id', '=', 'gs.term_id')
            ->join('academic_years as ay', 'ay.id', '=', 'ta.academic_year_id')
            ->leftJoin('classrooms as c', 'c.id', '=', 'ta.classroom_id')
            ->where('ta.school_id', $schoolId)->where('ta.teacher_profile_id', $teacherId)
            ->where('g.school_id', $schoolId)->where('ay.school_id', $schoolId)->where('ay.is_current', true)->where('t.is_active', true)
            ->select('gs.id as grade_subject_id','g.id as grade_id','s.name as subject','g.name as grade','t.name as term',
                'ay.name as academic_year','gs.weekly_lessons','c.id as classroom_id','c.name as classroom')
            ->distinct()->orderBy('s.name')->get();
        $courses = $rows->map(function ($course) use ($schoolId) {
            $units = DB::table('curriculum_units')->where('grade_subject_id', $course->grade_subject_id)
                ->orderBy('sort_order')->get()->map(function ($unit) use ($schoolId) {
                    $unit->lessons = DB::table('lessons as l')->where('l.curriculum_unit_id', $unit->id)
                        ->select('l.id','l.title','l.description','l.video_url','l.audio_url','l.is_published','l.sort_order')
                        ->orderBy('l.sort_order')->get()->map(function ($lesson) use ($schoolId) {
                            $lesson->resources = DB::table('educational_resources')->where('school_id',$schoolId)
                                ->where('lesson_id',$lesson->id)->select('id','title','resource_type','is_published')->get();
                            return $lesson;
                        });
                    return $unit;
                });
            $course->units = $units;
            $course->students_count = $course->classroom_id ? DB::table('student_enrollments')
                ->where('school_id',$schoolId)->where('classroom_id',$course->classroom_id)->where('status','active')->count() : DB::table('student_enrollments as e')->join('classrooms as c','c.id','=','e.classroom_id')->where('e.school_id',$schoolId)->where('c.grade_id',$course->grade_id)->where('e.status','active')->count();
            return $course;
        });
        return response()->json(['courses' => $courses]);
    }

    public function assignments(Request $request)
    {
        $teacherId = $this->teacherId($request);
        return DB::table('assignments')->where('school_id', CurrentSchool::id())->where('teacher_profile_id', $teacherId)
            ->orderByDesc('created_at')->paginate(30);
    }

    public function createAssignment(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate([
            'grade_subject_id' => ['required', 'integer'], 'classroom_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date', 'after:now'], 'max_score' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        abort_unless($this->ownsClassroom($teacherId, $data['classroom_id'], $data['grade_subject_id']), 403, 'المادة أو الشعبة غير مسندة إليك.');
        $id = DB::table('assignments')->insertGetId($data + [
            'school_id' => CurrentSchool::id(), 'teacher_profile_id' => $teacherId,
            'is_published' => $data['is_published'] ?? false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'تم إنشاء الواجب.', 'assignment' => DB::table('assignments')->find($id)], 201);
    }


    private function ownsGradeSubject(int $teacherId, int $gradeSubjectId): bool
    {
        return DB::table('teacher_assignments')->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->where('grade_subject_id', $gradeSubjectId)->exists();
    }

    private function ownedLesson(int $teacherId, int $lessonId): object
    {
        $lesson = DB::table('lessons as l')->join('curriculum_units as u', 'u.id', '=', 'l.curriculum_unit_id')
            ->join('teacher_assignments as ta', 'ta.grade_subject_id', '=', 'u.grade_subject_id')
            ->where('l.id', $lessonId)->where('ta.school_id', CurrentSchool::id())
            ->where('ta.teacher_profile_id', $teacherId)->select('l.*', 'u.grade_subject_id')->first();
        abort_unless($lesson, 404, 'الدرس غير موجود ضمن المواد المسندة إليك.');
        return $lesson;
    }

    public function createUnit(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['grade_subject_id' => ['required','integer'], 'title' => ['required','string','max:255'], 'sort_order' => ['nullable','integer','min:0']]);
        abort_unless($this->ownsGradeSubject($teacherId, (int) $data['grade_subject_id']), 403, 'هذه المادة غير مسندة إليك.');
        $id = DB::table('curriculum_units')->insertGetId([
            'grade_subject_id' => $data['grade_subject_id'], 'title' => $data['title'], 'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'تم إنشاء الوحدة.', 'unit' => DB::table('curriculum_units')->find($id)], 201);
    }

    public function createLesson(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['unit_id' => ['required','integer'], 'title' => ['required','string','max:255'],
            'description' => ['nullable','string','max:20000'], 'video_url' => ['nullable','url','max:2048'], 'audio_url' => ['nullable','url','max:2048'],
            'sort_order' => ['nullable','integer','min:0'], 'is_published' => ['sometimes','boolean']]);
        $unit = DB::table('curriculum_units')->where('id', $data['unit_id'])->first();
        abort_unless($unit && $this->ownsGradeSubject($teacherId, (int) $unit->grade_subject_id), 403, 'الوحدة لا تتبع مادة مسندة إليك.');
        $id = DB::table('lessons')->insertGetId([
            'curriculum_unit_id' => $unit->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'video_url' => $data['video_url'] ?? null, 'audio_url' => $data['audio_url'] ?? null, 'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'تم إنشاء الدرس.', 'lesson' => DB::table('lessons')->find($id)], 201);
    }

    public function updateLesson(Request $request, int $lessonId)
    {
        $teacherId = $this->teacherId($request);
        $lesson = $this->ownedLesson($teacherId, $lessonId);
        $data = $request->validate(['title' => ['sometimes','required','string','max:255'], 'description' => ['nullable','string','max:20000'],
            'video_url' => ['nullable','url','max:2048'], 'audio_url' => ['nullable','url','max:2048'], 'sort_order' => ['sometimes','integer','min:0'], 'is_published' => ['sometimes','boolean']]);
        DB::table('lessons')->where('id', $lesson->id)->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'تم تحديث الدرس.', 'lesson' => DB::table('lessons')->find($lessonId)]);
    }

    public function deleteLesson(Request $request, int $lessonId)
    {
        $teacherId = $this->teacherId($request);
        $lesson = $this->ownedLesson($teacherId, $lessonId);
        abort_if(DB::table('lesson_progress')->where('lesson_id', $lessonId)->exists(), 409, 'لا يمكن حذف درس له سجلات تقدم؛ ألغِ نشره بدلاً من ذلك.');
        DB::table('lessons')->where('id', $lesson->id)->delete();
        return response()->json(['message' => 'تم حذف الدرس.']);
    }

    public function updateAssignment(Request $request, int $assignmentId)
    {
        $teacherId = $this->teacherId($request);
        $assignment = DB::table('assignments')->where('id', $assignmentId)->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->first();
        abort_unless($assignment, 404);
        $data = $request->validate(['title' => ['sometimes','required','string','max:255'], 'instructions' => ['nullable','string','max:20000'],
            'due_at' => ['nullable','date'], 'max_score' => ['sometimes','required','numeric','min:0.01','max:100000'], 'is_published' => ['sometimes','boolean']]);
        if (isset($data['max_score']) && (float) $data['max_score'] !== (float) $assignment->max_score) {
            abort_if(DB::table('assignment_submissions')->where('assignment_id', $assignmentId)->whereNotNull('score')->exists(), 422,
                'لا يمكن تغيير الدرجة النهائية بعد تصحيح تسليمات الطلاب.');
        }
        DB::table('assignments')->where('id', $assignmentId)->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'تم تحديث الواجب.']);
    }

    public function deleteAssignment(Request $request, int $assignmentId)
    {
        $teacherId = $this->teacherId($request);
        $assignment = DB::table('assignments')->where('id', $assignmentId)->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->first();
        abort_unless($assignment, 404);
        abort_if(DB::table('assignment_submissions')->where('assignment_id', $assignmentId)->exists(), 409,
            'لا يمكن حذف واجب استقبل تسليمات؛ ألغِ نشره بدلاً من ذلك.');
        DB::table('assignments')->where('id', $assignmentId)->delete();
        return response()->json(['message' => 'تم حذف الواجب.']);
    }

    public function progressReport(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $schoolId = CurrentSchool::id();
        $filterClassroom = $request->validate(['classroom_id' => ['nullable','integer']])['classroom_id'] ?? null;
        $rows = DB::table('student_enrollments as e')->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->join('student_profiles as sp', 'sp.id', '=', 'e.student_profile_id')->join('users as u', 'u.id', '=', 'sp.user_id')
            ->where('e.school_id', $schoolId)->where('e.status', 'active')->where('c.school_id', $schoolId)
            ->whereExists(function ($q) use ($teacherId, $schoolId) {
                $q->selectRaw('1')->from('teacher_assignments as ta')->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
                    ->where('ta.school_id', $schoolId)->where('ta.teacher_profile_id', $teacherId)->whereColumn('gs.grade_id', 'c.grade_id')
                    ->where(function ($scope) { $scope->whereNull('ta.classroom_id')->orWhereColumn('ta.classroom_id', 'c.id'); });
            })->when($filterClassroom, fn ($q) => $q->where('c.id', $filterClassroom))
            ->select('sp.id as student_profile_id', 'sp.student_number', 'u.name', 'c.id as classroom_id', 'c.name as classroom')
            ->distinct()->orderBy('u.name')->get();
        $report = $rows->map(function ($student) use ($schoolId, $teacherId) {
            $gradeId = DB::table('classrooms')->where('id', $student->classroom_id)->value('grade_id');
            $subjectIds = DB::table('teacher_assignments as ta')->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
                ->where('ta.school_id', $schoolId)->where('ta.teacher_profile_id', $teacherId)->where('gs.grade_id', $gradeId)
                ->where(function ($q) use ($student) { $q->whereNull('ta.classroom_id')->orWhere('ta.classroom_id', $student->classroom_id); })
                ->pluck('ta.grade_subject_id')->unique()->values();
            $lessonIds = DB::table('lessons as l')->join('curriculum_units as cu', 'cu.id', '=', 'l.curriculum_unit_id')
                ->whereIn('cu.grade_subject_id', $subjectIds)->where('l.is_published', true)->pluck('l.id');
            $total = $lessonIds->count();
            $done = DB::table('lesson_progress')->where('school_id', $schoolId)->where('student_profile_id', $student->student_profile_id)
                ->whereIn('lesson_id', $lessonIds)->where('completion_percent', 100)->count();
            $assignmentIds = DB::table('assignments')->where('school_id', $schoolId)->where('teacher_profile_id', $teacherId)
                ->where('classroom_id', $student->classroom_id)->whereIn('grade_subject_id', $subjectIds)->pluck('id');
            $submitted = DB::table('assignment_submissions')->where('student_profile_id', $student->student_profile_id)->whereIn('assignment_id', $assignmentIds)->whereNotNull('submitted_at')->count();
            $graded = DB::table('assignment_submissions')->where('student_profile_id', $student->student_profile_id)->whereIn('assignment_id', $assignmentIds)->whereNotNull('score')->count();
            $examIds = DB::table('exams')->where('school_id', $schoolId)->where('teacher_profile_id', $teacherId)
                ->where('classroom_id', $student->classroom_id)->whereIn('grade_subject_id', $subjectIds)->pluck('id');
            $attempts = DB::table('exam_attempts')->where('student_profile_id', $student->student_profile_id)->whereIn('exam_id', $examIds)->count();
            $submittedExams = DB::table('exam_attempts')->where('student_profile_id', $student->student_profile_id)->whereIn('exam_id', $examIds)->where('status', 'submitted')->count();
            $percent = $total ? (int) round($done / $total * 100) : 0;
            return ['student_profile_id' => $student->student_profile_id, 'student_number' => $student->student_number, 'name' => $student->name,
                'classroom' => $student->classroom, 'lessons_total' => $total, 'lessons_completed' => $done, 'lesson_percent' => $percent,
                'assignments_submitted' => $submitted, 'assignments_graded' => $graded, 'exam_attempts' => $attempts,
                'submitted_exams' => $submittedExams, 'needs_follow_up' => ($total > 0 && $percent < 30) || ($submitted > $graded)];
        });
        return response()->json(['students' => $report->values(), 'summary' => ['students' => $report->count(), 'needs_follow_up' => $report->where('needs_follow_up', true)->count()]]);
    }

    public function submissions(Request $request, int $assignmentId)
    {
        $teacherId = $this->teacherId($request);
        $assignment = DB::table('assignments')->where('id', $assignmentId)->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->first();
        abort_unless($assignment, 404);
        return DB::table('assignment_submissions as sub')->join('student_profiles as sp', 'sp.id', '=', 'sub.student_profile_id')
            ->join('users as u', 'u.id', '=', 'sp.user_id')->where('sub.assignment_id', $assignmentId)
            ->select('sub.id', 'sub.answer_text', 'sub.attachment_path', 'sub.submitted_at', 'sub.score', 'sub.teacher_feedback',
                'sp.student_number', 'u.name as student_name')->orderByDesc('sub.submitted_at')->paginate(50);
    }

    public function gradeSubmission(Request $request, int $submissionId)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['score' => ['required', 'numeric', 'min:0'], 'teacher_feedback' => ['nullable', 'string', 'max:10000']]);
        $submission = DB::table('assignment_submissions as sub')->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->where('sub.id', $submissionId)->where('a.school_id', CurrentSchool::id())->where('a.teacher_profile_id', $teacherId)
            ->select('sub.id', 'a.max_score')->first();
        abort_unless($submission, 404);
        if ((float) $data['score'] > (float) $submission->max_score) {
            throw ValidationException::withMessages(['score' => ['الدرجة تتجاوز الدرجة النهائية للواجب.']]);
        }
        DB::table('assignment_submissions')->where('id', $submissionId)->update([
            'score' => $data['score'], 'teacher_feedback' => $data['teacher_feedback'] ?? null,
            'graded_by' => $request->user()->id, 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'تم حفظ التصحيح.']);
    }

    public function recordAttendance(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'], 'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.student_profile_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $classroom = DB::table('classrooms')->where('id', $data['classroom_id'])->where('school_id', CurrentSchool::id())->first();
        abort_unless($classroom && DB::table('teacher_assignments as ta')->join('grade_subjects as gs', 'gs.id', '=', 'ta.grade_subject_id')
            ->where('ta.school_id', CurrentSchool::id())->where('ta.teacher_profile_id', $teacherId)
            ->where('gs.grade_id', $classroom->grade_id)
            ->where(function ($q) use ($data) { $q->whereNull('ta.classroom_id')->orWhere('ta.classroom_id', $data['classroom_id']); })
            ->exists(), 403, 'الشعبة غير مسندة إليك.');
        $studentIds = array_column($data['records'], 'student_profile_id');
        $valid = DB::table('student_enrollments')->where('school_id', CurrentSchool::id())->where('classroom_id', $data['classroom_id'])
            ->where('status', 'active')->whereIn('student_profile_id', $studentIds)->pluck('student_profile_id')->all();
        abort_unless(count($valid) === count(array_unique($studentIds)), 422, 'تتضمن القائمة طالباً غير مسجل في هذه الشعبة.');
        DB::transaction(function () use ($data, $request) {
            foreach ($data['records'] as $record) {
                DB::table('attendance_records')->updateOrInsert(
                    ['student_profile_id' => $record['student_profile_id'], 'attendance_date' => $data['attendance_date']],
                    ['school_id' => CurrentSchool::id(), 'classroom_id' => $data['classroom_id'], 'status' => $record['status'],
                     'notes' => $record['notes'] ?? null, 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
        return response()->json(['message' => 'تم حفظ سجل الحضور والغياب.', 'saved' => count($data['records'])]);
    }
}
