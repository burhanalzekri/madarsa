<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_csv_can_be_previewed_without_creating_records(): void
    {
        School::create(['name' => 'مدرسة الاختبار', 'slug' => 'test-school']);
        $admin = User::create(['name' => 'مدير الاختبار', 'email' => 'admin@test.local', 'password' => 'secret-password', 'role' => 'school_admin']);
        Sanctum::actingAs($admin);
        $csv = "name,email,password,student_number\nأحمد,ahmed@test.local,ChangeMe-2026,ST-TEST-1\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->post('/api/v1/admin/students/import', ['file' => $file, 'dry_run' => '1']);

        $response->assertOk()->assertJsonPath('total', 1)->assertJsonPath('valid', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseMissing('users', ['email' => 'ahmed@test.local']);
    }

    public function test_student_csv_commit_creates_a_student_with_hashed_password(): void
    {
        School::create(['name' => 'مدرسة الاختبار', 'slug' => 'test-school']);
        $admin = User::create(['name' => 'مدير الاختبار', 'email' => 'admin@test.local', 'password' => 'secret-password', 'role' => 'school_admin']);
        Sanctum::actingAs($admin);
        $csv = "name,email,password,student_number\nأحمد,ahmed@test.local,ChangeMe-2026,ST-TEST-1\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->post('/api/v1/admin/students/import', ['file' => $file, 'dry_run' => '0']);

        $response->assertOk()->assertJsonPath('created', 1)->assertJsonPath('failed', 0);
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'ST-TEST-1']);
        $student = User::where('email', 'ahmed@test.local')->firstOrFail();
        $this->assertTrue(password_verify('ChangeMe-2026', $student->password));
    }
}
