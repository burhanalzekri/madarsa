<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data is intentionally prohibited outside local/testing.
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('تم تخطي بيانات العرض التجريبي خارج بيئة local/testing.');
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $schoolId = DB::table('schools')->where('slug', env('SCHOOL_SLUG', 'madrasa-demo'))->value('id');
            if (!$schoolId) {
                $schoolId = DB::table('schools')->insertGetId([
                    'name' => env('SCHOOL_NAME', 'مدرسة مدرستي النموذجية'),
                    'short_name' => 'مدرستي', 'description' => 'بيئة تعليمية تجريبية للتدريب والاختبار.',
                    'slug' => env('SCHOOL_SLUG', 'madrasa-demo'), 'platform_name' => 'مدرستي',
                    'governorate' => 'تعز', 'district' => 'مدينة تعز',
                    'primary_color' => '#14584b', 'secondary_color' => '#e8b84a',
                    'setup_completed' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $stageId = DB::table('academic_stages')->where('school_id', $schoolId)->where('name', 'التعليم الأساسي')->value('id');
            if (!$stageId) $stageId = DB::table('academic_stages')->insertGetId(['school_id'=>$schoolId,'name'=>'التعليم الأساسي','sort_order'=>1,'created_at'=>$now,'updated_at'=>$now]);
            $gradeId = DB::table('grades')->where('school_id',$schoolId)->where('code','G6')->value('id');
            if (!$gradeId) $gradeId = DB::table('grades')->insertGetId(['school_id'=>$schoolId,'academic_stage_id'=>$stageId,'name'=>'الصف السادس الأساسي','code'=>'G6','sort_order'=>6,'created_at'=>$now,'updated_at'=>$now]);

            $yearId = DB::table('academic_years')->where('school_id',$schoolId)->where('is_current',true)->value('id');
            if (!$yearId) $yearId = DB::table('academic_years')->insertGetId(['school_id'=>$schoolId,'name'=>'2026 - 2027','starts_at'=>'2026-09-01','ends_at'=>'2027-06-30','is_current'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $termId = DB::table('terms')->where('academic_year_id',$yearId)->where('term_number',1)->value('id');
            if (!$termId) $termId = DB::table('terms')->insertGetId(['academic_year_id'=>$yearId,'name'=>'الفصل الدراسي الأول','term_number'=>1,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $classId = DB::table('classrooms')->where('school_id',$schoolId)->where('grade_id',$gradeId)->where('academic_year_id',$yearId)->where('name','أ')->value('id');
            if (!$classId) $classId = DB::table('classrooms')->insertGetId(['school_id'=>$schoolId,'grade_id'=>$gradeId,'academic_year_id'=>$yearId,'name'=>'أ','capacity'=>35,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $classBId = DB::table('classrooms')->where('school_id',$schoolId)->where('grade_id',$gradeId)->where('academic_year_id',$yearId)->where('name','ب')->value('id');
            if (!$classBId) $classBId = DB::table('classrooms')->insertGetId(['school_id'=>$schoolId,'grade_id'=>$gradeId,'academic_year_id'=>$yearId,'name'=>'ب','capacity'=>35,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);

            // Demo accounts are created only in local/testing environments.
            if (!app()->environment(['local','testing'])) return;
            $password = env('DEMO_ACCOUNT_PASSWORD');
            if (!is_string($password) || trim($password) === '') {
                throw new \RuntimeException(
                    'يجب ضبط DEMO_ACCOUNT_PASSWORD قبل إنشاء الحسابات التجريبية.'
                );
            }
            $accounts = [
                'admin' => ['name'=>'مدير المدرسة التجريبي','email'=>'admin@madrasa.test','role'=>'school_admin'],
                'teacher' => ['name'=>'محمد عبدالرحمن - معلم','email'=>'teacher@madrasa.test','role'=>'teacher'],
                'student' => ['name'=>'أحمد محمد علي - طالب','email'=>'student@madrasa.test','role'=>'student'],
                'parent' => ['name'=>'محمد علي - ولي أمر','email'=>'parent@madrasa.test','role'=>'parent'],
            ];
            $ids = [];
            foreach ($accounts as $key=>$account) {
                $id = DB::table('users')->where('email',$account['email'])->value('id');
                $payload = ['name'=>$account['name'],'email'=>$account['email'],'password'=>Hash::make($password),'role'=>$account['role'],'is_active'=>true,'updated_at'=>$now];
                if ($id) DB::table('users')->where('id',$id)->update($payload);
                else $id = DB::table('users')->insertGetId($payload + ['created_at'=>$now]);
                $ids[$key] = $id;
            }

            $teacherProfileId = DB::table('teacher_profiles')->where('user_id',$ids['teacher'])->value('id');
            if (!$teacherProfileId) $teacherProfileId = DB::table('teacher_profiles')->insertGetId(['school_id'=>$schoolId,'user_id'=>$ids['teacher'],'employee_number'=>'T-DEMO-001','specialization'=>'الرياضيات','qualification'=>'بكالوريوس','status'=>'active','created_at'=>$now,'updated_at'=>$now]);
            $studentProfileId = DB::table('student_profiles')->where('user_id',$ids['student'])->value('id');
            if (!$studentProfileId) $studentProfileId = DB::table('student_profiles')->insertGetId(['school_id'=>$schoolId,'user_id'=>$ids['student'],'student_number'=>'S-DEMO-001','gender'=>'ذكر','status'=>'active','admission_date'=>'2026-09-01','created_at'=>$now,'updated_at'=>$now]);
            DB::table('parent_student_links')->updateOrInsert(['school_id'=>$schoolId,'parent_user_id'=>$ids['parent'],'student_profile_id'=>$studentProfileId],['relationship'=>'الأب','is_primary'=>true,'created_at'=>$now,'updated_at'=>$now]);

            // Two demo sections, with at least ten real student accounts and enrollments in each.
            $sectionStudentProfiles = ['A'=>[],'B'=>[]];
            $demoNames = ['أحمد محمد علي','سارة خالد حسن','عمر عبدالله صالح','مريم يحيى أحمد','خالد سعيد قاسم','نور عبدالرحمن','ليان محمد حسن','يوسف علي عبدالله','ريم فؤاد صالح','مازن إبراهيم أحمد'];
            foreach ([['class_id'=>$classId,'section'=>'A'],['class_id'=>$classBId,'section'=>'B']] as $section) {
                foreach ($demoNames as $index=>$studentName) {
                    $number = $index + 1;
                    $studentEmail = $section['section']==='A' && $number===1 ? 'student@madrasa.test' : 'student.'.strtolower($section['section']).sprintf('%02d',$number).'@madrasa.test';
                    $studentUserId = $section['section']==='A' && $number===1 ? $ids['student'] : DB::table('users')->where('email',$studentEmail)->value('id');
                    $studentPayload = ['name'=>$studentName.' - طالب '.$section['section'].sprintf('%02d',$number),'email'=>$studentEmail,'password'=>Hash::make($password),'role'=>'student','is_active'=>true,'updated_at'=>$now];
                    if ($studentUserId) DB::table('users')->where('id',$studentUserId)->update($studentPayload);
                    else $studentUserId = DB::table('users')->insertGetId($studentPayload+['created_at'=>$now]);
                    $profileId = DB::table('student_profiles')->where('user_id',$studentUserId)->value('id');
                    if (!$profileId) $profileId = DB::table('student_profiles')->insertGetId(['school_id'=>$schoolId,'user_id'=>$studentUserId,'student_number'=>'S-DEMO-'.$section['section'].sprintf('%03d',$number),'gender'=>$number%2?'ذكر':'أنثى','status'=>'active','admission_date'=>'2026-09-01','created_at'=>$now,'updated_at'=>$now]);
                    $sectionStudentProfiles[$section['section']][] = $profileId;
                    DB::table('student_enrollments')->updateOrInsert(
                        ['student_profile_id'=>$profileId,'academic_year_id'=>$yearId],
                        ['school_id'=>$schoolId,'classroom_id'=>$section['class_id'],'status'=>'active','enrolled_at'=>'2026-09-01','created_at'=>$now,'updated_at'=>$now]
                    );
                    if ($section['section']==='A' && $number===1) $studentProfileId = $profileId;
                }
            }

            $subjectId = DB::table('subjects')->where('school_id',$schoolId)->where('name','الرياضيات')->value('id');
            if (!$subjectId) $subjectId = DB::table('subjects')->insertGetId(['school_id'=>$schoolId,'name'=>'الرياضيات','subject_type'=>'general','language_code'=>'ar','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $gradeSubjectId = DB::table('grade_subjects')->where('grade_id',$gradeId)->where('term_id',$termId)->where('subject_id',$subjectId)->value('id');
            if (!$gradeSubjectId) $gradeSubjectId = DB::table('grade_subjects')->insertGetId(['grade_id'=>$gradeId,'term_id'=>$termId,'subject_id'=>$subjectId,'weekly_lessons'=>5,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('teacher_assignments')->updateOrInsert(['teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gradeSubjectId,'academic_year_id'=>$yearId,'classroom_id'=>$classId],['school_id'=>$schoolId,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('teacher_assignments')->updateOrInsert(['teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gradeSubjectId,'academic_year_id'=>$yearId,'classroom_id'=>$classBId],['school_id'=>$schoolId,'created_at'=>$now,'updated_at'=>$now]);

            // A small, coherent demo timetable and a multi-subject current-term catalog.
            $demoSubjects = [
                ['اللغة العربية',4,'قواعد اللغة والتعبير','الجملة الاسمية والفعلية'],
                ['العلوم',3,'الكائنات الحية والبيئة','خصائص الكائنات الحية'],
                ['اللغة الإنجليزية',3,'Getting Started','Greetings and Introductions'],
                ['التربية الإسلامية',2,'القيم والآداب','الصدق والأمانة'],
            ];
            $scheduleSlots = [
                ['weekday'=>0,'start'=>'08:00','end'=>'08:45'],
                ['weekday'=>1,'start'=>'09:00','end'=>'09:45'],
                ['weekday'=>2,'start'=>'08:00','end'=>'08:45'],
                ['weekday'=>3,'start'=>'10:00','end'=>'10:45'],
                ['weekday'=>4,'start'=>'09:00','end'=>'09:45'],
            ];
            $allGradeSubjects = [['id'=>$gradeSubjectId,'name'=>'الرياضيات']];
            foreach ($demoSubjects as $index=>$item) {
                [$subjectName,$weekly,$unitTitle,$lessonTitle] = $item;
                $sid = DB::table('subjects')->where('school_id',$schoolId)->where('name',$subjectName)->value('id');
                if (!$sid) $sid = DB::table('subjects')->insertGetId(['school_id'=>$schoolId,'name'=>$subjectName,'subject_type'=>'general','language_code'=>'ar','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
                $gsid = DB::table('grade_subjects')->where('grade_id',$gradeId)->where('term_id',$termId)->where('subject_id',$sid)->value('id');
                if (!$gsid) $gsid = DB::table('grade_subjects')->insertGetId(['grade_id'=>$gradeId,'term_id'=>$termId,'subject_id'=>$sid,'weekly_lessons'=>$weekly,'created_at'=>$now,'updated_at'=>$now]);
                if (!DB::table('teacher_assignments')->where('teacher_profile_id',$teacherProfileId)->where('grade_subject_id',$gsid)->where('academic_year_id',$yearId)->where('classroom_id',$classId)->exists()) {
                    DB::table('teacher_assignments')->insert(['school_id'=>$schoolId,'teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gsid,'classroom_id'=>$classId,'academic_year_id'=>$yearId,'created_at'=>$now,'updated_at'=>$now]);
                }
                DB::table('teacher_assignments')->updateOrInsert(['teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gsid,'academic_year_id'=>$yearId,'classroom_id'=>$classBId],['school_id'=>$schoolId,'created_at'=>$now,'updated_at'=>$now]);
                $allGradeSubjects[] = ['id'=>$gsid,'name'=>$subjectName];
                $uid = DB::table('curriculum_units')->where('grade_subject_id',$gsid)->where('title',$unitTitle)->value('id');
                if (!$uid) $uid = DB::table('curriculum_units')->insertGetId(['grade_subject_id'=>$gsid,'title'=>$unitTitle,'sort_order'=>1,'created_at'=>$now,'updated_at'=>$now]);
                if (!DB::table('lessons')->where('curriculum_unit_id',$uid)->where('title',$lessonTitle)->exists()) {
                    DB::table('lessons')->insert(['curriculum_unit_id'=>$uid,'title'=>$lessonTitle,'description'=>'محتوى تجريبي للتعريف بالمقرر، ويمكن للمعلم تعديله وإضافة مواده.','sort_order'=>1,'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]);
                }
                DB::table('lessons')->where('curriculum_unit_id',$uid)->where('title',$lessonTitle)->update(['video_url'=>'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4','audio_url'=>'https://www.w3schools.com/html/horse.mp3','updated_at'=>$now]);
                $practiceTitle = 'تطبيقات وأنشطة: '.$lessonTitle;
                DB::table('lessons')->updateOrInsert(
                    ['curriculum_unit_id'=>$uid,'title'=>$practiceTitle],
                    ['description'=>'نشاط تدريبي تجريبي يتضمن أسئلة قصيرة وتطبيقات تساعد الطالب على ترسيخ المفاهيم.','video_url'=>'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4','audio_url'=>'https://www.w3schools.com/html/horse.mp3','sort_order'=>2,'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]
                );
            }
            foreach ($allGradeSubjects as $index=>$course) {
                $teacherForCourse = $teacherProfileId;
                $slot = $scheduleSlots[$index % count($scheduleSlots)];
                foreach ([['id'=>$classId,'name'=>'أ'],['id'=>$classBId,'name'=>'ب']] as $classroom) {
                    $exists = DB::table('class_schedule_entries')->where('school_id',$schoolId)->where('classroom_id',$classroom['id'])
                        ->where('grade_subject_id',$course['id'])->where('weekday',$slot['weekday'])->exists();
                    if (!$exists) DB::table('class_schedule_entries')->insert([
                        'school_id'=>$schoolId,'classroom_id'=>$classroom['id'],'grade_subject_id'=>$course['id'],
                        'teacher_profile_id'=>$teacherForCourse,'weekday'=>$slot['weekday'],'starts_at'=>$slot['start'],
                        'ends_at'=>$slot['end'],'room'=>'الفصل السادس / '.$classroom['name'],'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }

            $unitId = DB::table('curriculum_units')->where('grade_subject_id',$gradeSubjectId)->where('title','الأعداد والعمليات الحسابية')->value('id');
            if (!$unitId) $unitId = DB::table('curriculum_units')->insertGetId(['grade_subject_id'=>$gradeSubjectId,'title'=>'الأعداد والعمليات الحسابية','sort_order'=>1,'created_at'=>$now,'updated_at'=>$now]);
            $lessonId = DB::table('lessons')->where('curriculum_unit_id',$unitId)->where('title','القيمة المنزلية للأعداد')->value('id');
            if (!$lessonId) $lessonId = DB::table('lessons')->insertGetId(['curriculum_unit_id'=>$unitId,'title'=>'القيمة المنزلية للأعداد','description'=>'يتعرف الطالب إلى المنازل العددية ويطبقها على أمثلة عملية.','sort_order'=>1,'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('lessons')->where('id',$lessonId)->update(['video_url'=>'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4','audio_url'=>'https://www.w3schools.com/html/horse.mp3','updated_at'=>$now]);
            if (!DB::table('lesson_progress')->where('student_profile_id',$studentProfileId)->where('lesson_id',$lessonId)->exists()) DB::table('lesson_progress')->insert(['school_id'=>$schoolId,'student_profile_id'=>$studentProfileId,'lesson_id'=>$lessonId,'completion_percent'=>65,'started_at'=>$now->copy()->subDays(2),'last_viewed_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
            foreach ($sectionStudentProfiles as $sectionCode=>$profileIds) {
                foreach ($profileIds as $studentIndex=>$profileId) {
                    $completion = [100,85,70,55,40,25,10,0,0,0][$studentIndex];
                    DB::table('lesson_progress')->updateOrInsert(
                        ['student_profile_id'=>$profileId,'lesson_id'=>$lessonId],
                        ['school_id'=>$schoolId,'completion_percent'=>$completion,'started_at'=>$completion>0?$now->copy()->subDays(3):null,
                         'completed_at'=>$completion>=100?$now->copy()->subDay():null,'last_viewed_at'=>$completion>0?$now->copy()->subHours($studentIndex+1):null,
                         'created_at'=>$now,'updated_at'=>$now]
                    );
                }
            }
            foreach ([['code'=>'A','class_id'=>$classId,'title'=>'تدريبات القيمة المنزلية - الشعبة أ'],['code'=>'B','class_id'=>$classBId,'title'=>'تدريبات القيمة المنزلية - الشعبة ب']] as $sectionAssignment) {
                DB::table('assignments')->updateOrInsert(
                    ['school_id'=>$schoolId,'title'=>$sectionAssignment['title']],
                    ['teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gradeSubjectId,'classroom_id'=>$sectionAssignment['class_id'],
                     'instructions'=>'حل التدريبات الموجودة في نهاية الدرس، واكتب خطوات الحل بوضوح.',
                     'due_at'=>$now->copy()->addDays(5),'max_score'=>20,'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]
                );
                $assignmentId = DB::table('assignments')->where('school_id',$schoolId)->where('title',$sectionAssignment['title'])->value('id');
                foreach (array_slice($sectionStudentProfiles[$sectionAssignment['code']],0,7) as $submissionIndex=>$profileId) {
                    DB::table('assignment_submissions')->updateOrInsert(
                        ['assignment_id'=>$assignmentId,'student_profile_id'=>$profileId],
                        ['answer_text'=>'إجابة تجريبية للطالب: تم حل التمارين وكتابة خطوات الحل.','submitted_at'=>$now->copy()->subDays(7-$submissionIndex),
                         'score'=>$submissionIndex<5?18-$submissionIndex:null,'teacher_feedback'=>$submissionIndex<5?'أحسنت، واصل التقدم.':null,
                         'graded_by'=>$submissionIndex<5?$ids['teacher']:null,'created_at'=>$now,'updated_at'=>$now]
                    );
                }
            }

            $examId = DB::table('exams')->where('school_id',$schoolId)->where('title','اختبار تجريبي في القيمة المنزلية')->value('id');
            if (!$examId) {
                $examId = DB::table('exams')->insertGetId(['school_id'=>$schoolId,'teacher_profile_id'=>$teacherProfileId,'grade_subject_id'=>$gradeSubjectId,'classroom_id'=>$classId,'title'=>'اختبار تجريبي في القيمة المنزلية','instructions'=>'أجب عن جميع الأسئلة خلال الوقت المحدد.','starts_at'=>$now->copy()->addDays(3),'duration_minutes'=>20,'total_score'=>10,'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]);
                DB::table('exam_questions')->insert([
                    ['exam_id'=>$examId,'question_type'=>'multiple_choice','question_text'=>'ما قيمة الرقم 5 في العدد 352؟','options'=>json_encode(['5','50','500'],JSON_UNESCAPED_UNICODE),'correct_answer'=>'50','score'=>5,'sort_order'=>1,'created_at'=>$now,'updated_at'=>$now],
                    ['exam_id'=>$examId,'question_type'=>'true_false','question_text'=>'العدد 700 يتكون من 7 مئات.','options'=>json_encode(['true','false']),'correct_answer'=>'true','score'=>5,'sort_order'=>2,'created_at'=>$now,'updated_at'=>$now],
                ]);
            }
            DB::table('exams')->where('school_id',$schoolId)->where('title','اختبار تجريبي في القيمة المنزلية')
                ->update(['starts_at'=>$now->copy()->addDays(3),'is_published'=>true,'updated_at'=>$now]);
            $practiceLessonTitle = 'تطبيقات القيمة المنزلية';
            DB::table('lessons')->updateOrInsert(
                ['curriculum_unit_id'=>$unitId,'title'=>$practiceLessonTitle],
                ['description'=>'تدريبات قصيرة على تحليل الأعداد وتحديد قيمة كل رقم بحسب منزلته.','video_url'=>'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4','audio_url'=>'https://www.w3schools.com/html/horse.mp3','sort_order'=>2,
                 'is_published'=>true,'created_at'=>$now,'updated_at'=>$now]
            );

            // Idempotent demo announcements for validating role targeting in each portal.
            $demoAnnouncements = [
                ['title'=>'مرحباً بكم في بوابة مدرستي','body'=>'أهلاً بكم في الفصل الدراسي الأول. تابعوا المقررات والدروس والواجبات من بوابتكم التعليمية، وتحققوا من الإعلانات بشكل دوري.','category'=>'عام','target_roles'=>null],
                ['title'=>'تذكير بمتابعة الواجبات','body'=>'يرجى متابعة واجب تدريبات القيمة المنزلية وتسليمه قبل الموعد المحدد في صفحة الواجبات والاختبارات.','category'=>'أكاديمي','target_roles'=>json_encode(['student'],JSON_UNESCAPED_UNICODE)],
                ['title'=>'لقاء متابعة أولياء الأمور','body'=>'يمكن لأولياء الأمور مراجعة تقارير الأبناء ومتابعة مستوى التحصيل من بوابة ولي الأمر.','category'=>'تنبيه مهم','target_roles'=>json_encode(['parent'],JSON_UNESCAPED_UNICODE)],
            ];
            foreach ($demoAnnouncements as $announcement) {
                DB::table('school_announcements')->updateOrInsert(
                    ['school_id'=>$schoolId,'title'=>$announcement['title']],
                    ['created_by'=>$ids['admin'],'body'=>$announcement['body'],'category'=>$announcement['category'],
                     'target_roles'=>$announcement['target_roles'],'is_published'=>true,'published_at'=>$now,
                     'expires_at'=>null,'created_at'=>$now,'updated_at'=>$now]
                );
            }
        });
    }
}
