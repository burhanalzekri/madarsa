<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $schoolId = CurrentSchool::id();
        $links = DB::table('parent_student_links as l')
            ->join('student_profiles as sp', 'sp.id', '=', 'l.student_profile_id')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->leftJoin('student_enrollments as e', function ($join) use ($schoolId) {
                $join->on('e.student_profile_id', '=', 'sp.id')->where('e.school_id', '=', $schoolId)->where('e.status', '=', 'active');
            })
            ->leftJoin('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->leftJoin('grades as g', 'g.id', '=', 'c.grade_id')
            ->where('l.school_id', $schoolId)->where('l.parent_user_id', $request->user()->id)
            ->select('sp.id as student_id', 'u.name', 'sp.student_number', 'l.relationship', 'g.name as grade', 'c.name as classroom')
            ->distinct()->get();

        $children = $links->map(function ($child) use ($schoolId) {
            $sid = (int) $child->student_id;
            $attendance = DB::table('attendance_records')->where('school_id', $schoolId)->where('student_profile_id', $sid)
                ->whereBetween('attendance_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
            $attendanceSummary = (clone $attendance)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
            $results = DB::table('exam_attempts as a')->join('exams as x', 'x.id', '=', 'a.exam_id')
                ->join('grade_subjects as gs', 'gs.id', '=', 'x.grade_subject_id')->join('subjects as s', 's.id', '=', 'gs.subject_id')
                ->where('x.school_id', $schoolId)->where('a.student_profile_id', $sid)->where('a.status', 'graded')
                ->whereNotNull('a.score')->orderByDesc('a.updated_at')->limit(12)
                ->select('x.id', 'x.title', 's.name as subject', 'a.score', 'x.total_score', 'a.updated_at as graded_at')->get();
            $assignments = DB::table('assignments as a')->join('assignment_submissions as s', 's.assignment_id', '=', 'a.id')
                ->join('grade_subjects as gs', 'gs.id', '=', 'a.grade_subject_id')->join('subjects as sub', 'sub.id', '=', 'gs.subject_id')
                ->where('a.school_id', $schoolId)->where('s.student_profile_id', $sid)->orderByDesc('s.updated_at')->limit(8)
                ->select('a.id', 'a.title', 'sub.name as subject', 's.score', 'a.max_score', 's.submitted_at', 's.teacher_feedback')->get();
            $classroom = DB::table('student_enrollments as e')->join('classrooms as c','c.id','=','e.classroom_id')
                ->where('e.school_id',$schoolId)->where('e.student_profile_id',$sid)->where('e.status','active')->select('c.id','c.grade_id')->first();
            $lessonIds = $classroom ? DB::table('lessons as l')->join('curriculum_units as cu','cu.id','=','l.curriculum_unit_id')
                ->join('grade_subjects as gs','gs.id','=','cu.grade_subject_id')->where('gs.grade_id',$classroom->grade_id)->where('l.is_published',true)->pluck('l.id') : collect();
            $lessonsDone = $lessonIds->isEmpty() ? 0 : DB::table('lesson_progress')->where('school_id',$schoolId)->where('student_profile_id',$sid)->whereIn('lesson_id',$lessonIds)->where('completion_percent',100)->count();
            $assignmentTotal = $classroom ? DB::table('assignments')->where('school_id',$schoolId)->where('classroom_id',$classroom->id)->where('is_published',true)->count() : 0;
            $assignmentDone = $classroom ? DB::table('assignment_submissions as sub')->join('assignments as a','a.id','=','sub.assignment_id')->where('a.school_id',$schoolId)->where('a.classroom_id',$classroom->id)->where('a.is_published',true)->where('sub.student_profile_id',$sid)->whereNotNull('sub.submitted_at')->count() : 0;
            $examTotal = $classroom ? DB::table('exams')->where('school_id',$schoolId)->where('classroom_id',$classroom->id)->where('is_published',true)->count() : 0;
            $examDone = $classroom ? DB::table('exam_attempts as at')->join('exams as x','x.id','=','at.exam_id')->where('x.school_id',$schoolId)->where('x.classroom_id',$classroom->id)->where('x.is_published',true)->where('at.student_profile_id',$sid)->whereIn('at.status',['submitted','submitted_for_review','graded'])->count() : 0;
            $learningTotal = $lessonIds->count() + $assignmentTotal + $examTotal;
            $learningDone = $lessonsDone + $assignmentDone + $examDone;
            return [
                'id' => $sid, 'name' => $child->name, 'student_number' => $child->student_number,
                'relationship' => $child->relationship, 'grade' => $child->grade, 'classroom' => $child->classroom,
                'attendance' => ['present' => (int)($attendanceSummary['present'] ?? 0), 'absent' => (int)($attendanceSummary['absent'] ?? 0), 'late' => (int)($attendanceSummary['late'] ?? 0), 'excused' => (int)($attendanceSummary['excused'] ?? 0), 'total' => (clone $attendance)->count()],
                'exam_results' => $results, 'assignments' => $assignments,
                'learning_progress' => ['lessons_done'=>$lessonsDone,'lessons_total'=>$lessonIds->count(),'assignments_done'=>$assignmentDone,'assignments_total'=>$assignmentTotal,
                    'exams_done'=>$examDone,'exams_total'=>$examTotal,'completion_percent'=>$learningTotal ? (int) round($learningDone/$learningTotal*100) : 0],
            ];
        })->values();

        return response()->json(['children' => $children, 'period' => ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]]);
    }

    public function linkStudent(Request $request)
    {
        $data = $request->validate([
            'parent_name' => ['required', 'string', 'max:190'],
            'parent_email' => ['required', 'email', 'max:190'],
            'parent_password' => ['nullable', 'string', 'min:8', 'max:200'],
            'student_number' => ['required', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
        $schoolId = CurrentSchool::id();
        $student = DB::table('student_profiles')->where('school_id', $schoolId)->where('student_number', $data['student_number'])->first();
        abort_unless($student, 404, 'رقم الطالب غير موجود في هذه المدرسة.');
        $parent = DB::table('users')->where('email', $data['parent_email'])->first();
        if ($parent) {
            abort_unless($parent->role === 'parent' && $parent->is_active, 422, 'البريد مستخدم لحساب آخر أو لحساب ولي أمر موقوف.');
        } else {
            abort_unless(!empty($data['parent_password']), 422, 'أدخل كلمة مرور لا تقل عن 8 أحرف لإنشاء حساب ولي الأمر.');
            $parentId = DB::table('users')->insertGetId(['name' => $data['parent_name'], 'email' => $data['parent_email'], 'password' => \Illuminate\Support\Facades\Hash::make($data['parent_password']), 'role' => 'parent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $parent = (object)['id' => $parentId];
        }
        DB::table('parent_student_links')->updateOrInsert(
            ['school_id' => $schoolId, 'parent_user_id' => $parent->id, 'student_profile_id' => $student->id],
            ['relationship' => $data['relationship'] ?? 'ولي أمر', 'is_primary' => $data['is_primary'] ?? false, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['message' => 'تم ربط حساب ولي الأمر بالطالب بنجاح.'], 201);
    }
}
