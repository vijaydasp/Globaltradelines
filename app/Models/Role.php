<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model {
    protected $guarded=['id'];
    protected function casts(): array {return ['is_super'=>'boolean'];}
    public function permissions(): \Illuminate\Database\Eloquent\Relations\HasMany {return $this->hasMany(RolePermission::class);}
}
