<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateSchoolAdmin extends Command
{
    protected $signature = 'madrasa:create-admin {--name= : اسم مدير المدرسة} {--email= : البريد الإلكتروني} {--school= : اسم المدرسة}';
    protected $description = 'إنشاء مدرسة وحساب مدير أولي بطريقة تفاعلية وآمنة';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('اسم مدير المدرسة')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('البريد الإلكتروني للمدير'))));
        $schoolName = trim((string) ($this->option('school') ?: $this->ask('اسم المدرسة')));

        if ($name === '' || $schoolName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('الاسم واسم المدرسة وبريد إلكتروني صحيح حقول مطلوبة.');
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->components->error('هذا البريد مسجل مسبقاً. استخدم بريداً آخر أو استعد الحساب الحالي.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('كلمة مرور قوية للمدير (8 أحرف على الأقل)');
        if (strlen($password) < 12) {
            $this->components->error('استخدم كلمة مرور لا تقل عن 12 حرفاً.');
            return self::FAILURE;
        }
        $confirmation = (string) $this->secret('أعد إدخال كلمة المرور');
        if (!hash_equals($password, $confirmation)) {
            $this->components->error('كلمتا المرور غير متطابقتين.');
            return self::FAILURE;
        }

        [$school, $user] = DB::transaction(function () use ($schoolName, $name, $email, $password): array {
            $school = School::query()->first();
            if (!$school) {
                $slug = Str::slug($schoolName) ?: 'school-' . now()->format('YmdHis');
                $school = School::create([
                    'name' => $schoolName,
                    'slug' => $slug,
                    'platform_name' => 'مدرستي',
                    'primary_color' => '#14584b',
                    'secondary_color' => '#e8b84a',
                    'setup_completed' => false,
                ]);
            }
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'school_admin',
                'is_active' => true,
            ]);
            return [$school, $user];
        });

        $this->components->success('تم إنشاء حساب مدير المدرسة.');
        $this->line('المدرسة: ' . $school->name);
        $this->line('البريد: ' . $user->email);
        $this->warn('احفظ كلمة المرور في مدير كلمات مرور آمن، ولا تشاركها في المحادثات.');
        return self::SUCCESS;
    }
}
