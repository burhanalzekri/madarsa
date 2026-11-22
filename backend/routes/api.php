<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{AuthController,StudentController,TeacherController,EnrollmentController,ClassroomController,TeacherAssignmentController,StudentLearningController,TeacherWorkspaceController,AcademicStructureController,CurriculumController,AssessmentController,SchoolReportsController,ClassScheduleController,ParentPortalController,AnnouncementController,StudentAcademicReportController};

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'application' => config('app.name'),
    'version' => trim(file_get_contents(base_path('../VERSION'))),
]));

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/resources/{id}/download', [CurriculumController::class, 'downloadResource']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Student: only student learning endpoints. Object-level ownership checks remain in controllers.
        Route::middleware('role:student')->prefix('student')->group(function () {
            Route::get('/dashboard', [StudentLearningController::class, 'dashboard']);
            Route::get('/courses', [CurriculumController::class, 'studentCourses']);
            Route::get('/schedule', [ClassScheduleController::class, 'studentSchedule']);
            Route::get('/academic-report', [StudentAcademicReportController::class, 'student']);
            Route::put('/lessons/{lessonId}/progress', [StudentLearningController::class, 'saveProgress']);
            Route::post('/assignments/{assignmentId}/submit', [StudentLearningController::class, 'submitAssignment']);
            Route::get('/exams/{examId}', [AssessmentController::class, 'studentExam']);
            Route::post('/exams/{examId}/attempt', [AssessmentController::class, 'startAttempt']);
            Route::post('/exams/{examId}/submit', [AssessmentController::class, 'submitAttempt']);
        });

        Route::middleware('role:parent')->prefix('parent')->group(function () {
            Route::get('/dashboard', [ParentPortalController::class, 'dashboard']);
            Route::get('/children/{studentId}/academic-report', [StudentAcademicReportController::class, 'parent']);
        });

        // Teacher: teaching workspace only; assignment/class ownership must be checked per record.
        Route::middleware('role:teacher')->prefix('teacher')->group(function () {
            Route::get('/dashboard', [TeacherWorkspaceController::class, 'dashboard']);
            Route::get('/announcements', [AnnouncementController::class, 'teacherIndex']);
            Route::post('/announcements', [AnnouncementController::class, 'teacherStore']);
            Route::get('/courses', [TeacherWorkspaceController::class, 'courses']);
            Route::get('/schedule', [ClassScheduleController::class, 'teacherSchedule']);
            Route::get('/assignments', [TeacherWorkspaceController::class, 'assignments']);
            Route::post('/assignments', [TeacherWorkspaceController::class, 'createAssignment']);
            Route::patch('/assignments/{assignmentId}', [TeacherWorkspaceController::class, 'updateAssignment']);
            Route::delete('/assignments/{assignmentId}', [TeacherWorkspaceController::class, 'deleteAssignment']);
            Route::post('/curriculum/units', [TeacherWorkspaceController::class, 'createUnit']);
            Route::post('/curriculum/lessons', [TeacherWorkspaceController::class, 'createLesson']);
            Route::patch('/curriculum/lessons/{lessonId}', [TeacherWorkspaceController::class, 'updateLesson']);
            Route::delete('/curriculum/lessons/{lessonId}', [TeacherWorkspaceController::class, 'deleteLesson']);
            Route::get('/progress-report', [TeacherWorkspaceController::class, 'progressReport']);
            Route::get('/assignments/{assignmentId}/submissions', [TeacherWorkspaceController::class, 'submissions']);
            Route::patch('/submissions/{submissionId}/grade', [TeacherWorkspaceController::class, 'gradeSubmission']);
            Route::post('/attendance', [TeacherWorkspaceController::class, 'recordAttendance']);
            Route::get('/exams', [AssessmentController::class, 'teacherExams']);
            Route::post('/exams', [AssessmentController::class, 'createExam']);
            Route::get('/exams/{examId}/attempts', [AssessmentController::class, 'examAttempts']);
            Route::patch('/exam-attempts/{attemptId}/grade', [AssessmentController::class, 'gradeAttempt']);
            Route::get('/attendance/roster', [AssessmentController::class, 'attendanceRoster']);
            Route::get('/attendance/report', [AssessmentController::class, 'attendanceReport']);
        });

        // School administration: management APIs are never exposed to teacher/student roles.
        Route::middleware('role:school_admin,super_admin')->group(function () {
            Route::get('/admin/reports/overview', [SchoolReportsController::class, 'overview']);
            Route::get('/admin/announcements', [AnnouncementController::class, 'adminIndex']);
            Route::post('/admin/announcements', [AnnouncementController::class, 'store']);
            Route::post('/admin/parent-links', [ParentPortalController::class, 'linkStudent']);
            Route::get('/admin/academic-structure', [AcademicStructureController::class, 'index']);
            Route::get('/admin/curriculum', [CurriculumController::class, 'index']);
            Route::get('/admin/schedule', [ClassScheduleController::class, 'adminIndex']);
            Route::post('/admin/schedule', [ClassScheduleController::class, 'store']);
            Route::patch('/admin/schedule/{id}', [ClassScheduleController::class, 'update']);
            Route::delete('/admin/schedule/{id}', [ClassScheduleController::class, 'destroy']);
            Route::post('/admin/curriculum/units', [CurriculumController::class, 'createUnit']);
            Route::patch('/admin/curriculum/units/{id}', [CurriculumController::class, 'updateUnit']);
            Route::delete('/admin/curriculum/units/{id}', [CurriculumController::class, 'deleteUnit']);
            Route::post('/admin/curriculum/lessons', [CurriculumController::class, 'createLesson']);
            Route::patch('/admin/curriculum/lessons/{id}', [CurriculumController::class, 'updateLesson']);
            Route::delete('/admin/curriculum/lessons/{id}', [CurriculumController::class, 'deleteLesson']);
            Route::post('/admin/curriculum/resources', [CurriculumController::class, 'uploadResource']);
            Route::post('/admin/academic-structure/{type}', [AcademicStructureController::class, 'store']);
            Route::patch('/admin/academic-structure/{type}/{id}', [AcademicStructureController::class, 'update']);
            Route::apiResource('students', StudentController::class);
            Route::apiResource('teachers', TeacherController::class);
            Route::get('/enrollments', [EnrollmentController::class, 'index']);
            Route::post('/enrollments', [EnrollmentController::class, 'store']);
            Route::post('/enrollments/{id}/transfer', [EnrollmentController::class, 'transfer']);
            Route::get('/enrollments/{id}/history', [EnrollmentController::class, 'history']);
            Route::get('/classrooms', [ClassroomController::class, 'index']);
            Route::post('/classrooms', [ClassroomController::class, 'store']);
            Route::patch('/classrooms/{id}', [ClassroomController::class, 'update']);
            Route::get('/teacher-assignments', [TeacherAssignmentController::class, 'index']);
            Route::post('/teacher-assignments', [TeacherAssignmentController::class, 'store']);
        });
    });
});
