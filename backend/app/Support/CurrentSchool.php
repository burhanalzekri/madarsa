<?php
namespace App\Support;
use App\Models\School;
use RuntimeException;
class CurrentSchool {
 public static function id(): int {
  $school=School::query()->first();
  if (!$school) throw new RuntimeException('لم يتم إعداد المدرسة بعد. أكمل معالج الإعداد الأولي.');
  return (int)$school->id;
 }
}
