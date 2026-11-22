<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = ['school_id', 'name', 'starts_at', 'ends_at', 'is_current'];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'is_current' => 'boolean'];
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function terms(): HasMany { return $this->hasMany(Term::class); }
}
