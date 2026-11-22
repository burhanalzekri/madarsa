<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;

class SchoolReportsController extends Controller
{
    public function overview()
    {
        $schoolId = CurrentSchool::id();
        $attendance = DB::table('attendance_records')->where('school_id',$schoolId)
            ->whereBetween('attendance_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()]);
        $summary = (clone $attendance)->select('status',DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total','status');
        return response()->json([
            'students'=>DB::table('student_profiles')->where('school_id',$schoolId)->where('status','active')->count(),
            'teachers'=>DB::table('teacher_profiles')->where('school_id',$schoolId)->where('status','active')->count(),
            'classrooms'=>DB::table('classrooms')->where('school_id',$schoolId)->where('is_active',true)->count(),
            'assignments'=>DB::table('assignments')->where('school_id',$schoolId)->count(),
            'exams'=>DB::table('exams')->where('school_id',$schoolId)->count(),
            'attendance_total'=>(clone $attendance)->count(),
            'attendance_summary'=>[
                'present'=>(int)($summary['present']??0),'absent'=>(int)($summary['absent']??0),
                'late'=>(int)($summary['late']??0),'excused'=>(int)($summary['excused']??0),
            ],
            'average_exam_score'=>round((float)(DB::table('exam_attempts as a')->join('exams as e','e.id','=','a.exam_id')
                ->where('e.school_id',$schoolId)->whereNotNull('a.score')->avg('a.score')??0),2),
            'period'=>['from'=>now()->startOfMonth()->toDateString(),'to'=>now()->endOfMonth()->toDateString()],
        ]);
    }
}
