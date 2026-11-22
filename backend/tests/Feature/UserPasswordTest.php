<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_password_is_hashed_once_and_can_be_verified(): void
    {
        $user = User::create([
            'name' => 'اختبار كلمة المرور',
            'email' => 'password-check@test.local',
            'password' => 'Strong-Test-Password-2026',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->assertNotSame('Strong-Test-Password-2026', $user->password);
        $this->assertTrue(Hash::check('Strong-Test-Password-2026', $user->password));
    }
}
