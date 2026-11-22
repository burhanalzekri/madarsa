<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{StudentProfile,StudentEnrollment,Classroom};
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class EnrollmentController extends Controller {
 private function manager(Request $r){abort_unless(in_array($r->user()->role,['school_admin','super_admin'],true),403);}
 public function store(Request $r){$this->manager($r);$d=$r->validate(['student_profile_id'=>'required|integer|exists:student_profiles,id','classroom_id'=>'required|integer|exists:classrooms,id']);$school=CurrentSchool::id();$student=StudentProfile::where('school_id',$school)->findOrFail($d['student_profile_id']);$class=Classroom::where('school_id',$school)->findOrFail($d['classroom_id']);abort_unless($class->is_active,422,'الشعبة غير نشطة.');$capacityCount=StudentEnrollment::where('classroom_id',$class->id)->where('status','active')->count();abort_if($class->capacity && $capacityCount >= $class->capacity,422,'الشعبة وصلت إلى سعتها الاستيعابية.');$existing=StudentEnrollment::where('student_profile_id',$student->id)->where('academic_year_id',$class->academic_year_id)->first(); abort_if($existing,409,'الطالب لديه تسجيل في هذه السنة. استخدم إجراء النقل لتغيير شعبته.'); $e=DB::transaction(fn()=>StudentEnrollment::create(['school_id'=>$school,'student_profile_id'=>$student->id,'classroom_id'=>$class->id,'academic_year_id'=>$class->academic_year_id,'status'=>'active','enrolled_at'=>now()->toDateString()]));return response()->json(['message'=>'تم تسجيل الطالب في الشعبة.','enrollment'=>$e->load('classroom.grade')],201);}

 public function transfer(Request $r,int $id){
  $this->manager($r); $d=$r->validate(['classroom_id'=>'required|integer|exists:classrooms,id','reason'=>'nullable|string|max:2000']); $school=CurrentSchool::id();
  $enrollment=StudentEnrollment::where('school_id',$school)->with('classroom')->findOrFail($id);
  abort_unless($enrollment->status==='active',422,'لا يمكن نقل تسجيل غير نشط.');
  $target=Classroom::where('school_id',$school)->with(['grade','academicYear'])->findOrFail($d['classroom_id']);
  abort_unless($target->is_active,422,'الشعبة المستهدفة غير نشطة.');
  $count=StudentEnrollment::where('classroom_id',$target->id)->where('status','active')->where('academic_year_id',$target->academic_year_id)->count();
  abort_if($target->capacity && $count >= $target->capacity && $target->id !== $enrollment->classroom_id,422,'الشعبة المستهدفة وصلت إلى سعتها الاستيعابية.');
  abort_if((int)$enrollment->classroom_id === (int)$target->id,422,'الطالب مسجل بالفعل في هذه الشعبة.');
  $oldClassroom=$enrollment->classroom_id; $promotion=(int)$enrollment->academic_year_id !== (int)$target->academic_year_id; abort_if($promotion && !$target->academicYear->is_current,422,'يجب أن تكون الشعبة المستهدفة ضمن السنة الدراسية الحالية.');
  $result=DB::transaction(function()use($r,$enrollment,$target,$oldClassroom,$promotion,$d,$school){
   if($promotion){$enrollment->update(['status'=>'completed','ended_at'=>now()->toDateString()]);$responseEnrollment=StudentEnrollment::create(['school_id'=>$school,'student_profile_id'=>$enrollment->student_profile_id,'classroom_id'=>$target->id,'academic_year_id'=>$target->academic_year_id,'status'=>'active','enrolled_at'=>now()->toDateString()]);$historyEnrollment=$enrollment;$type='promotion';}
   else{$enrollment->update(['classroom_id'=>$target->id,'ended_at'=>null]);$responseEnrollment=$enrollment;$historyEnrollment=$enrollment;$type='transfer';}
   DB::table('enrollment_transfer_history')->insert(['school_id'=>$school,'student_enrollment_id'=>$historyEnrollment->id,'student_profile_id'=>$enrollment->student_profile_id,'from_classroom_id'=>$oldClassroom,'to_classroom_id'=>$target->id,'academic_year_id'=>$target->academic_year_id,'performed_by'=>$r->user()->id,'transfer_type'=>$type,'reason'=>$d['reason']??null,'created_at'=>now(),'updated_at'=>now()]);
   return $responseEnrollment->fresh()->load(['classroom.grade','classroom.academicYear']);
  });
  return response()->json(['message'=>$promotion?'تم ترقية الطالب وتسجيله في السنة الجديدة.':'تم نقل الطالب إلى الشعبة الجديدة.','enrollment'=>$result,'transfer_type'=>$promotion?'promotion':'transfer'],201);
 }
 public function history(Request $r,int $id){$this->manager($r);$school=CurrentSchool::id();$e=StudentEnrollment::where('school_id',$school)->findOrFail($id);return DB::table('enrollment_transfer_history as h')->leftJoin('classrooms as f','f.id','=','h.from_classroom_id')->join('classrooms as t','t.id','=','h.to_classroom_id')->join('users as u','u.id','=','h.performed_by')->where('h.school_id',$school)->where('h.student_profile_id',$e->student_profile_id)->select('h.id','h.transfer_type','h.reason','h.created_at','f.name as from_classroom','t.name as to_classroom','u.name as performed_by')->orderByDesc('h.id')->get();}

 public function index(Request $r){$this->manager($r);return StudentEnrollment::with(['student.user','classroom.grade','classroom.academicYear'])->where('school_id',CurrentSchool::id())->when($r->filled('classroom_id'),fn($q)=>$q->where('classroom_id',$r->integer('classroom_id')))->orderByDesc('id')->paginate(30);}
}
