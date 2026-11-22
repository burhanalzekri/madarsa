<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = ['school_id', 'academic_stage_id', 'name', 'code', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function stage(): BelongsTo { return $this->belongsTo(AcademicStage::class, 'academic_stage_id'); }
    public function classrooms(): HasMany { return $this->hasMany(Classroom::class); }
}
