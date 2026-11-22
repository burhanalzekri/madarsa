<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class School extends Model {
 protected $fillable=['name','slug','address','phone','email','logo_path','primary_color','secondary_color','settings','short_name','description','school_type','educational_level','governorate','district','website','cover_path','platform_name','setup_completed','setup_locked_at'];
 protected $casts=['settings'=>'array','setup_completed'=>'boolean','setup_locked_at'=>'datetime'];
}
