<?php
namespace App\Models;
class RolePermission extends \Illuminate\Database\Eloquent\Model {
    public $timestamps=false;
    public $incrementing=false;
    protected $table='role_permissions';
    protected $guarded=[];
}
