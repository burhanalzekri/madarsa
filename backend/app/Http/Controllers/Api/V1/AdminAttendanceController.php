<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    private function manager(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['school_admin', 'super_admin'], true), 403, 'ليست لديك صلاحية إدارة الحضور.');
    }

    public function roster(Request $request)
    {
        $this->manager($request);
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $schoolId = CurrentSchool::id();
        $classroom = DB::table('classrooms')->where('school_id', $schoolId)->where('id', $data['classroom_id'])->first();
        abort_unless($classroom, 404, 'الشعبة غير موجودة في المدرسة الحالية.');
        $date = $data['date'] ?? now()->toDateString();
        $students = DB::table('student_enrollments as e')
            ->join('student_profiles as sp', 'sp.id', '=', 'e.student_profile_id')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->leftJoin('attendance_records as ar', function ($join) use ($date, $classroom) {
                $join->on('ar.student_profile_id', '=', 'sp.id')
                    ->where('ar.attendance_date', '=', $date)
                    ->where('ar.classroom_id', '=', $classroom->id);
            })
            ->where('e.school_id', $schoolId)->where('e.classroom_id', $classroom->id)->where('e.status', 'active')
            ->select('sp.id as student_profile_id', 'sp.student_number', 'u.name', 'ar.status', 'ar.notes')
            ->orderBy('u.name')->get();
        return response()->json(['date' => $date, 'classroom' => $classroom, 'students' => $students]);
    }

    public function store(Request $request)
    {
        $this->manager($request);
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_profile_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $schoolId = CurrentSchool::id();
        $classroom = DB::table('classrooms')->where('school_id', $schoolId)->where('id', $data['classroom_id'])->first();
        abort_unless($classroom, 404, 'الشعبة غير موجودة في المدرسة الحالية.');
        $studentIds = array_column($data['records'], 'student_profile_id');
        $validIds = DB::table('student_enrollments')->where('school_id', $schoolId)
            ->where('classroom_id', $classroom->id)->where('status', 'active')
            ->whereIn('student_profile_id', $studentIds)->pluck('student_profile_id')->all();
        abort_unless(count($validIds) === count($studentIds), 422, 'تتضمن القائمة طالباً غير مسجل في هذه الشعبة.');

        DB::transaction(function () use ($data, $request, $schoolId, $classroom) {
            foreach ($data['records'] as $record) {
                DB::table('attendance_records')->updateOrInsert(
                    ['student_profile_id' => $record['student_profile_id'], 'attendance_date' => $data['attendance_date']],
                    ['school_id' => $schoolId, 'classroom_id' => $classroom->id, 'status' => $record['status'],
                     'notes' => $record['notes'] ?? null, 'recorded_by' => $request->user()->id,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
        return response()->json(['message' => 'تم حفظ سجل الحضور والغياب في قاعدة البيانات.', 'saved' => count($data['records'])]);
    }

    public function report(Request $request)
    {
        $this->manager($request);
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $schoolId = CurrentSchool::id();
        $classroom = DB::table('classrooms')->where('school_id', $schoolId)->where('id', $data['classroom_id'])->first();
        abort_unless($classroom, 404, 'الشعبة غير موجودة في المدرسة الحالية.');
        $query = DB::table('attendance_records as ar')
            ->join('student_profiles as sp', 'sp.id', '=', 'ar.student_profile_id')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->where('ar.school_id', $schoolId)->where('ar.classroom_id', $classroom->id)
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('ar.attendance_date', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('ar.attendance_date', '<=', $to));
        return response()->json([
            'summary' => (clone $query)->select('ar.status', DB::raw('COUNT(*) as total'))->groupBy('ar.status')->pluck('total', 'status'),
            'records' => $query->select('ar.id', 'ar.attendance_date', 'ar.status', 'ar.notes', 'sp.student_number', 'u.name')
                ->orderByDesc('ar.attendance_date')->orderBy('u.name')->limit(500)->get(),
        ]);
    }
}
