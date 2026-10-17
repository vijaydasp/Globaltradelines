<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
final class Audit {
    public static function record(string $action,string $entity,?int $id,string $summary): void {
        DB::insert('admin_activity_logs',['actor_id'=>\Illuminate\Support\Facades\Auth::id(),'action'=>$action,'entity'=>$entity,'entity_id'=>$id,'summary'=>mb_substr($summary,0,255)]);
    }
}
