<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CurriculumController extends Controller
{
 private function school(): int { return CurrentSchool::id(); }
 private function manager(Request $r): void { abort_unless(in_array($r->user()->role,['school_admin','super_admin'],true),403,'هذه العملية متاحة لإدارة المدرسة فقط.'); }
 private function gradeSubject(int $id) { return DB::table('grade_subjects as gs')->join('grades as g','g.id','=','gs.grade_id')->join('terms as t','t.id','=','gs.term_id')->join('academic_years as ay','ay.id','=','t.academic_year_id')->where('gs.id',$id)->where('g.school_id',$this->school())->where('ay.school_id',$this->school())->select('gs.*')->firstOrFail(); }
 public function index(Request $r) { $this->manager($r); $school=$this->school(); return response()->json(['courses'=>DB::table('grade_subjects as gs')->join('grades as g','g.id','=','gs.grade_id')->join('subjects as s','s.id','=','gs.subject_id')->join('terms as t','t.id','=','gs.term_id')->join('academic_years as ay','ay.id','=','t.academic_year_id')->where('g.school_id',$school)->where('ay.school_id',$school)->select('gs.id','gs.grade_id','gs.subject_id','gs.term_id','g.name as grade_name','s.name as subject_name','t.name as term_name','ay.name as year_name')->orderBy('g.name')->orderBy('s.name')->get(), 'units'=>DB::table('curriculum_units as u')->join('grade_subjects as gs','gs.id','=','u.grade_subject_id')->join('grades as g','g.id','=','gs.grade_id')->where('g.school_id',$school)->select('u.*')->orderBy('u.sort_order')->orderBy('u.id')->get(), 'lessons'=>DB::table('lessons as l')->join('curriculum_units as u','u.id','=','l.curriculum_unit_id')->join('grade_subjects as gs','gs.id','=','u.grade_subject_id')->join('grades as g','g.id','=','gs.grade_id')->where('g.school_id',$school)->select('l.*','u.title as unit_title','u.grade_subject_id')->orderBy('l.sort_order')->orderBy('l.id')->get(), 'resources'=>DB::table('educational_resources')->where('school_id',$school)->orderByDesc('id')->get()]); }
 public function createUnit(Request $r) { $this->manager($r); $d=$r->validate(['grade_subject_id'=>'required|integer','title'=>'required|string|max:190','sort_order'=>'nullable|integer|min:0|max:9999']); $this->gradeSubject((int)$d['grade_subject_id']); $id=DB::table('curriculum_units')->insertGetId(['grade_subject_id'=>$d['grade_subject_id'],'title'=>$d['title'],'sort_order'=>$d['sort_order']??0,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['message'=>'تم إنشاء الوحدة.','unit'=>DB::table('curriculum_units')->find($id)],201); }
 public function updateUnit(Request $r,int $id) { $this->manager($r); $unit=$this->unit($id); $d=$r->validate(['title'=>'sometimes|required|string|max:190','sort_order'=>'sometimes|integer|min:0|max:9999']); DB::table('curriculum_units')->where('id',$id)->update($d+['updated_at'=>now()]); return response()->json(['unit'=>DB::table('curriculum_units')->find($id)]); }
 public function deleteUnit(Request $r,int $id) { $this->manager($r); $this->unit($id); DB::table('curriculum_units')->where('id',$id)->delete(); return response()->json(['message'=>'تم حذف الوحدة ودروسها المرتبطة.']); }
 private function unit(int $id) { return DB::table('curriculum_units as u')->join('grade_subjects as gs','gs.id','=','u.grade_subject_id')->join('grades as g','g.id','=','gs.grade_id')->where('u.id',$id)->where('g.school_id',$this->school())->select('u.*')->firstOrFail(); }
 public function createLesson(Request $r) { $this->manager($r); $d=$r->validate(['curriculum_unit_id'=>'required|integer','title'=>'required|string|max:190','description'=>'nullable|string|max:12000','video_url'=>'nullable|url|max:2048','audio_url'=>'nullable|url|max:2048','sort_order'=>'nullable|integer|min:0|max:9999','is_published'=>'sometimes|boolean']); $unit=$this->unit((int)$d['curriculum_unit_id']); $id=DB::table('lessons')->insertGetId(['curriculum_unit_id'=>$unit->id,'title'=>$d['title'],'description'=>$d['description']??null,'video_url'=>$d['video_url']??null,'audio_url'=>$d['audio_url']??null,'sort_order'=>$d['sort_order']??0,'is_published'=>$d['is_published']??false,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['message'=>'تم إنشاء الدرس.','lesson'=>DB::table('lessons')->find($id)],201); }
 public function updateLesson(Request $r,int $id) { $this->manager($r); $lesson=$this->lesson($id); $d=$r->validate(['title'=>'sometimes|required|string|max:190','description'=>'nullable|string|max:12000','video_url'=>'nullable|url|max:2048','audio_url'=>'nullable|url|max:2048','sort_order'=>'sometimes|integer|min:0|max:9999','is_published'=>'sometimes|boolean']); DB::table('lessons')->where('id',$lesson->id)->update($d+['updated_at'=>now()]); return response()->json(['lesson'=>DB::table('lessons')->find($id)]); }
 public function deleteLesson(Request $r,int $id) { $this->manager($r); $lesson=$this->lesson($id); DB::table('lessons')->where('id',$lesson->id)->delete(); return response()->json(['message'=>'تم حذف الدرس ومرفقاته.']); }
 private function lesson(int $id) { return DB::table('lessons as l')->join('curriculum_units as u','u.id','=','l.curriculum_unit_id')->join('grade_subjects as gs','gs.id','=','u.grade_subject_id')->join('grades as g','g.id','=','gs.grade_id')->where('l.id',$id)->where('g.school_id',$this->school())->select('l.*')->firstOrFail(); }
 public function uploadResource(Request $r) { $this->manager($r); $d=$r->validate(['lesson_id'=>'nullable|integer','title'=>'required|string|max:190','file'=>'required|file|max:51200|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp,mp4,webm,mp3,m4a,wav','is_published'=>'sometimes|boolean']); if(!empty($d['lesson_id']))$this->lesson((int)$d['lesson_id']); $file=$r->file('file'); $path=$file->store('madrasa/'.$this->school().'/resources','local'); $type=match(true){str_starts_with($file->getMimeType(),'image/')=>'image',str_starts_with($file->getMimeType(),'video/')=>'video',str_starts_with($file->getMimeType(),'audio/')=>'audio',$file->getMimeType()==='application/pdf'=>'pdf',default=>'document'}; $id=DB::table('educational_resources')->insertGetId(['school_id'=>$this->school(),'lesson_id'=>$d['lesson_id']??null,'uploaded_by'=>$r->user()->id,'title'=>$d['title'],'resource_type'=>$type,'disk'=>'local','file_path'=>$path,'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize(),'is_published'=>$d['is_published']??false,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['message'=>'تم رفع الملف وحفظه بشكل خاص.','resource'=>DB::table('educational_resources')->find($id)],201); }
 public function downloadResource(Request $r,int $id) {
  $resource=DB::table('educational_resources')->where('id',$id)->where('school_id',$this->school())->first(); abort_unless($resource,404); $allowed=false;
  if(in_array($r->user()->role,['school_admin','super_admin'],true)) $allowed=true;
  elseif($r->user()->role==='student' && $resource->is_published && $resource->lesson_id){
   $profileId=DB::table('student_profiles')->where('school_id',$this->school())->where('user_id',$r->user()->id)->value('id');
   $gradeId=DB::table('lessons as l')->join('curriculum_units as u','u.id','=','l.curriculum_unit_id')->join('grade_subjects as gs','gs.id','=','u.grade_subject_id')->where('l.id',$resource->lesson_id)->value('gs.grade_id');
   $allowed=$profileId && $gradeId && DB::table('student_enrollments as e')->join('classrooms as c','c.id','=','e.classroom_id')->where('e.school_id',$this->school())->where('e.student_profile_id',$profileId)->where('e.status','active')->where('c.grade_id',$gradeId)->exists();
  } elseif($r->user()->role==='teacher' && $resource->lesson_id){
   $teacher=DB::table('teacher_profiles')->where('school_id',$this->school())->where('user_id',$r->user()->id)->value('id');
   $allowed=$teacher && DB::table('teacher_assignments as ta')->join('curriculum_units as u','u.grade_subject_id','=','ta.grade_subject_id')->join('lessons as l','l.curriculum_unit_id','=','u.id')->where('l.id',$resource->lesson_id)->where('ta.school_id',$this->school())->where('ta.teacher_profile_id',$teacher)->exists();
  }
  abort_unless($allowed,403); abort_unless(Storage::disk('local')->exists($resource->file_path),404,'الملف غير موجود على الخادم.'); return Storage::disk('local')->download($resource->file_path,$resource->title);
 }
 public function studentCourses(Request $r)
 {
  abort_unless($r->user()->role==='student',403);
  $school=$this->school();
  $student=DB::table('student_profiles')->where('school_id',$school)->where('user_id',$r->user()->id)->firstOrFail();
  $rows=DB::table('student_enrollments as e')
   ->join('classrooms as c','c.id','=','e.classroom_id')
   ->join('grade_subjects as gs','gs.grade_id','=','c.grade_id')
   ->join('grades as g','g.id','=','gs.grade_id')
   ->join('terms as t','t.id','=','gs.term_id')
   ->join('academic_years as ay','ay.id','=','t.academic_year_id')
   ->join('subjects as s','s.id','=','gs.subject_id')
   ->leftJoin('teacher_assignments as ta',function($j){$j->on('ta.grade_subject_id','=','gs.id')->where(function($q){$q->whereNull('ta.classroom_id')->orWhereColumn('ta.classroom_id','c.id');});})
   ->leftJoin('teacher_profiles as tp','tp.id','=','ta.teacher_profile_id')->leftJoin('users as tu','tu.id','=','tp.user_id')
   ->where('e.school_id',$school)->where('e.student_profile_id',$student->id)->where('e.status','active')
   ->where('c.is_active',true)->where('ay.is_current',true)->where('t.is_active',true)
   ->select('gs.id','gs.grade_id','gs.weekly_lessons','s.name as subject','g.name as grade','c.name as classroom','tu.name as teacher','t.name as term','ay.name as academic_year')
   ->distinct()->get();
  $courses=$rows->map(function($course)use($student,$school){
   $units=DB::table('curriculum_units')->where('grade_subject_id',$course->id)->orderBy('sort_order')->get()->map(function($unit)use($student,$school){
    $lessons=DB::table('lessons as l')->leftJoin('lesson_progress as p',function($j)use($student){$j->on('p.lesson_id','=','l.id')->where('p.student_profile_id',$student->id);})
     ->where('l.curriculum_unit_id',$unit->id)->where('l.is_published',true)
     ->select('l.id','l.title','l.description','l.video_url','l.audio_url','l.sort_order','p.completion_percent','p.completed_at','p.started_at','p.last_viewed_at')
     ->orderBy('l.sort_order')->get()->map(function($lesson)use($school){
      $lesson->resources=DB::table('educational_resources')->where('school_id',$school)->where('lesson_id',$lesson->id)->where('is_published',true)->select('id','title','resource_type','mime_type','file_size')->get();
      return $lesson;
     });
    return ['id'=>$unit->id,'title'=>$unit->title,'lessons'=>$lessons];
   });
   return ['id'=>$course->id,'subject'=>$course->subject,'grade'=>$course->grade,'classroom'=>$course->classroom,'teacher'=>$course->teacher,
    'weekly_lessons'=>$course->weekly_lessons,'term'=>$course->term,'academic_year'=>$course->academic_year,'units'=>$units];
  });
  return response()->json(['courses'=>$courses]);
 }

}
