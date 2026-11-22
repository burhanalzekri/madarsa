<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    protected $fillable = ['academic_year_id', 'name', 'term_number', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
}
