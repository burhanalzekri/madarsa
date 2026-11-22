<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicStage extends Model
{
    protected $fillable = ['school_id', 'name', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function grades(): HasMany { return $this->hasMany(Grade::class); }
}
