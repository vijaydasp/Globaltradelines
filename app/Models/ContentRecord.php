<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ContentRecord extends Model {
    protected $guarded=['id'];
    public static function forModule(string $module): static {Modules::get($module);return (new static)->setTable($module);}
}
