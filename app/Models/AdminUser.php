<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUser extends Authenticatable {
    protected $table='admin_users';
    protected $guarded=['id'];
    protected $hidden=['password_hash'];
    public function getAuthPasswordName(): string {return 'password_hash';}
    public function getRememberTokenName(): string {return '';}
    protected function casts(): array {return ['is_active'=>'boolean','auth_version'=>'integer'];}
    public function role(): BelongsTo {return $this->belongsTo(Role::class);}
    public function hasPermission(string $permission): bool {return $this->is_active&&($this->role?->is_super||$this->role?->permissions()->where('permission_key',$permission)->exists());}
}
