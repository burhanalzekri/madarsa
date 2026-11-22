<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private int $schoolId;

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->schoolId = DB::table('schools')->insertGetId([
            'name' => 'مدرسة الاختبار', 'slug' => 'announcement-test',
            'primary_color' => '#14584b', 'secondary_color' => '#e8b84a',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_student_only_receives_announcements_targeted_to_students_or_everyone(): void
    {
        $student = User::create([
            'name' => 'طالب اختبار', 'email' => 'student-announcement@test.local',
            'password' => 'password', 'role' => 'student', 'is_active' => true,
        ]);
        $now = now();
        DB::table('school_announcements')->insert([
            ['school_id' => $this->schoolId, 'created_by' => $student->id, 'title' => 'عام', 'body' => 'للجميع', 'category' => 'عام', 'target_roles' => null, 'is_published' => true, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $this->schoolId, 'created_by' => $student->id, 'title' => 'للطلاب', 'body' => 'للطلاب فقط', 'category' => 'أكاديمي', 'target_roles' => json_encode(['student']), 'is_published' => true, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['school_id' => $this->schoolId, 'created_by' => $student->id, 'title' => 'للمعلمين', 'body' => 'للمعلمين فقط', 'category' => 'عام', 'target_roles' => json_encode(['teacher']), 'is_published' => true, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->actingAs($student)->getJson('/api/v1/announcements')
            ->assertOk()->assertJsonCount(2, 'announcements')
            ->assertJsonFragment(['title' => 'عام'])
            ->assertJsonFragment(['title' => 'للطلاب'])
            ->assertJsonMissing(['title' => 'للمعلمين']);
    }

    public function test_student_cannot_manage_school_announcements(): void
    {
        $student = User::create([
            'name' => 'طالب اختبار', 'email' => 'student-admin-announcement@test.local',
            'password' => 'password', 'role' => 'student', 'is_active' => true,
        ]);

        $this->actingAs($student)->getJson('/api/v1/admin/announcements')->assertForbidden();
        $this->actingAs($student)->postJson('/api/v1/admin/announcements', [
            'title' => 'محاولة نشر', 'body' => 'يجب رفضها',
        ])->assertForbidden();
    }
}
