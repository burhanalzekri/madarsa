<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class StudentImportController extends Controller
{
    private const MAX_ROWS = 500;

    public function export(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['school_admin', 'super_admin'], true), 403, 'ليست لديك صلاحية تصدير الطلاب.');
        $schoolId = CurrentSchool::id();
        $query = StudentProfile::with(['user', 'enrollments.classroom.grade', 'enrollments.classroom.academicYear'])
            ->where('school_id', $schoolId)->orderBy('id');
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn ($q) => $q->where('student_number', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%')));
        }
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الرقم المدرسي','اسم الطالب','البريد الإلكتروني','الهاتف','الجنس','تاريخ الميلاد','العنوان','تاريخ القبول','الصف','الشعبة','السنة الدراسية','الحالة'], ',', '"', '\\');
            $query->chunk(500, function ($students) use ($out) {
                foreach ($students as $student) {
                    $enrollment = $student->enrollments->firstWhere('status', 'active') ?? $student->enrollments->first();
                    fputcsv($out, [
                        $student->student_number, $student->user?->name, $student->user?->email,
                        $student->user?->phone, $student->gender, $student->birth_date?->format('Y-m-d'),
                        $student->address, $student->admission_date?->format('Y-m-d'),
                        $enrollment?->classroom?->grade?->name, $enrollment?->classroom?->name,
                        $enrollment?->classroom?->academicYear?->name, $student->status,
                    ], ',', '"', '\\');
                }
            });
            fclose($out);
        }, 'madrasa-students-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function template()
    {
        $headers = ['name','email','phone','password','student_number','national_id','birth_date','gender','address','admission_date','classroom_id','notes'];
        // Header-only template: never ship a reusable demo password that could become a real login.
        $csv = "\xEF\xBB\xBF" . implode(',', $headers) . "\r\n";
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="madrasa-students-template.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function import(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['school_admin', 'super_admin'], true), 403, 'ليست لديك صلاحية استيراد الطلاب.');
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'dry_run' => 'nullable|boolean',
            'default_classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $rows = $this->readCsv($request->file('file')->getRealPath());
        if (count($rows) > self::MAX_ROWS) {
            return response()->json(['message' => 'الحد الأقصى للاستيراد هو 500 طالب في الملف الواحد.'], 422);
        }
        if (!$rows) {
            return response()->json(['message' => 'الملف فارغ أو لا يحتوي على صفوف بيانات.'], 422);
        }

        $schoolId = CurrentSchool::id();
        $defaultClassroomId = $request->input('default_classroom_id');
        if ($defaultClassroomId && !Classroom::where('school_id', $schoolId)->whereKey($defaultClassroomId)->exists()) {
            return response()->json(['message' => 'الشعبة الافتراضية لا تتبع هذه المدرسة.'], 422);
        }
        $seenEmails = [];
        $seenNumbers = [];
        $prepared = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $data = $this->normalizeRow($row);
            if (empty($data['classroom_id']) && $defaultClassroomId) $data['classroom_id'] = (string) $defaultClassroomId;
            $validator = Validator::make($data, [
                'name' => 'required|string|max:190',
                'email' => 'nullable|email|max:190|unique:users,email',
                'phone' => 'nullable|string|max:30',
                'password' => 'required|string|min:8|max:100',
                'student_number' => 'nullable|string|max:60|unique:student_profiles,student_number',
                'national_id' => 'nullable|string|max:60',
                'birth_date' => 'nullable|date',
                'gender' => 'nullable|in:male,female',
                'address' => 'nullable|string|max:255',
                'admission_date' => 'nullable|date',
                'classroom_id' => 'nullable|integer|exists:classrooms,id',
                'notes' => 'nullable|string|max:2000',
            ]);
            $errors = $validator->errors()->all();
            if (!empty($data['email'])) {
                $key = strtolower($data['email']);
                if (isset($seenEmails[$key])) $errors[] = 'البريد الإلكتروني مكرر داخل الملف (السطر '.$seenEmails[$key].').';
                $seenEmails[$key] = $line;
            }
            if (!empty($data['student_number'])) {
                $key = strtolower($data['student_number']);
                if (isset($seenNumbers[$key])) $errors[] = 'الرقم المدرسي مكرر داخل الملف (السطر '.$seenNumbers[$key].').';
                $seenNumbers[$key] = $line;
            }
            if (!empty($data['classroom_id']) && !Classroom::where('school_id', $schoolId)->whereKey($data['classroom_id'])->exists()) {
                $errors[] = 'الشعبة المحددة لا تتبع هذه المدرسة.';
            }
            $prepared[] = ['line' => $line, 'data' => $data, 'errors' => array_values(array_unique($errors))];
        }

        $valid = count(array_filter($prepared, fn ($r) => !$r['errors']));
        if ($request->boolean('dry_run', true)) {
            return response()->json([
                'message' => 'اكتملت معاينة الملف. لم يتم حفظ أي طالب بعد.',
                'total' => count($prepared), 'valid' => $valid, 'invalid' => count($prepared) - $valid,
                'rows' => array_map(fn ($r) => ['line' => $r['line'], 'name' => $r['data']['name'] ?? '', 'student_number' => $r['data']['student_number'] ?? '', 'errors' => $r['errors']], $prepared),
            ]);
        }

        $created = 0;
        $failed = [];
        foreach ($prepared as $item) {
            if ($item['errors']) {
                $failed[] = ['line' => $item['line'], 'errors' => $item['errors']];
                continue;
            }
            try {
                DB::transaction(function () use ($item, $schoolId) {
                    $d = $item['data'];
                    $user = User::create([
                        'name' => $d['name'], 'email' => $d['email'] ?: null,
                        'phone' => $d['phone'] ?: null, 'password' => $d['password'],
                        'role' => 'student', 'is_active' => true,
                    ]);
                    $profile = StudentProfile::create([
                        'school_id' => $schoolId, 'user_id' => $user->id,
                        'student_number' => $d['student_number'] ?: ('STU-'.now()->format('ym').'-'.str_pad((string)$user->id, 5, '0', STR_PAD_LEFT)),
                        'national_id' => $d['national_id'] ?: null, 'birth_date' => $d['birth_date'] ?: null,
                        'gender' => $d['gender'] ?: null, 'address' => $d['address'] ?: null,
                        'admission_date' => $d['admission_date'] ?: now()->toDateString(), 'notes' => $d['notes'] ?: null,
                    ]);
                    if (!empty($d['classroom_id'])) {
                        $classroom = Classroom::where('school_id', $schoolId)->findOrFail($d['classroom_id']);
                        StudentEnrollment::create([
                            'school_id' => $schoolId, 'student_profile_id' => $profile->id,
                            'classroom_id' => $classroom->id, 'academic_year_id' => $classroom->academic_year_id,
                            'status' => 'active', 'enrolled_at' => now()->toDateString(),
                        ]);
                    }
                });
                $created++;
            } catch (Throwable $e) {
                report($e);
                $failed[] = ['line' => $item['line'], 'errors' => ['تعذر حفظ السجل؛ تحقق من تكرار البريد أو الرقم المدرسي.']];
            }
        }

        return response()->json([
            'message' => "تم استيراد {$created} طالب بنجاح.",
            'total' => count($prepared), 'created' => $created, 'failed' => count($failed), 'errors' => $failed,
        ], 200);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) return [];
        $first = fgets($handle);
        if ($first === false) { fclose($handle); return []; }
        $delimiter = ',';
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (!$headers) { fclose($handle); return []; }
        $headers = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h)), $headers);
        $records = [];
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count(array_filter($values, fn ($v) => trim((string)$v) !== '')) === 0) continue;
            $record = [];
            foreach ($headers as $i => $header) $record[$header] = $values[$i] ?? null;
            $records[] = $record;
            if (count($records) > self::MAX_ROWS) break;
        }
        fclose($handle);
        return $records;
    }

    private function normalizeRow(array $row): array
    {
        $aliases = [
            'name' => ['name','student_name','اسم الطالب','الاسم'],
            'email' => ['email','البريد الإلكتروني','البريد الالكتروني'],
            'phone' => ['phone','mobile','الهاتف','الجوال'],
            'password' => ['password','كلمة المرور'],
            'student_number' => ['student_number','student_no','الرقم المدرسي','رقم الطالب'],
            'national_id' => ['national_id','رقم الهوية'],
            'birth_date' => ['birth_date','تاريخ الميلاد'],
            'gender' => ['gender','الجنس'],
            'address' => ['address','العنوان'],
            'admission_date' => ['admission_date','تاريخ القبول'],
            'classroom_id' => ['classroom_id','رقم الشعبة','معرف الشعبة'],
            'notes' => ['notes','ملاحظات'],
        ];
        $normalized = [];
        foreach ($aliases as $key => $names) {
            $value = null;
            foreach ($names as $name) if (array_key_exists($name, $row)) { $value = trim((string)$row[$name]); break; }
            $normalized[$key] = $value === '' ? null : $value;
        }
        $gender = strtolower((string)($normalized['gender'] ?? ''));
        $normalized['gender'] = match ($gender) { 'ذكر','m','male' => 'male', 'أنثى','انثى','f','female' => 'female', default => $normalized['gender'] };
        return $normalized;
    }
}
