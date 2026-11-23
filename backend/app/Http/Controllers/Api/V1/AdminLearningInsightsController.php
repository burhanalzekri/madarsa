<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;

class AdminLearningInsightsController extends Controller
{
    public function index()
    {
        $school = CurrentSchool::id();
        $subjects = DB::table('grade_subjects as gs')
            ->join('grades as g', 'g.id', '=', 'gs.grade_id')
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->join('terms as t', 't.id', '=', 'gs.term_id')
            ->where('g.school_id', $school)->where('s.school_id', $school)
            ->select('gs.id', 'gs.grade_id', 't.academic_year_id', 'g.name as grade', 's.name as subject', 't.name as term')
            ->get();

        $classrooms = DB::table('classrooms')->where('school_id', $school)->where('is_active', true)->get();
        $subjectRows = [];
        foreach ($subjects as $subject) {
            foreach ($classrooms->where('grade_id', $subject->grade_id)->where('academic_year_id', $subject->academic_year_id) as $classroom) {
                $enrolledCount = DB::table('student_enrollments')->where('school_id', $school)->where('classroom_id', $classroom->id)->where('status', 'active')->count();
                $lessonIds = DB::table('lessons as l')->join('curriculum_units as cu', 'cu.id', '=', 'l.curriculum_unit_id')
                    ->where('cu.grade_subject_id', $subject->id)->where('l.is_published', true)->pluck('l.id');
                $lessonTotal = $lessonIds->count();
                $lessonDone = $lessonTotal ? DB::table('lesson_progress as lp')
                    ->join('student_enrollments as en', 'en.student_profile_id', '=', 'lp.student_profile_id')
                    ->where('lp.school_id', $school)->where('en.classroom_id', $classroom->id)->where('en.status', 'active')
                    ->whereIn('lp.lesson_id', $lessonIds)->where('lp.completion_percent', 100)->count() : 0;
                $assignmentIds = DB::table('assignments')->where('school_id', $school)->where('classroom_id', $classroom->id)
                    ->where('grade_subject_id', $subject->id)->where('is_published', true)->pluck('id');
                $assignmentTotal = $assignmentIds->count();
                $assignmentDone = $assignmentTotal ? DB::table('assignment_submissions')->whereIn('assignment_id', $assignmentIds)->whereNotNull('submitted_at')->count() : 0;
                $examIds = DB::table('exams')->where('school_id', $school)->where('classroom_id', $classroom->id)
                    ->where('grade_subject_id', $subject->id)->where('is_published', true)->pluck('id');
                $examTotal = $examIds->count();
                $attempts = $examTotal ? DB::table('exam_attempts')->whereIn('exam_id', $examIds)->whereIn('status', ['submitted', 'submitted_for_review', 'graded']) : null;
                $examDone = $attempts ? (clone $attempts)->count() : 0;
                $average = $examTotal ? DB::table('exam_attempts as ea')->join('exams as ex', 'ex.id', '=', 'ea.exam_id')
                    ->whereIn('ea.exam_id', $examIds)->whereNotNull('ea.score')->avg(DB::raw('(ea.score / NULLIF(ex.total_score, 0)) * 100')) : null;
                $total = ($lessonTotal + $assignmentTotal + $examTotal) * $enrolledCount;
                $done = $lessonDone + $assignmentDone + $examDone;
                if ($total === 0) continue;
                $subjectRows[] = [
                    'grade_subject_id' => $subject->id, 'grade' => $subject->grade, 'subject' => $subject->subject,
                    'term' => $subject->term, 'classroom' => $classroom->name,
                    'lessons_done' => $lessonDone, 'lessons_total' => $lessonTotal,
                    'assignments_done' => $assignmentDone, 'assignments_total' => $assignmentTotal,
                    'exams_done' => $examDone, 'exams_total' => $examTotal,
                    'average_exam_score' => $average === null ? null : round((float) $average, 2), 'students_count' => $enrolledCount,
                    'completion_percent' => (int) round(($done / $total) * 100),
                ];
            }
        }

        $alerts = [];
        $students = DB::table('student_enrollments as e')->join('student_profiles as sp', 'sp.id', '=', 'e.student_profile_id')
            ->join('users as u', 'u.id', '=', 'sp.user_id')->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->join('grades as g', 'g.id', '=', 'c.grade_id')->where('e.school_id', $school)->where('e.status', 'active')
            ->select('sp.id as student_id', 'sp.student_number', 'u.name', 'c.id as classroom_id', 'c.name as classroom', 'g.name as grade')
            ->orderBy('u.name')->limit(300)->get();

        foreach ($students as $student) {
            $studentClassroom = $classrooms->firstWhere('id', $student->classroom_id);
            $gradeSubjectIds = $subjects->where('grade_id', $studentClassroom->grade_id ?? 0)
                ->where('academic_year_id', $studentClassroom->academic_year_id ?? 0)->pluck('id');
            $lessonIds = DB::table('lessons as l')->join('curriculum_units as cu', 'cu.id', '=', 'l.curriculum_unit_id')
                ->whereIn('cu.grade_subject_id', $gradeSubjectIds)->where('l.is_published', true)->pluck('l.id');
            $lessonTotal = $lessonIds->count();
            $lessonDone = $lessonTotal ? DB::table('lesson_progress')->where('school_id', $school)->where('student_profile_id', $student->student_id)
                ->whereIn('lesson_id', $lessonIds)->where('completion_percent', 100)->count() : 0;
            $assignmentQuery = DB::table('assignments as a')->where('a.school_id', $school)->where('a.classroom_id', $student->classroom_id)->where('a.is_published', true);
            $assignmentTotal = (clone $assignmentQuery)->count();
            $overdue = (clone $assignmentQuery)->whereNotNull('a.due_at')->where('a.due_at', '<', now())
                ->whereNotExists(function ($q) use ($student) { $q->selectRaw('1')->from('assignment_submissions as sub')
                    ->whereColumn('sub.assignment_id', 'a.id')->where('sub.student_profile_id', $student->student_id)->whereNotNull('sub.submitted_at'); })->count();
            $examIds = DB::table('exams')->where('school_id', $school)->where('classroom_id', $student->classroom_id)->where('is_published', true)->pluck('id');
            $examTotal = $examIds->count();
            $examDone = $examTotal ? DB::table('exam_attempts')->where('student_profile_id', $student->student_id)->whereIn('exam_id', $examIds)
                ->whereIn('status', ['submitted', 'submitted_for_review', 'graded'])->count() : 0;
            $assignmentDone = $assignmentTotal ? DB::table('assignment_submissions')->where('student_profile_id', $student->student_id)
                ->whereIn('assignment_id', (clone $assignmentQuery)->pluck('a.id'))->whereNotNull('submitted_at')->count() : 0;
            $total = $lessonTotal + $assignmentTotal + $examTotal;
            $done = $lessonDone + $assignmentDone + $examDone;
            $percent = $total ? (int) round($done / $total * 100) : 0;
            if ($overdue > 0 || ($total > 0 && $percent < 40)) {
                $alerts[] = ['student_id' => $student->student_id, 'student_number' => $student->student_number, 'name' => $student->name,
                    'grade' => $student->grade, 'classroom' => $student->classroom, 'completion_percent' => $percent,
                    'overdue_assignments' => $overdue, 'reason' => $overdue > 0 ? 'واجبات تجاوزت موعد التسليم' : 'نسبة الإنجاز أقل من 40%'];
            }
        }

        usort($alerts, fn ($a, $b) => ($b['overdue_assignments'] <=> $a['overdue_assignments']) ?: ($a['completion_percent'] <=> $b['completion_percent']));
        return response()->json(['subjects' => $subjectRows, 'alerts' => $alerts, 'generated_at' => now()->toIso8601String()]);
    }
}
