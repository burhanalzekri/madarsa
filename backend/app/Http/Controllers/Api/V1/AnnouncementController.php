<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AnnouncementController extends Controller
{
 public function index(Request $request){
  $role=$request->user()->role; $school=CurrentSchool::id();
  $rows=DB::table('school_announcements')->where('school_id',$school)->where('is_published',true)
   ->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))
   ->orderByDesc('published_at')->limit(100)->get(['id','title','body','category','published_at','target_roles','classroom_id','created_by']);
  $userId=(int)$request->user()->id;
  $visible=$rows->filter(function($item)use($role,$school,$userId){
   $targets=$item->target_roles?json_decode($item->target_roles,true):null;
   if($targets && !in_array($role,$targets,true)) return false;
   if(!$item->classroom_id) return true;
   if($role==='student') return DB::table('student_enrollments')->where('school_id',$school)->where('classroom_id',$item->classroom_id)->where('status','active')->whereIn('student_profile_id',DB::table('student_profiles')->where('school_id',$school)->where('user_id',$userId)->select('id'))->exists();
   if($role==='parent') return DB::table('parent_student_links as l')->join('student_enrollments as e','e.student_profile_id','=','l.student_profile_id')->where('l.school_id',$school)->where('l.parent_user_id',$userId)->where('e.school_id',$school)->where('e.classroom_id',$item->classroom_id)->where('e.status','active')->exists();
   if($role==='teacher') return (int)$item->created_by===$userId;
   return false;
  })->map(function($item){unset($item->target_roles,$item->classroom_id,$item->created_by);return $item;})->values();
  return response()->json(['announcements'=>$visible]);
 }

 public function teacherIndex(Request $request){
  abort_unless($request->user()->role==='teacher',403);
  return response()->json(['announcements'=>DB::table('school_announcements')->where('school_id',CurrentSchool::id())->where('created_by',$request->user()->id)->orderByDesc('created_at')->limit(100)->get(['id','title','body','category','classroom_id','target_roles','is_published','published_at','expires_at'])]);
 }
 public function teacherStore(Request $request){
  abort_unless($request->user()->role==='teacher',403);
  $data=$request->validate(['classroom_id'=>'required|integer','title'=>'required|string|max:190','body'=>'required|string|max:10000','category'=>'nullable|string|max:40','target_roles'=>'required|array|min:1','target_roles.*'=>'in:student,parent','is_published'=>'sometimes|boolean','expires_at'=>'nullable|date|after:now']);
  $school=CurrentSchool::id();
  $teacherId=DB::table('teacher_profiles')->where('school_id',$school)->where('user_id',$request->user()->id)->value('id');
  abort_unless($teacherId,403,'لا يوجد ملف معلم مرتبط بهذا الحساب.');
  $classroom=DB::table('classrooms')->where('id',$data['classroom_id'])->where('school_id',$school)->first();
  abort_unless($classroom && DB::table('teacher_assignments as ta')->join('grade_subjects as gs','gs.id','=','ta.grade_subject_id')->where('ta.school_id',$school)->where('ta.teacher_profile_id',$teacherId)->where('gs.grade_id',$classroom->grade_id)->where(fn($q)=>$q->whereNull('ta.classroom_id')->orWhere('ta.classroom_id',$classroom->id))->exists(),403,'لا يمكنك إرسال إعلان لهذه الشعبة لأنها غير مسندة إليك.');
  $published=$data['is_published']??true;
  $id=DB::table('school_announcements')->insertGetId(['school_id'=>$school,'created_by'=>$request->user()->id,'classroom_id'=>$classroom->id,'title'=>$data['title'],'body'=>$data['body'],'category'=>$data['category']??'إعلان المعلم','target_roles'=>json_encode(array_values(array_unique($data['target_roles'])),JSON_UNESCAPED_UNICODE),'is_published'=>$published,'published_at'=>$published?now():null,'expires_at'=>$data['expires_at']??null,'created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['message'=>'تم حفظ إعلان الشعبة.','announcement'=>DB::table('school_announcements')->find($id)],201);
 }
 public function adminIndex(Request $request){
  abort_unless(in_array($request->user()->role,['school_admin','super_admin'],true),403);
  return response()->json(['announcements'=>DB::table('school_announcements')->where('school_id',CurrentSchool::id())->orderByDesc('created_at')->limit(100)->get(['id','title','body','category','published_at','is_published'])]);
 }
 public function store(Request $request){
  abort_unless(in_array($request->user()->role,['school_admin','super_admin'],true),403);
  $data=$request->validate(['title'=>'required|string|max:190','body'=>'required|string|max:10000','category'=>'nullable|string|max:40','target_roles'=>'nullable|array','target_roles.*'=>'in:student,teacher,parent','is_published'=>'sometimes|boolean','expires_at'=>'nullable|date|after:now']);
  $published=$data['is_published']??true; $id=DB::table('school_announcements')->insertGetId([
   'school_id'=>CurrentSchool::id(),'created_by'=>$request->user()->id,'title'=>$data['title'],'body'=>$data['body'],
   'category'=>$data['category']??'عام','target_roles'=>isset($data['target_roles'])?json_encode(array_values(array_unique($data['target_roles'])),JSON_UNESCAPED_UNICODE):null,
   'is_published'=>$published,'published_at'=>$published?now():null,'expires_at'=>$data['expires_at']??null,'created_at'=>now(),'updated_at'=>now()
  ]);
  return response()->json(['message'=>'تم نشر الإعلان المدرسي.','announcement'=>DB::table('school_announcements')->find($id)],201);
 }
}
